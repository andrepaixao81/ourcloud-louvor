#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

REQUEST_ID="${1:-}"
REQUEST_DIR="/var/lib/sgml/restore-requests"
RUNNER="/usr/local/sbin/sgml-web-restore-runner"

[[ "$EUID" -eq 0 ]] || { echo "dispatcher precisa executar como root" >&2; exit 1; }
[[ "$REQUEST_ID" =~ ^r[a-f0-9]{24}$ ]] || { echo "request id inválido" >&2; exit 2; }
[[ -x "$RUNNER" ]] || { echo "runner de restauração ausente" >&2; exit 1; }
REQUEST_FILE="$REQUEST_DIR/$REQUEST_ID.json"
[[ -f "$REQUEST_FILE" && ! -L "$REQUEST_FILE" ]] || { echo "solicitação de restauração não encontrada" >&2; exit 1; }
OWNER="$(stat -c '%U' "$REQUEST_FILE")"
[[ "$OWNER" == "sgml" ]] || { echo "solicitação com proprietário inválido" >&2; exit 1; }

UNIT="sgml-restore-${REQUEST_ID}"
if systemctl list-units --all --full --no-legend | awk '{print $1}' | grep -Fxq "${UNIT}.service"; then
  echo "restauração já agendada"
  exit 0
fi

systemd-run --quiet --collect \
  --unit="$UNIT" \
  --on-active=2s \
  --timer-property=AccuracySec=1s \
  "$RUNNER" "$REQUEST_ID"

echo "restauração $REQUEST_ID agendada"
