#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="/var/www/sgml"
DOMAIN=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""
ADMIN_NAME="Administrador"
ORG_NAME=""
ORG_SLUG=""
SSL_EMAIL=""
SKIP_HTTPS=0
APP_KEY_FILE=""
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="sgml"
DB_USER="sgml"
PHP_BIN="/usr/bin/php8.3"
PHP_FPM_SERVICE="php8.3-fpm"
PHP_FPM_SOCKET="/run/php/php8.3-fpm-sgml.sock"
NODE_MAJOR="24"
GENERATED_ADMIN_PASSWORD=0

usage() {
  cat <<'USAGE'
SGML - instalação limpa no Ubuntu Server 24.04 LTS (ARM64 ou AMD64)

Uso recomendado:
  sudo bash deploy/install.sh \
    --domain louvor.seudominio.com.br \
    --admin-email admin@seudominio.com.br \
    --organization "Nome da Igreja"

Opções:
  --domain DOMINIO               Domínio público do SGML (obrigatório)
  --admin-email EMAIL            E-mail do Administrador inicial (obrigatório)
  --admin-password SENHA         Senha inicial (opcional; se omitida, será gerada)
  --admin-name NOME              Nome do Administrador (padrão: Administrador)
  --organization NOME            Nome da igreja/organização (obrigatório)
  --organization-slug SLUG       Slug opcional; se omitido, é gerado do nome
  --ssl-email EMAIL              E-mail do Let's Encrypt (padrão: admin-email)
  --skip-https                   Não solicitar certificado agora
  --app-key-file ARQUIVO         Reutilizar APP_KEY de instalação anterior
  --app-dir DIRETORIO            Diretório da aplicação (padrão: /var/www/sgml)
  -h, --help                     Exibir esta ajuda

Observação para migração:
  Para restaurar backup de outra VM e manter segredos criptografados legíveis,
  use a APP_KEY da instalação anterior com --app-key-file.
USAGE
}

log(){ printf '[INFO] %s\n' "$*"; }
ok(){ printf '[OK] %s\n' "$*"; }
warn(){ printf '[AVISO] %s\n' "$*" >&2; }
die(){ printf '[ERRO] %s\n' "$*" >&2; exit 1; }

normalize_public_permissions(){
  chmod 0711 "$APP_DIR"
  chmod 0755 "$APP_DIR/public"
  find "$APP_DIR/public" -type d -print0 | xargs -0 -r chmod 0755
  find "$APP_DIR/public" -type f -print0 | xargs -0 -r chmod 0644
  [[ ! -d "$APP_DIR/public/build" ]] || chmod 0755 "$APP_DIR/public/build"
  if [[ -d "$APP_DIR/public/build/assets" ]]; then
    chmod 0755 "$APP_DIR/public/build/assets"
    find "$APP_DIR/public/build/assets" -type f -print0 | xargs -0 -r chmod 0644
  fi
  [[ ! -f "$APP_DIR/public/build/manifest.json" ]] || chmod 0644 "$APP_DIR/public/build/manifest.json"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --admin-email) ADMIN_EMAIL="${2:-}"; shift 2 ;;
    --admin-password) ADMIN_PASSWORD="${2:-}"; shift 2 ;;
    --admin-name) ADMIN_NAME="${2:-}"; shift 2 ;;
    --organization) ORG_NAME="${2:-}"; shift 2 ;;
    --organization-slug) ORG_SLUG="${2:-}"; shift 2 ;;
    --ssl-email) SSL_EMAIL="${2:-}"; shift 2 ;;
    --skip-https) SKIP_HTTPS=1; shift ;;
    --app-key-file) APP_KEY_FILE="${2:-}"; shift 2 ;;
    --app-dir) APP_DIR="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
done

[[ "$EUID" -eq 0 ]] || die "Execute como root: sudo bash deploy/install.sh ..."
[[ -f /etc/os-release ]] || die "Não foi possível identificar o sistema operacional."
# shellcheck disable=SC1091
source /etc/os-release
[[ "${ID:-}" == "ubuntu" && "${VERSION_ID:-}" == "24.04" ]] || die "Este instalador foi preparado para Ubuntu Server 24.04 LTS. Detectado: ${PRETTY_NAME:-desconhecido}."
ARCH="$(dpkg --print-architecture)"
[[ "$ARCH" == "arm64" || "$ARCH" == "amd64" ]] || die "Arquitetura não suportada por este instalador: $ARCH."

[[ -n "$DOMAIN" ]] || { usage; die "Informe --domain."; }
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ && "$DOMAIN" == *.* ]] || die "Domínio inválido: $DOMAIN"
[[ "$ADMIN_EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || die "Informe um --admin-email válido."
[[ -n "$ORG_NAME" ]] || { usage; die "Informe --organization."; }
SSL_EMAIL="${SSL_EMAIL:-$ADMIN_EMAIL}"

if [[ -z "$ORG_SLUG" ]]; then
  ORG_SLUG="$(printf '%s' "$ORG_NAME" | iconv -f UTF-8 -t ASCII//TRANSLIT 2>/dev/null | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g' || true)"
  ORG_SLUG="${ORG_SLUG:-minha-igreja}"
fi
[[ "$ORG_SLUG" =~ ^[a-z0-9-]+$ ]] || die "organization-slug inválido: $ORG_SLUG"

for text in "$ADMIN_NAME" "$ORG_NAME"; do
  [[ "$text" != *$'\n'* && "$text" != *'"'* && "$text" != *'$'* && "$text" != *'\\'* ]] || die "Nome de administrador/organização contém caractere não aceito pelo instalador (aspas duplas, cifrão, barra invertida ou quebra de linha)."
done

if [[ -z "$ADMIN_PASSWORD" ]]; then
  ADMIN_PASSWORD="$(openssl rand -hex 16)"
  GENERATED_ADMIN_PASSWORD=1
fi
[[ ${#ADMIN_PASSWORD} -ge 12 ]] || die "A senha inicial deve possuir pelo menos 12 caracteres."
[[ "$ADMIN_PASSWORD" != *$'\n'* && "$ADMIN_PASSWORD" != *'"'* && "$ADMIN_PASSWORD" != *'$'* && "$ADMIN_PASSWORD" != *'\\'* ]] || die "A senha passada ao instalador não pode conter aspas duplas, cifrão, barra invertida ou quebra de linha. Você poderá usar outra senha depois do primeiro login."

if [[ -n "$APP_KEY_FILE" ]]; then
  [[ -f "$APP_KEY_FILE" ]] || die "Arquivo APP_KEY não encontrado: $APP_KEY_FILE"
  RESTORED_APP_KEY="$(tr -d '\r\n' < "$APP_KEY_FILE")"
  RESTORED_APP_KEY="${RESTORED_APP_KEY#APP_KEY=}"
  [[ "$RESTORED_APP_KEY" == base64:* || ${#RESTORED_APP_KEY} -ge 32 ]] || die "O arquivo informado não parece conter uma APP_KEY válida."
else
  RESTORED_APP_KEY=""
fi

[[ -f "$SOURCE_DIR/artisan" && -f "$SOURCE_DIR/composer.json" ]] || die "Execute este instalador a partir do pacote/repositório completo do SGML."

log "Sistema: ${PRETTY_NAME}; arquitetura: $ARCH."
log "Aplicação: $APP_DIR; domínio: $DOMAIN."

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y \
  ca-certificates curl wget unzip xz-utils rsync openssl sudo acl \
  nginx certbot python3-certbot-nginx \
  postgresql postgresql-client \
  php8.3-cli php8.3-fpm php8.3-pgsql php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-gd php8.3-opcache
ok "Pacotes do Ubuntu instalados (PHP 8.3, PostgreSQL, Nginx e utilitários)."

# Usuário dedicado da aplicação.
getent group sgml >/dev/null || groupadd --system sgml
if ! id sgml >/dev/null 2>&1; then
  useradd --system --gid sgml --home-dir "$APP_DIR" --no-create-home --shell /usr/sbin/nologin sgml
fi
usermod -aG sgml www-data
ok "Usuário de serviço sgml preparado."

# Composer oficial com verificação do instalador.
if ! command -v composer >/dev/null 2>&1; then
  log "Instalando Composer."
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  ACTUAL_CHECKSUM="$(php8.3 -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || die "Checksum do instalador do Composer inválido."
  php8.3 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f /tmp/composer-setup.php
fi
COMPOSER_BIN="$(command -v composer)"
composer83(){ "$PHP_BIN" "$COMPOSER_BIN" "$@"; }
composer83 --version >/dev/null
ok "Composer será executado explicitamente por $PHP_BIN."

# Node.js 24 LTS oficial. O instalador resolve o patch mais recente da linha v24.
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1 || [[ "$(node -p 'Number(process.versions.node.split(".")[0])' 2>/dev/null || echo 0)" -ne 24 ]]; then
  log "Instalando Node.js ${NODE_MAJOR} LTS a partir de nodejs.org."
  NODE_ARCH="$([[ "$ARCH" == "arm64" ]] && echo arm64 || echo x64)"
  NODE_BASE="https://nodejs.org/dist/latest-v${NODE_MAJOR}.x"
  curl -fsSL "$NODE_BASE/SHASUMS256.txt" -o /tmp/node-shasums.txt
  NODE_TARBALL="$(awk -v a="$NODE_ARCH" '$2 ~ ("node-v[0-9.]+-linux-" a "\\.tar\\.xz$") {print $2; exit}' /tmp/node-shasums.txt)"
  [[ -n "$NODE_TARBALL" ]] || die "Não foi possível localizar o binário Node.js para $NODE_ARCH."
  curl -fsSL "$NODE_BASE/$NODE_TARBALL" -o "/tmp/$NODE_TARBALL"
  (cd /tmp && grep " $NODE_TARBALL$" node-shasums.txt | sha256sum -c -)
  rm -rf /usr/local/lib/nodejs/sgml-node
  mkdir -p /usr/local/lib/nodejs/sgml-node
  tar -xJf "/tmp/$NODE_TARBALL" -C /usr/local/lib/nodejs/sgml-node --strip-components=1
  for bin in node npm npx corepack; do ln -sfn "/usr/local/lib/nodejs/sgml-node/bin/$bin" "/usr/local/bin/$bin"; done
  rm -f "/tmp/$NODE_TARBALL" /tmp/node-shasums.txt
fi
ok "Node.js $(node --version); npm $(npm --version)."

# Banco de dados local. PostgreSQL permanece escutando localmente conforme padrão do Ubuntu.
DB_PASSWORD="$(openssl rand -hex 24)"
systemctl enable --now postgresql nginx "$PHP_FPM_SERVICE"
sudo -u postgres psql -v ON_ERROR_STOP=1 --set=db_user="$DB_USER" --set=db_name="$DB_NAME" --set=db_password="$DB_PASSWORD" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'db_user', :'db_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'db_user') \gexec
SELECT format('ALTER ROLE %I WITH LOGIN PASSWORD %L', :'db_user', :'db_password') \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db_name', :'db_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name') \gexec
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'db_name') \gexec
SELECT format('GRANT CONNECT, TEMPORARY ON DATABASE %I TO %I', :'db_name', :'db_user') \gexec
SQL
ok "Banco PostgreSQL '$DB_NAME' e usuário '$DB_USER' preparados."

# Código da aplicação.
install -d -o root -g sgml -m 0750 "$APP_DIR"
rsync -a --delete \
  --exclude='.env' --exclude='vendor' --exclude='node_modules' --exclude='public/storage' \
  "$SOURCE_DIR/" "$APP_DIR/"
cd "$APP_DIR"
install -d -o sgml -g sgml -m 0770 storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public

# Arquivo .env inicial.
cat > .env <<EOF_ENV
APP_NAME="OurCloud Louvor"
APP_VERSION=1.0.0-rc88
APP_ENV=production
APP_KEY=${RESTORED_APP_KEY}
APP_DEBUG=false
APP_URL=http://${DOMAIN}
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_SSLMODE=prefer

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@${DOMAIN}"
MAIL_FROM_NAME="\${APP_NAME}"

GOOGLE_CALENDAR_ENABLED=false
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://${DOMAIN}/integrations/google/callback
GOOGLE_CALENDAR_ID=primary

SGML_ADMIN_NAME="${ADMIN_NAME}"
SGML_ADMIN_EMAIL=${ADMIN_EMAIL}
SGML_ADMIN_PASSWORD=${ADMIN_PASSWORD}
SGML_ORGANIZATION_NAME="${ORG_NAME}"
SGML_ORGANIZATION_SLUG=${ORG_SLUG}

SGML_BACKUP_PATH=/var/backups/sgml/database
SGML_PG_DUMP_BINARY=/usr/bin/pg_dump
SGML_PG_RESTORE_BINARY=/usr/bin/pg_restore
SGML_BACKUP_TIMEOUT=300
SGML_BACKUP_MAX_FILES=30
SGML_BACKUP_UPLOAD_MAX_KB=524288
SGML_RESTORE_REQUEST_PATH=/var/lib/sgml/restore-requests
SGML_RESTORE_STATUS_PATH=/var/lib/sgml/restore-status
SGML_RESTORE_DISPATCHER=/usr/local/sbin/sgml-web-restore-dispatch
EOF_ENV
chown root:sgml .env
chmod 0640 .env

# Dependências PHP e frontend.
php8.3 scripts/preflight.php
php8.3 scripts/smoke-release-platform.php
php8.3 scripts/smoke-repertoire-livewire.php
php8.3 scripts/smoke-rehearsal-ux.php
php8.3 scripts/smoke-song-reference.php
php8.3 scripts/smoke-reset-test-service.php
php8.3 scripts/smoke-vite-asset-paths.php
php8.3 scripts/smoke-rc75-recurrence-whatsapp.php
php8.3 scripts/smoke-rc76-availability-manual-alerts.php
php8.3 scripts/smoke-rc77-schedule-blade.php
php8.3 scripts/smoke-rc78-special-event-lifecycle.php
php8.3 scripts/smoke-rc79-agenda-custom-events.php
php8.3 scripts/smoke-rc80-account-phone.php
php8.3 scripts/smoke-rc81-birthdays.php
php8.3 scripts/smoke-rc82-minister-ux-performance.php
php8.3 scripts/smoke-rc83-uiux.php
php8.3 scripts/smoke-rc84-minister-song-keys.php
php8.3 scripts/smoke-rc85-friendly-403.php
php8.3 scripts/smoke-rc86-birthday-privacy-backup-runtime.php
php8.3 scripts/smoke-rc87-birthday-member-only-backup-path.php
php8.3 scripts/smoke-rc88-open-source.php
node --check resources/js/app.js
export COMPOSER_ALLOW_SUPERUSER=1
if [[ -f composer.lock ]]; then
  composer83 install --no-dev --prefer-dist --no-interaction --optimize-autoloader
else
  warn "composer.lock não está presente no pacote; o Composer resolverá as versões compatíveis e criará o lock nesta instalação."
  composer83 update --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

# Confirma que as dependências resolvidas realmente carregam no PHP 8.3 de produção.
$PHP_BIN -d display_errors=1 -r 'require "vendor/autoload.php"; echo "[OK] vendor compatível com PHP ".PHP_VERSION."\n";'
SYMFONY_HTTP_VERSION="$($PHP_BIN -r '$p=json_decode(file_get_contents("vendor/composer/installed.json"),true); $pkgs=$p["packages"]??$p; foreach($pkgs as $x){if(($x["name"]??"")==="symfony/http-foundation"){echo $x["version"]??""; break;}}')"
[[ "$SYMFONY_HTTP_VERSION" == v7.4.* || "$SYMFONY_HTTP_VERSION" == 7.4.* ]] || die "Symfony HttpFoundation incompatível com PHP 8.3: ${SYMFONY_HTTP_VERSION:-não identificado}."
ok "Symfony HttpFoundation $SYMFONY_HTTP_VERSION compatível com PHP 8.3."

if [[ -z "$RESTORED_APP_KEY" ]]; then
  php8.3 artisan key:generate --force
else
  ok "APP_KEY da instalação anterior aplicada."
fi

if [[ -f package-lock.json ]]; then
  npm ci --no-audit --no-fund
else
  warn "package-lock.json não está presente no pacote; o npm criará o lock nesta instalação."
  npm install --no-audit --no-fund
fi
npm run build
rm -rf node_modules

# Valida todas as views antes de criar/alterar o banco da instalação limpa.
# Se houver erro de Blade/PHP gerado, a instalação é interrompida aqui.
php8.3 scripts/validate-blade-views.php
ok "Views Blade validadas antes das migrations."

php8.3 artisan migrate --force
php8.3 artisan db:seed --force
# A senha inicial não deve permanecer no .env depois de o hash ter sido criado no banco.
sed -i 's/^SGML_ADMIN_PASSWORD=.*/SGML_ADMIN_PASSWORD=/' .env
php8.3 artisan storage:link || true

# Pool FPM dedicado: web e workers usam a mesma identidade sgml.
cat > /etc/php/8.3/fpm/pool.d/sgml.conf <<EOF_FPM
[sgml]
user = sgml
group = sgml
listen = ${PHP_FPM_SOCKET}
listen.owner = www-data
listen.group = sgml
listen.mode = 0660
pm = dynamic
pm.max_children = 12
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
clear_env = no
catch_workers_output = yes
php_admin_value[upload_max_filesize] = 512M
php_admin_value[post_max_size] = 520M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
php_admin_value[memory_limit] = 256M
EOF_FPM

# Nginx.
sed -e "s/__DOMAIN__/${DOMAIN}/g" -e 's#__PHP_FPM_SOCKET__#/run/php/php8.3-fpm-sgml.sock#g' deploy/nginx-site.conf > /etc/nginx/sites-available/sgml
ln -sfn /etc/nginx/sites-available/sgml /etc/nginx/sites-enabled/sgml
rm -f /etc/nginx/sites-enabled/default

# Fila e scheduler.
install -o root -g root -m 0644 deploy/sgml-queue.service /etc/systemd/system/sgml-queue.service
install -o root -g root -m 0644 deploy/sgml-scheduler.service /etc/systemd/system/sgml-scheduler.service
install -o root -g root -m 0644 deploy/sgml-scheduler.timer /etc/systemd/system/sgml-scheduler.timer

# Backup/restauração pela interface.
# RC87: pai atravessável apenas pelo grupo sgml; conteúdo não listável.
install -d -o root -g sgml -m 0710 /var/backups/sgml
install -d -o sgml -g sgml -m 0700 /var/backups/sgml/database
install -d -o root -g sgml -m 0750 /var/lib/sgml /var/log/sgml
install -d -o sgml -g sgml -m 0700 /var/lib/sgml/restore-requests /var/lib/sgml/restore-status
install -o root -g root -m 0755 deploy/sgml-web-restore-dispatch.sh /usr/local/sbin/sgml-web-restore-dispatch
install -o root -g root -m 0755 deploy/sgml-web-restore-runner.sh /usr/local/sbin/sgml-web-restore-runner
cat > /etc/sudoers.d/sgml-restore <<'EOF_SUDO'
Defaults:sgml !requiretty
sgml ALL=(root) NOPASSWD: /usr/local/sbin/sgml-web-restore-dispatch *
EOF_SUDO
chmod 0440 /etc/sudoers.d/sgml-restore
visudo -cf /etc/sudoers.d/sgml-restore >/dev/null

# Permissões finais.
chown -R root:sgml "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 0750 {} +
find "$APP_DIR" -type f -exec chmod 0640 {} +

# Nginx roda como www-data e precisa atravessar a raiz e ler somente public/.
# Mantemos o código privado em 0750/0640 e abrimos apenas a superfície pública.
normalize_public_permissions

chmod 0750 "$APP_DIR/artisan" "$APP_DIR"/deploy/*.sh "$APP_DIR"/scripts/*.sh 2>/dev/null || true
chown -R sgml:sgml "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 0770 {} +
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0660 {} +
if [[ -d "$APP_DIR/storage/app/public" ]]; then
  find "$APP_DIR/storage/app/public" -type d -exec chmod 0755 {} +
  find "$APP_DIR/storage/app/public" -type f -exec chmod 0644 {} +
fi
chown root:sgml "$APP_DIR/.env" && chmod 0640 "$APP_DIR/.env"

# Não basta o build existir: o worker Nginx (www-data) precisa conseguir ler CSS/JS.
if [[ -f "$APP_DIR/public/build/manifest.json" ]]; then
  STATIC_ASSETS="$($PHP_BIN -r '
    $m=json_decode(file_get_contents("public/build/manifest.json"), true) ?: [];
    $out=[];
    $normalize=function(string $path): string {
      $path=ltrim($path, "/");
      return str_starts_with($path, "build/") ? $path : "build/".$path;
    };
    foreach($m as $entry){
      if(!empty($entry["file"])) $out[]=$normalize($entry["file"]);
      foreach(($entry["css"]??[]) as $css) $out[]=$normalize($css);
    }
    echo implode("\n", array_values(array_unique($out)));
  ' 2>/dev/null || true)"
  [[ -n "$STATIC_ASSETS" ]] || die "Manifest Vite não contém assets CSS/JS."
  while IFS= read -r asset; do
    [[ -n "$asset" ]] || continue
    sudo -u www-data test -r "$APP_DIR/public/$asset" || die "Nginx/www-data não consegue ler /$asset."
  done <<< "$STATIC_ASSETS"
  ok "Assets públicos legíveis pelo usuário do Nginx."
fi

# Reaplica explicitamente permissões do build no fim da preparação.
normalize_public_permissions
grep -Eq "^[[:space:]]*root[[:space:]]+$APP_DIR/public;[[:space:]]*$" /etc/nginx/sites-enabled/sgml \
  || die "Vhost Nginx não aponta para $APP_DIR/public."
systemctl daemon-reload
systemctl restart "$PHP_FPM_SERVICE"
systemctl enable --now sgml-queue.service sgml-scheduler.timer
nginx -t
systemctl reload nginx

# Caches somente depois de permissões, banco e FPM estarem prontos.
sudo -u sgml php8.3 artisan optimize:clear
sudo -u sgml php8.3 artisan config:cache
sudo -u sgml php8.3 artisan route:cache
sudo -u sgml php8.3 artisan view:cache
sudo -u sgml php8.3 scripts/check-backup-runtime.php

HTTPS_ENABLED=0
if [[ "$SKIP_HTTPS" -eq 0 ]]; then
  log "Tentando habilitar HTTPS com Let's Encrypt para $DOMAIN."
  if getent ahosts "$DOMAIN" >/dev/null 2>&1 && certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$SSL_EMAIL" --redirect; then
    HTTPS_ENABLED=1
    sed -i "s#^APP_URL=.*#APP_URL=https://${DOMAIN}#" .env
    sed -i 's/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' .env
    sed -i "s#^GOOGLE_REDIRECT_URI=.*#GOOGLE_REDIRECT_URI=https://${DOMAIN}/integrations/google/callback#" .env
    sudo -u sgml php8.3 artisan config:cache
    systemctl reload nginx "$PHP_FPM_SERVICE"
    ok "HTTPS habilitado."
  else
    warn "O certificado não foi emitido. O SGML ficou em HTTP. Confira DNS e portas 80/443 e execute depois: sudo bash deploy/enable-https-letsencrypt.sh --domain $DOMAIN --email $SSL_EMAIL"
  fi
else
  warn "HTTPS foi ignorado por --skip-https. Não use credenciais reais em HTTP pela Internet."
fi

# Verificações finais.
systemctl is-active --quiet sgml-queue.service || die "sgml-queue.service não iniciou."
systemctl is-active --quiet sgml-scheduler.timer || die "sgml-scheduler.timer não iniciou."
php-fpm8.3 -t
nginx -t
if [[ "$HTTPS_ENABLED" -eq 1 ]]; then
  curl -ksSf --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/up" >/dev/null || warn "Healthcheck HTTPS local não respondeu; consulte TROUBLESHOOTING.md."
else
  curl -sSf "http://127.0.0.1/up" -H "Host: $DOMAIN" >/dev/null || warn "Healthcheck HTTP local não respondeu; consulte TROUBLESHOOTING.md."
fi

SECRETS_FILE="/root/sgml-install-secrets.txt"
{
  echo "SGML - segredos iniciais da instalação"
  echo "Gerado em: $(date -Iseconds)"
  echo "URL: $([[ "$HTTPS_ENABLED" -eq 1 ]] && echo https || echo http)://$DOMAIN"
  echo "Administrador: $ADMIN_EMAIL"
  if [[ "$GENERATED_ADMIN_PASSWORD" -eq 1 ]]; then echo "Senha inicial do Administrador: $ADMIN_PASSWORD"; else echo "Senha inicial do Administrador: informada no comando"; fi
  echo "Banco: $DB_NAME"
  echo "Usuário PostgreSQL: $DB_USER"
  echo "Senha PostgreSQL: $DB_PASSWORD"
  APP_KEY_VALUE="$(grep '^APP_KEY=' .env | cut -d= -f2-)"
  echo "APP_KEY fingerprint: $(printf '%s' "$APP_KEY_VALUE" | sha256sum | cut -c1-16)"
  echo
  echo "PROTEJA ESTE ARQUIVO. Após guardar os dados em local seguro, remova-o com:"
  echo "  sudo shred -u $SECRETS_FILE"
} > "$SECRETS_FILE"
chmod 0600 "$SECRETS_FILE"

cat <<EOF_DONE

============================================================
SGML instalado com sucesso
============================================================
URL: $([[ "$HTTPS_ENABLED" -eq 1 ]] && echo https || echo http)://${DOMAIN}
Administrador: ${ADMIN_EMAIL}
Aplicação: ${APP_DIR}
PHP: $(php8.3 -r 'echo PHP_VERSION;')
PostgreSQL: $(psql --version | sed 's/psql (PostgreSQL) //')
Node.js: $(node --version)

Os segredos iniciais foram gravados em:
  ${SECRETS_FILE}

Próximos passos:
  1. Abra a URL acima e faça login.
  2. Configure SMTP/WhatsApp somente se desejar.
  3. Gere um backup em Administração > Backup e restauração.
  4. Leia INSTALL.md e MIGRATION.md antes de colocar em produção.
============================================================
EOF_DONE
