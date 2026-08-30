#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

APP_DIR="/var/www/sgml"
FILE=""
ASSUME_YES=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir) APP_DIR="$2"; shift 2;;
    --file) FILE="$2"; shift 2;;
    --yes) ASSUME_YES=1; shift;;
    *) echo "Opção desconhecida: $1" >&2; exit 2;;
  esac
done

[[ "$EUID" -eq 0 ]] || { echo "Execute como root (sudo)." >&2; exit 1; }
[[ -n "$FILE" && -f "$FILE" ]] || { echo "[ERRO] Informe --file com um backup .dump existente." >&2; exit 1; }
[[ -f "$APP_DIR/.env" && -f "$APP_DIR/artisan" ]] || { echo "[ERRO] Instalação SGML não encontrada em $APP_DIR." >&2; exit 1; }
command -v pg_restore >/dev/null || { echo "[ERRO] pg_restore não encontrado. Instale postgresql-client." >&2; exit 1; }
command -v php8.3 >/dev/null || { echo "[ERRO] php8.3 não encontrado." >&2; exit 1; }
id sgml >/dev/null 2>&1 || { echo "[ERRO] usuário de sistema sgml não existe." >&2; exit 1; }

read_env() {
  local key="$1" value
  value="$(grep -m1 "^${key}=" "$APP_DIR/.env" | cut -d= -f2- || true)"
  value="${value%\"}"; value="${value#\"}"; value="${value%\'}"; value="${value#\'}"
  printf '%s' "$value"
}

DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"
APP_KEY="$(read_env APP_KEY)"
[[ -n "$DB_HOST" && -n "$DB_PORT" && -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] || { echo "[ERRO] Banco incompleto no .env." >&2; exit 1; }
[[ -n "$APP_KEY" ]] || { echo "[ERRO] APP_KEY ausente. Para migração, restaure a APP_KEY da VM de origem antes do banco." >&2; exit 1; }

pg_restore --list "$FILE" >/dev/null
SHA="$(sha256sum "$FILE" | awk '{print $1}')"
echo "[OK] Backup reconhecido pelo pg_restore."
echo "[INFO] SHA-256: $SHA"
echo "[INFO] Destino: PostgreSQL $DB_DATABASE em $DB_HOST:$DB_PORT"

echo "[ATENÇÃO] A restauração substituirá os objetos do banco de destino pelos dados do backup."
if [[ "$ASSUME_YES" -ne 1 ]]; then
  read -r -p "Digite o nome do banco ($DB_DATABASE) para confirmar: " CONFIRM
  [[ "$CONFIRM" == "$DB_DATABASE" ]] || { echo "Cancelado."; exit 1; }
fi

QUEUE_WAS_ENABLED=0
TIMER_WAS_ENABLED=0
systemctl is-enabled --quiet sgml-queue.service 2>/dev/null && QUEUE_WAS_ENABLED=1 || true
systemctl is-enabled --quiet sgml-scheduler.timer 2>/dev/null && TIMER_WAS_ENABLED=1 || true

cleanup() {
  cd "$APP_DIR" 2>/dev/null || true
  sudo -u sgml php8.3 artisan up >/dev/null 2>&1 || true
  if [[ "$QUEUE_WAS_ENABLED" -eq 1 ]]; then systemctl restart sgml-queue.service >/dev/null 2>&1 || true; fi
  if [[ "$TIMER_WAS_ENABLED" -eq 1 ]]; then systemctl restart sgml-scheduler.timer >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

cd "$APP_DIR"
sudo -u sgml php8.3 artisan down --retry=30 || true
systemctl stop sgml-queue.service >/dev/null 2>&1 || true
systemctl stop sgml-scheduler.timer >/dev/null 2>&1 || true

# Guarda uma cópia pré-restauração se o banco atual estiver acessível.
PRE="/var/backups/sgml/pre-restore-$(date +%Y%m%d_%H%M%S).dump"
mkdir -p /var/backups/sgml
chmod 700 /var/backups/sgml
if command -v pg_dump >/dev/null; then
  if PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -Fc --no-owner --no-privileges -f "$PRE" "$DB_DATABASE"; then
    chmod 600 "$PRE"
    echo "[OK] Backup pré-restauração: $PRE"
  else
    rm -f "$PRE"
    echo "[AVISO] Não foi possível criar backup pré-restauração; prosseguindo somente porque houve confirmação explícita." >&2
  fi
fi

PGPASSWORD="$DB_PASSWORD" pg_restore \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction \
  "$FILE"

echo "[OK] Banco restaurado."
sudo -u sgml php8.3 artisan optimize:clear
sudo -u sgml php8.3 artisan migrate --force
sudo -u sgml php8.3 artisan config:cache
sudo -u sgml php8.3 artisan route:cache
sudo -u sgml php8.3 artisan view:cache
sudo -u sgml php8.3 artisan up
if [[ "$QUEUE_WAS_ENABLED" -eq 1 ]]; then systemctl restart sgml-queue.service; fi
if [[ "$TIMER_WAS_ENABLED" -eq 1 ]]; then systemctl restart sgml-scheduler.timer; fi
trap - EXIT

echo "[OK] Restauração concluída. Confira domínio, APP_KEY, fila/scheduler e feeds de agenda antes de liberar a nova VM."
