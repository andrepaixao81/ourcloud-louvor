#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

DOMAIN=""
EMAIL=""
APP_DIR="/var/www/sgml"

usage(){
  cat <<'USAGE'
Uso:
  sudo bash deploy/enable-https-letsencrypt.sh \
    --domain louvor.seudominio.com.br \
    --email admin@seudominio.com.br
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --email|--ssl-email) EMAIL="${2:-}"; shift 2 ;;
    --app-dir) APP_DIR="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

[[ "$EUID" -eq 0 ]] || { echo "[ERRO] Execute como root." >&2; exit 1; }
[[ -n "$DOMAIN" && -n "$EMAIL" ]] || { usage; exit 2; }
[[ -f /etc/nginx/sites-available/sgml ]] || { echo "[ERRO] Vhost SGML não encontrado." >&2; exit 1; }
[[ -f "$APP_DIR/.env" && -f "$APP_DIR/artisan" ]] || { echo "[ERRO] Aplicação não encontrada em $APP_DIR." >&2; exit 1; }
getent ahosts "$DOMAIN" >/dev/null || { echo "[ERRO] O domínio $DOMAIN não resolve por DNS." >&2; exit 1; }
command -v certbot >/dev/null || { apt-get update; apt-get install -y certbot python3-certbot-nginx; }

nginx -t
certbot --nginx --non-interactive --agree-tos --email "$EMAIL" -d "$DOMAIN" --redirect
nginx -t
systemctl reload nginx

set_env(){
  local key="$1" value="$2"
  if grep -q "^${key}=" "$APP_DIR/.env"; then
    sed -i "s#^${key}=.*#${key}=${value}#" "$APP_DIR/.env"
  else
    printf '%s=%s\n' "$key" "$value" >> "$APP_DIR/.env"
  fi
}
set_env APP_URL "https://${DOMAIN}"
set_env SESSION_SECURE_COOKIE true
set_env GOOGLE_REDIRECT_URI "https://${DOMAIN}/integrations/google/callback"
chown root:sgml "$APP_DIR/.env"
chmod 0640 "$APP_DIR/.env"

cd "$APP_DIR"
sudo -u sgml php8.3 artisan optimize:clear
sudo -u sgml php8.3 artisan config:cache
sudo -u sgml php8.3 artisan route:cache
sudo -u sgml php8.3 artisan view:cache
systemctl reload php8.3-fpm

STATUS="$(curl -ksS -o /dev/null -w '%{http_code}' --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/login" || true)"
case "$STATUS" in
  200|302) echo "[OK] HTTPS ativo: https://$DOMAIN" ;;
  *) echo "[AVISO] Certificado instalado, mas /login respondeu HTTP $STATUS. Rode scripts/verify-install.sh." ;;
esac
