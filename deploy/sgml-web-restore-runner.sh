#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

REQUEST_ID="${1:-}"
APP_DIR="/var/www/sgml"
REQUEST_DIR="/var/lib/sgml/restore-requests"
STATUS_DIR="/var/lib/sgml/restore-status"
BACKUP_DIR="/var/backups/sgml/database"
LOG_DIR="/var/log/sgml"

[[ "$EUID" -eq 0 ]] || { echo "runner precisa executar como root" >&2; exit 1; }
[[ "$REQUEST_ID" =~ ^r[a-f0-9]{24}$ ]] || { echo "request id inválido" >&2; exit 2; }
REQUEST_FILE="$REQUEST_DIR/$REQUEST_ID.json"
STATUS_FILE="$STATUS_DIR/$REQUEST_ID.json"
LOG_FILE="$LOG_DIR/restore-$REQUEST_ID.log"

install -d -o root -g sgml -m 0750 /var/lib/sgml
install -d -o sgml -g sgml -m 0700 "$REQUEST_DIR" "$STATUS_DIR"
chown root:sgml /var/lib/sgml
chmod 0750 /var/lib/sgml
chown sgml:sgml "$REQUEST_DIR" "$STATUS_DIR"
chmod 0700 "$REQUEST_DIR" "$STATUS_DIR"
install -d -o root -g sgml -m 0750 "$LOG_DIR"

write_status() {
  local state="$1" message="$2" finished="${3:-}"
  php8.3 -r '
    $path=$argv[1]; $id=$argv[2]; $state=$argv[3]; $message=$argv[4]; $finished=$argv[5];
    $data=["request_id"=>$id,"state"=>$state,"message"=>$message,"updated_at"=>date(DATE_ATOM)];
    if ($finished !== "") $data["finished_at"]=$finished;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
  ' "$STATUS_FILE" "$REQUEST_ID" "$state" "$message" "$finished"
  chown sgml:sgml "$STATUS_FILE"
  chmod 0600 "$STATUS_FILE"
}

[[ -f "$REQUEST_FILE" ]] || { write_status failed "Solicitação de restauração não encontrada." "$(date -Iseconds)"; exit 1; }
FILENAME="$(php8.3 -r '$d=json_decode(file_get_contents($argv[1]),true); echo basename((string)($d["filename"]??""));' "$REQUEST_FILE")"
EXPECTED_SHA="$(php8.3 -r '$d=json_decode(file_get_contents($argv[1]),true); echo (string)($d["sha256"]??"");' "$REQUEST_FILE")"
[[ "$FILENAME" =~ ^sgml-db-[0-9]{8}_[0-9]{6}-[a-f0-9]{8}\.dump$ ]] || { write_status failed "Nome do backup solicitado é inválido." "$(date -Iseconds)"; exit 1; }
BACKUP_FILE="$BACKUP_DIR/$FILENAME"
[[ -f "$BACKUP_FILE" && ! -L "$BACKUP_FILE" ]] || { write_status failed "Arquivo de backup não encontrado no servidor." "$(date -Iseconds)"; exit 1; }
ACTUAL_SHA="$(sha256sum "$BACKUP_FILE" | awk '{print $1}')"
[[ -z "$EXPECTED_SHA" || "$EXPECTED_SHA" == "$ACTUAL_SHA" ]] || { write_status failed "SHA-256 do backup mudou após a solicitação. Restauração cancelada." "$(date -Iseconds)"; exit 1; }

write_status running "Restauração iniciada. O SGML pode ficar indisponível por alguns instantes."
set +e
"$APP_DIR/deploy/restore-database-backup.sh" --file "$BACKUP_FILE" --app-dir "$APP_DIR" --yes >"$LOG_FILE" 2>&1
RC=$?
set -e
chown root:sgml "$LOG_FILE" || true
chmod 0640 "$LOG_FILE" || true

if [[ "$RC" -eq 0 ]]; then
  write_status success "Banco restaurado com sucesso. Migrations, caches, fila e scheduler foram revalidados." "$(date -Iseconds)"
  rm -f "$REQUEST_FILE"
  exit 0
fi

TAIL="$(tail -n 15 "$LOG_FILE" 2>/dev/null | tr '\n' ' ' | cut -c1-1800)"
write_status failed "A restauração falhou e o SGML tentou sair do modo de manutenção. Detalhe técnico: $TAIL" "$(date -Iseconds)"
exit "$RC"
