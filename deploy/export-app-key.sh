#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
APP_DIR="/var/www/sgml"
OUTPUT="/root/sgml-app-key.txt"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir) APP_DIR="${2:-}"; shift 2 ;;
    --output) OUTPUT="${2:-}"; shift 2 ;;
    -h|--help) echo "Uso: sudo bash deploy/export-app-key.sh [--output /root/sgml-app-key.txt]"; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done
[[ "$EUID" -eq 0 ]] || { echo "Execute como root." >&2; exit 1; }
[[ -f "$APP_DIR/.env" ]] || { echo ".env não encontrado em $APP_DIR." >&2; exit 1; }
KEY="$(grep -m1 '^APP_KEY=' "$APP_DIR/.env" | cut -d= -f2-)"
[[ -n "$KEY" ]] || { echo "APP_KEY ausente." >&2; exit 1; }
printf '%s\n' "$KEY" > "$OUTPUT"
chmod 0600 "$OUTPUT"
FINGERPRINT="$(printf '%s' "$KEY" | sha256sum | cut -c1-16)"
echo "[OK] APP_KEY exportada para $OUTPUT (permissão 0600)."
echo "[INFO] Fingerprint: $FINGERPRINT"
echo "[ATENÇÃO] O arquivo contém um segredo da instalação. Transporte-o com segurança e apague-o após a migração."
