#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="/var/www/sgml"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="/usr/bin/php8.3"
NODE_MAJOR="24"
STAGE_DIR=""
STAMP="$(date +%Y%m%d_%H%M%S)"

usage(){ cat <<'USAGE'
Uso:
  sudo bash deploy/update.sh [--app-dir /var/www/sgml]

Execute a partir da NOVA versão descompactada/clonada do SGML.
O .env, storage, APP_KEY e banco PostgreSQL da instalação existente são preservados.

As dependências PHP e os assets são preparados em staging ANTES
de a aplicação instalada ser modificada. O Composer é executado explicitamente com
PHP 8.3 e o pacote fixa a plataforma em PHP 8.3.6.
USAGE
}

log(){ printf '[INFO] %s\n' "$*"; }
ok(){ printf '[OK] %s\n' "$*"; }
warn(){ printf '[AVISO] %s\n' "$*" >&2; }
die(){ printf '[ERRO] %s\n' "$*" >&2; exit 1; }

normalize_public_permissions(){
  # A superfície pública precisa ser realmente atravessável/lida pelo worker do Nginx.
  # Mantemos o restante do código restrito. Estes chmod explícitos em build/assets
  # reproduzem a correção validada em produção e evitam herança de umask 027.
  chmod 0711 "$APP_DIR"
  chmod 0755 "$APP_DIR/public"
  find "$APP_DIR/public" -type d -print0 | xargs -0 -r chmod 0755
  find "$APP_DIR/public" -type f -print0 | xargs -0 -r chmod 0644
  if [[ -d "$APP_DIR/public/build" ]]; then
    chmod 0755 "$APP_DIR/public/build"
  fi
  if [[ -d "$APP_DIR/public/build/assets" ]]; then
    chmod 0755 "$APP_DIR/public/build/assets"
    find "$APP_DIR/public/build/assets" -type f -print0 | xargs -0 -r chmod 0644
  fi
  [[ ! -f "$APP_DIR/public/build/manifest.json" ]] || chmod 0644 "$APP_DIR/public/build/manifest.json"
}

validate_nginx_document_root(){
  local app_url host expected_root site
  app_url="$(grep -m1 '^APP_URL=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
  host="${app_url#*://}"; host="${host%%/*}"; host="${host%%:*}"
  expected_root="$APP_DIR/public"
  [[ -n "$host" ]] || die "Não foi possível determinar o host por APP_URL."
  site="/etc/nginx/sites-enabled/sgml"
  if [[ -f "$site" ]]; then
    grep -Eq "server_name[[:space:]]+([^;[:space:]]+[[:space:]]+)*${host}([[:space:];]|$)" "$site" \
      || die "Vhost SGML não contém server_name $host: $site"
    grep -Eq "^[[:space:]]*root[[:space:]]+${expected_root//\//\/};[[:space:]]*$" "$site" \
      || die "Nginx não aponta para $expected_root. Revise $site."
    ok "Nginx aponta para $expected_root."
  else
    warn "Vhost padrão $site não encontrado; validando somente nginx -t e assets HTTP."
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir) APP_DIR="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
done

[[ "$EUID" -eq 0 ]] || die "Execute como root."
[[ -x "$PHP_BIN" ]] || die "PHP 8.3 não encontrado em $PHP_BIN."
[[ -f "$APP_DIR/.env" && -f "$APP_DIR/artisan" ]] || die "Instalação atual não encontrada em $APP_DIR."
[[ -f "$SOURCE_DIR/artisan" && -f "$SOURCE_DIR/composer.json" ]] || die "Pacote fonte incompleto."
id sgml >/dev/null 2>&1 || die "Usuário sgml ausente. Use o instalador oficial."

PHP_VERSION="$($PHP_BIN -r 'echo PHP_VERSION;')"
$PHP_BIN -r 'exit(version_compare(PHP_VERSION,"8.3.0","<") || version_compare(PHP_VERSION,"8.4.0",">=") ? 1 : 0);' \
  || die "Esta distribuição está fixada na linha PHP 8.3.x. Detectado: $PHP_VERSION"
ok "Runtime alvo confirmado: PHP $PHP_VERSION"

# Composer deve ser executado pelo mesmo PHP usado em produção. Isso evita que um
# /usr/bin/php mais novo resolva dependências incompatíveis com php8.3-fpm.
if ! command -v composer >/dev/null 2>&1; then
  log "Composer não encontrado; instalando Composer oficial."
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/sgml-composer-setup.php
  ACTUAL_CHECKSUM="$($PHP_BIN -r "echo hash_file('sha384', '/tmp/sgml-composer-setup.php');")"
  [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || die "Checksum do instalador do Composer inválido."
  $PHP_BIN /tmp/sgml-composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f /tmp/sgml-composer-setup.php
fi
COMPOSER_BIN="$(command -v composer)"
composer83(){ "$PHP_BIN" "$COMPOSER_BIN" "$@"; }
composer83 --version >/dev/null
ok "Composer será executado por $PHP_BIN."

install_node24(){
  local arch node_arch node_base node_tarball
  arch="$(dpkg --print-architecture)"
  [[ "$arch" == "arm64" || "$arch" == "amd64" ]] || die "Arquitetura sem binário Node preparado: $arch"
  node_arch="$([[ "$arch" == "arm64" ]] && echo arm64 || echo x64)"
  node_base="https://nodejs.org/dist/latest-v${NODE_MAJOR}.x"
  log "Instalando Node.js ${NODE_MAJOR} LTS e npm antes de alterar a aplicação."
  curl -fsSL "$node_base/SHASUMS256.txt" -o /tmp/sgml-node-shasums.txt
  node_tarball="$(awk -v a="$node_arch" '$2 ~ ("node-v[0-9.]+-linux-" a "\\.tar\\.xz$") {print $2; exit}' /tmp/sgml-node-shasums.txt)"
  [[ -n "$node_tarball" ]] || die "Não foi possível localizar Node.js para $node_arch."
  curl -fsSL "$node_base/$node_tarball" -o "/tmp/$node_tarball"
  (cd /tmp && grep " $node_tarball$" sgml-node-shasums.txt | sha256sum -c -)
  rm -rf /usr/local/lib/nodejs/sgml-node
  install -d -m 0755 /usr/local/lib/nodejs/sgml-node
  tar -xJf "/tmp/$node_tarball" -C /usr/local/lib/nodejs/sgml-node --strip-components=1
  for bin in node npm npx corepack; do ln -sfn "/usr/local/lib/nodejs/sgml-node/bin/$bin" "/usr/local/bin/$bin"; done
  rm -f "/tmp/$node_tarball" /tmp/sgml-node-shasums.txt
}

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  install_node24
fi
NODE_MAJOR_FOUND="$(node -p 'Number(process.versions.node.split(".")[0])' 2>/dev/null || echo 0)"
if (( NODE_MAJOR_FOUND < 20 )); then install_node24; fi
ok "Node.js $(node --version); npm $(npm --version)."

# A plataforma Composer do pacote é obrigatória. Mesmo se Composer for iniciado com
# outro PHP por engano em algum fluxo futuro, a resolução continuará mirando 8.3.6.
PLATFORM_PHP="$($PHP_BIN -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["config"]["platform"]["php"] ?? "";' "$SOURCE_DIR/composer.json")"
[[ "$PLATFORM_PHP" == "8.3.6" ]] || die "composer.json precisa fixar config.platform.php=8.3.6; encontrado: ${PLATFORM_PHP:-ausente}."
ok "Composer platform fixada em PHP $PLATFORM_PHP."
$PHP_BIN "$SOURCE_DIR/scripts/smoke-release-platform.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-repertoire-livewire.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rehearsal-ux.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-song-reference.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-reset-test-service.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-vite-asset-paths.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc75-recurrence-whatsapp.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc76-availability-manual-alerts.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc77-schedule-blade.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc78-special-event-lifecycle.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc79-agenda-custom-events.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc80-account-phone.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc81-birthdays.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc82-minister-ux-performance.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc83-uiux.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc84-minister-song-keys.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc85-friendly-403.php"
$PHP_BIN "$SOURCE_DIR/scripts/smoke-rc86-birthday-privacy-backup-runtime.php"
node --check "$SOURCE_DIR/resources/js/app.js"

# Prepara release isoladamente. Se Composer/npm falharem aqui, /var/www/sgml não é tocado.
STAGE_DIR="$(mktemp -d /var/tmp/sgml-rc86-stage.XXXXXX)"
MAINTENANCE_STARTED=0
cleanup(){ [[ -n "$STAGE_DIR" && -d "$STAGE_DIR" ]] && rm -rf "$STAGE_DIR"; }
recover_interrupted_update(){
  local rc="$1"
  if [[ "$rc" -ne 0 && "$MAINTENANCE_STARTED" -eq 1 ]]; then
    warn "Atualização interrompida após entrar em manutenção; tentando devolver o SGML ao estado operacional."
    normalize_public_permissions 2>/dev/null || true
    systemctl restart php8.3-fpm >/dev/null 2>&1 || true
    systemctl enable sgml-scheduler.timer >/dev/null 2>&1 || true
    systemctl restart sgml-scheduler.timer >/dev/null 2>&1 || true
    systemctl enable sgml-queue.service >/dev/null 2>&1 || true
    systemctl restart sgml-queue.service >/dev/null 2>&1 || true
    nginx -t >/dev/null 2>&1 && systemctl reload nginx >/dev/null 2>&1 || true
    if [[ -f "$APP_DIR/artisan" && -f "$APP_DIR/vendor/autoload.php" ]]; then
      sudo -u sgml "$PHP_BIN" "$APP_DIR/artisan" up >/dev/null 2>&1 || true
    fi
    warn "Rotina de recuperação executada. Corrija a causa e reexecute a atualização."
  fi
}
finalize(){
  local rc="$?"
  trap - EXIT
  recover_interrupted_update "$rc"
  cleanup
  exit "$rc"
}
trap finalize EXIT
log "Preparando release em staging: $STAGE_DIR"
rsync -a --delete \
  --exclude='.env' --exclude='storage' --exclude='vendor' --exclude='node_modules' --exclude='public/storage' \
  "$SOURCE_DIR/" "$STAGE_DIR/"
install -d -m 0770 "$STAGE_DIR/storage/framework/cache" "$STAGE_DIR/storage/framework/sessions" \
  "$STAGE_DIR/storage/framework/views" "$STAGE_DIR/storage/logs" "$STAGE_DIR/bootstrap/cache"

cd "$STAGE_DIR"
export COMPOSER_ALLOW_SUPERUSER=1
if [[ -f composer.lock ]]; then
  log "Instalando dependências PHP a partir de composer.lock."
  composer83 install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader
else
  warn "composer.lock ainda não está versionado; resolvendo uma vez para a plataforma PHP 8.3.6."
  composer83 update --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader
fi

# Não basta o Composer terminar: o autoload da release deve abrir no PHP 8.3 real.
$PHP_BIN -d display_errors=1 -r 'require "vendor/autoload.php"; echo "[OK] vendor compatível com PHP ".PHP_VERSION."\n";'
SYMFONY_HTTP_VERSION="$($PHP_BIN -r '$p=json_decode(file_get_contents("vendor/composer/installed.json"),true); $pkgs=$p["packages"]??$p; foreach($pkgs as $x){if(($x["name"]??"")==="symfony/http-foundation"){echo $x["version"]??""; break;}}')"
[[ "$SYMFONY_HTTP_VERSION" == v7.4.* || "$SYMFONY_HTTP_VERSION" == 7.4.* ]] \
  || die "Symfony HttpFoundation incompatível com a linha PHP 8.3: ${SYMFONY_HTTP_VERSION:-não identificado}."
ok "Symfony HttpFoundation $SYMFONY_HTTP_VERSION compatível com PHP 8.3."

if [[ -f package-lock.json ]]; then
  npm ci --no-audit --no-fund
else
  warn "package-lock.json ainda não está versionado; npm criará o lock nesta atualização."
  npm install --no-audit --no-fund
fi
npm run build
[[ -f public/build/manifest.json ]] || die "Build frontend não gerou public/build/manifest.json."
rm -rf node_modules
ok "Frontend compilado em staging antes da manutenção."

# Valida todas as views Blade ainda no staging. Uma view inválida não pode
# substituir a aplicação ativa nem colocá-la em manutenção.
cp "$APP_DIR/.env" "$STAGE_DIR/.env"
chmod 0600 "$STAGE_DIR/.env"
$PHP_BIN scripts/validate-blade-views.php
rm -f "$STAGE_DIR/.env"
ok "Views Blade validadas em staging antes da manutenção."

# RC86: runtime operacional deve ser autocorrigido pelo atualizador.
# Restaurações/migrações antigas podem deixar o diretório administrativo ausente;
# isso não deve colocar a aplicação em manutenção para depois falhar na validação.
ensure_backup_runtime(){
  local configured path parent
  configured="$(grep -m1 '^SGML_BACKUP_PATH=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
  path="${configured:-/var/backups/sgml/database}"
  [[ "$path" == /* ]] || die "SGML_BACKUP_PATH precisa ser absoluto: $path"
  parent="$(dirname "$path")"

  # RC87: o diretório final podia pertencer ao sgml, mas um pai 0700/root
  # impedia a travessia. No runtime padrão, o pai é root:sgml 0710:
  # sgml atravessa sem poder listar; somente database permanece sgml 0700.
  if [[ "$path" == /var/backups/sgml/* ]]; then
    install -d -o root -g sgml -m 0710 /var/backups/sgml
  fi
  install -d -o sgml -g sgml -m 0700 "$path"
  chown sgml:sgml "$path"
  chmod 0700 "$path"

  sudo -u sgml test -x "$parent" || die "SGML não consegue atravessar o diretório pai do backup: $parent"
  sudo -u sgml test -w "$path" || die "Diretório de backup não ficou gravável por sgml: $path"
  ok "Runtime de backup preparado: $path"
}
ensure_backup_runtime

# Backup só depois de sabermos que a nova release consegue ser construída.
SAFE_DIR="/var/backups/sgml/releases/$STAMP"
install -d -o root -g root -m 0700 "$SAFE_DIR"
cp -a "$APP_DIR/.env" "$SAFE_DIR/.env.before"
if [[ -x "$APP_DIR/deploy/backup.sh" ]]; then
  log "Gerando backup técnico antes da atualização."
  BACKUP_DIR=/var/backups/sgml/pre-update bash "$APP_DIR/deploy/backup.sh"
fi

log "Colocando aplicação em manutenção e pausando fila."
MAINTENANCE_STARTED=1
cd "$APP_DIR"
systemctl stop sgml-queue.service >/dev/null 2>&1 || true
$PHP_BIN artisan down --retry=30 >/dev/null 2>&1 || warn "artisan down não pôde ser executado (esperado se a RC64 deixou vendor incompatível); continuando com reparo."

# Substitui código + vendor + build somente depois do staging aprovado.
rsync -a --delete \
  --exclude='.env' --exclude='storage' --exclude='node_modules' --exclude='public/storage' \
  "$STAGE_DIR/" "$APP_DIR/"
cd "$APP_DIR"

# Atualiza somente a informação de versão, preservando todo o restante do .env.
if grep -q '^APP_VERSION=' .env; then
  sed -i 's/^APP_VERSION=.*/APP_VERSION=1.0.0-rc94/' .env
else
  printf '\nAPP_VERSION=1.0.0-rc94\n' >> .env
fi

# Componentes operacionais da release.
install -o root -g root -m 0644 deploy/sgml-queue.service /etc/systemd/system/sgml-queue.service
install -o root -g root -m 0644 deploy/sgml-scheduler.service /etc/systemd/system/sgml-scheduler.service
install -o root -g root -m 0644 deploy/sgml-scheduler.timer /etc/systemd/system/sgml-scheduler.timer
install -o root -g root -m 0755 deploy/sgml-web-restore-dispatch.sh /usr/local/sbin/sgml-web-restore-dispatch
install -o root -g root -m 0755 deploy/sgml-web-restore-runner.sh /usr/local/sbin/sgml-web-restore-runner
systemctl daemon-reload

chown -R root:sgml "$APP_DIR"

# Código privado permanece restrito. A superfície pública é normalizada separadamente.
find "$APP_DIR" -mindepth 1 -maxdepth 1 -type d ! -name public ! -name storage ! -name bootstrap -exec chmod 0750 {} +
normalize_public_permissions

chown -R sgml:sgml "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 0770 {} +
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0660 {} +
# Arquivos publicados por storage:link também precisam ser legíveis pelo Nginx.
if [[ -d "$APP_DIR/storage/app/public" ]]; then
  find "$APP_DIR/storage/app/public" -type d -exec chmod 0755 {} +
  find "$APP_DIR/storage/app/public" -type f -exec chmod 0644 {} +
fi
chown root:sgml "$APP_DIR/.env" && chmod 0640 "$APP_DIR/.env"
chmod 0750 "$APP_DIR/artisan" "$APP_DIR"/deploy/*.sh "$APP_DIR"/scripts/*.sh 2>/dev/null || true

# Não basta o build existir: o worker Nginx (www-data) precisa conseguir ler os
# arquivos estáticos. Este teste impede uma release de ser marcada como OK com
# CSS/JS retornando 403.
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
  sudo -u www-data test -r "$APP_DIR/public/$asset" || die "Nginx/www-data não consegue ler /$asset após aplicar permissões."
done <<< "$STATIC_ASSETS"
ok "Assets públicos legíveis pelo usuário do Nginx."

# Revalida autoload agora no caminho final e só então executa Artisan.
sudo -u sgml $PHP_BIN -r 'require "vendor/autoload.php"; echo "[OK] Autoload de produção compatível com PHP ".PHP_VERSION."\n";'
sudo -u sgml $PHP_BIN artisan package:discover --ansi
sudo -u sgml $PHP_BIN artisan optimize:clear
sudo -u sgml $PHP_BIN artisan migrate --force
sudo -u sgml $PHP_BIN artisan config:cache
sudo -u sgml $PHP_BIN artisan route:cache
sudo -u sgml $PHP_BIN artisan view:cache
sudo -u sgml $PHP_BIN scripts/validate-blade-views.php
ensure_backup_runtime
sudo -u sgml $PHP_BIN scripts/check-backup-runtime.php
sudo -u sgml $PHP_BIN scripts/smoke-whatsapp-providers.php
sudo -u sgml $PHP_BIN scripts/smoke-repertoire-livewire.php
sudo -u sgml $PHP_BIN scripts/smoke-rehearsal-ux.php
sudo -u sgml $PHP_BIN scripts/smoke-song-reference.php
sudo -u sgml $PHP_BIN scripts/smoke-reset-test-service.php
sudo -u sgml $PHP_BIN scripts/smoke-rc75-recurrence-whatsapp.php
sudo -u sgml $PHP_BIN scripts/smoke-rc76-availability-manual-alerts.php
sudo -u sgml $PHP_BIN scripts/smoke-rc77-schedule-blade.php
sudo -u sgml $PHP_BIN scripts/smoke-rc78-special-event-lifecycle.php
sudo -u sgml $PHP_BIN scripts/smoke-rc79-agenda-custom-events.php
$PHP_BIN scripts/smoke-rc80-account-phone.php
$PHP_BIN scripts/smoke-rc81-birthdays.php
$PHP_BIN scripts/smoke-rc82-minister-ux-performance.php
$PHP_BIN scripts/smoke-rc83-uiux.php
$PHP_BIN scripts/smoke-rc84-minister-song-keys.php
$PHP_BIN scripts/smoke-rc85-friendly-403.php
$PHP_BIN scripts/smoke-rc86-birthday-privacy-backup-runtime.php
$PHP_BIN scripts/smoke-rc87-birthday-member-only-backup-path.php
$PHP_BIN scripts/smoke-rc88-open-source.php
$PHP_BIN scripts/smoke-rc89-operational-cleanup-recurrence.php

# Última normalização depois de Artisan/caches: nenhuma etapa posterior pode deixar
# public/build em 0750/0640.
normalize_public_permissions
validate_nginx_document_root
php-fpm8.3 -t
nginx -t
systemctl enable sgml-queue.service sgml-scheduler.timer >/dev/null
systemctl restart php8.3-fpm
systemctl restart sgml-queue.service
systemctl restart sgml-scheduler.timer
systemctl reload nginx
sudo -u sgml $PHP_BIN artisan up

# Validação HTTP real do primeiro CSS/JS gerado.
APP_URL="$(grep -m1 '^APP_URL=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
[[ -n "$APP_URL" ]] || die "APP_URL ausente no .env."
while IFS= read -r asset; do
  [[ -n "$asset" ]] || continue
  code="$(curl -k -sS -o /dev/null -w '%{http_code}' "${APP_URL%/}/$asset" || true)"
  [[ "$code" == "200" ]] || die "Asset /$asset retornou HTTP ${code:-erro} pelo Nginx."
done <<< "$STATIC_ASSETS"
ok "CSS/JS públicos retornam HTTP 200 pelo Nginx."

systemctl is-active --quiet sgml-queue.service || die "sgml-queue.service não iniciou após a atualização."
systemctl is-active --quiet sgml-scheduler.timer || die "sgml-scheduler.timer não iniciou após a atualização."

# Guarda os locks gerados no servidor para que possam ser enviados ao repositório.
LOCK_EXPORT="$SAFE_DIR/locks-generated"
install -d -o root -g root -m 0700 "$LOCK_EXPORT"
[[ -f composer.lock ]] && cp composer.lock "$LOCK_EXPORT/composer.lock"
[[ -f package-lock.json ]] && cp package-lock.json "$LOCK_EXPORT/package-lock.json"

MAINTENANCE_STARTED=0
trap - EXIT
cleanup
ok "Atualização/reparo concluído."
echo "[INFO] Backup prévio: $SAFE_DIR"
echo "[INFO] Locks gerados: $LOCK_EXPORT"
echo "[INFO] Execute: sudo bash $APP_DIR/scripts/verify-install.sh"
