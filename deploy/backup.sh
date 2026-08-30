#!/usr/bin/env bash
set -euo pipefail
umask 077

APP_DIR="${APP_DIR:-/var/www/sgml}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/sgml}"
STAMP="$(date +%Y%m%d_%H%M%S)"
TARGET="$BACKUP_DIR/$STAMP"

[[ -f "$APP_DIR/.env" ]] || { echo "Arquivo $APP_DIR/.env não encontrado."; exit 1; }
mkdir -p "$TARGET"

read_env() {
  local key="$1"
  local value
  value="$(grep -m1 "^${key}=" "$APP_DIR/.env" | cut -d= -f2- || true)"
  value="${value%\"}"; value="${value#\"}"
  value="${value%\'}"; value="${value#\'}"
  printf '%s' "$value"
}

DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"

[[ -n "$DB_HOST" && -n "$DB_PORT" && -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] || {
  echo 'Configuração PostgreSQL incompleta no .env.'
  exit 1
}

PGPASSWORD="$DB_PASSWORD" pg_dump \
  -h "$DB_HOST" \
  -p "$DB_PORT" \
  -U "$DB_USERNAME" \
  -Fc "$DB_DATABASE" > "$TARGET/database.dump"

tar -czf "$TARGET/storage.tar.gz" -C "$APP_DIR" storage/app 2>/dev/null || true
cp "$APP_DIR/.env" "$TARGET/env.backup"
chmod 600 "$TARGET/env.backup" "$TARGET/database.dump" "$TARGET/storage.tar.gz" 2>/dev/null || true

find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +

echo "Backup concluído em $TARGET"
