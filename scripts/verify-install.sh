#!/usr/bin/env bash
set -u
APP_DIR="${APP_DIR:-/var/www/sgml}"
PHP_BIN="${PHP_BIN:-php8.3}"
FAIL=0
VENDOR_OK=1
ok(){ printf '[OK] %s\n' "$1"; }
fail(){ printf '[FALHA] %s\n' "$1"; FAIL=1; }
info(){ printf '[INFO] %s\n' "$1"; }

cd "$APP_DIR" 2>/dev/null || { echo "Diretório $APP_DIR não encontrado."; exit 1; }

# RC87: se a verificação for executada com sudo, autocorrige o runtime padrão
# antes do check PHP. O pai fica não-listável e atravessável apenas pelo grupo sgml.
if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
  BACKUP_PATH="$(grep -m1 '^SGML_BACKUP_PATH=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
  BACKUP_PATH="${BACKUP_PATH:-/var/backups/sgml/database}"
  if [[ "$BACKUP_PATH" == /var/backups/sgml/* ]]; then
    install -d -o root -g sgml -m 0710 /var/backups/sgml
  fi
  install -d -o sgml -g sgml -m 0700 "$BACKUP_PATH"
fi
command -v "$PHP_BIN" >/dev/null && ok "$($PHP_BIN -r 'echo "PHP ".PHP_VERSION;')" || { fail 'PHP 8.3 não encontrado'; VENDOR_OK=0; }
command -v psql >/dev/null && ok "$(psql --version)" || fail 'psql não encontrado'
command -v nginx >/dev/null && ok "$(nginx -v 2>&1)" || fail 'Nginx não encontrado'
[[ -S /run/php/php8.3-fpm-sgml.sock ]] && ok 'Socket FPM dedicado SGML' || fail 'Socket /run/php/php8.3-fpm-sgml.sock ausente'

sudo -u sgml "$PHP_BIN" scripts/preflight.php && ok 'Preflight PHP' || fail 'Preflight PHP'

# Teste único e explícito do vendor evita dezenas de fatals iguais caso o Composer
# tenha resolvido pacotes para uma versão de PHP diferente da produção.
if [[ ! -f vendor/autoload.php ]]; then
  fail 'vendor/autoload.php ausente'
  VENDOR_OK=0
elif sudo -u sgml "$PHP_BIN" -d display_errors=1 -r 'require "vendor/autoload.php"; echo "autoload-ok\n";' >/tmp/sgml-vendor-check.txt 2>&1; then
  ok 'Dependências Composer compatíveis com PHP 8.3'
  SYMFONY_HTTP_VERSION="$(sudo -u sgml "$PHP_BIN" -r '$p=json_decode(file_get_contents("vendor/composer/installed.json"),true); $pkgs=$p["packages"]??$p; foreach($pkgs as $x){if(($x["name"]??"")==="symfony/http-foundation"){echo $x["version"]??""; break;}}' 2>/dev/null || true)"
  if [[ "$SYMFONY_HTTP_VERSION" == v7.4.* || "$SYMFONY_HTTP_VERSION" == 7.4.* ]]; then
    ok "Symfony HttpFoundation $SYMFONY_HTTP_VERSION na linha compatível com PHP 8.3"
  else
    fail "Symfony HttpFoundation incompatível/inesperado: ${SYMFONY_HTTP_VERSION:-não identificado}"
    VENDOR_OK=0
  fi
else
  cat /tmp/sgml-vendor-check.txt
  fail 'Dependências Composer incompatíveis com PHP 8.3'
  VENDOR_OK=0
fi

if [[ "$VENDOR_OK" -eq 1 ]]; then
  sudo -u sgml "$PHP_BIN" artisan migrate:status >/tmp/sgml-migrate-status.txt 2>&1 && ok 'Migrations acessíveis' || { cat /tmp/sgml-migrate-status.txt; fail 'Migrations'; }
  sudo -u sgml "$PHP_BIN" artisan route:list >/tmp/sgml-route-list.txt 2>&1 && ok 'Rotas carregadas' || { cat /tmp/sgml-route-list.txt; fail 'Rotas'; }
  sudo -u sgml "$PHP_BIN" scripts/check-backup-runtime.php && ok 'Backup/restauração' || fail 'Backup/restauração'
  sudo -u sgml "$PHP_BIN" scripts/smoke-whatsapp-providers.php && ok 'Provedores WhatsApp (Evolution/Meta)' || fail 'Provedores WhatsApp'
  sudo -u sgml "$PHP_BIN" scripts/smoke-repertoire-livewire.php && ok 'Editor de repertório/Livewire' || fail 'Editor de repertório/Livewire'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rehearsal-ux.php && ok 'UX de Ensaios e confirmação de louvor' || fail 'UX de Ensaios e confirmação de louvor'
  sudo -u sgml "$PHP_BIN" scripts/smoke-song-reference.php && ok 'Referência YouTube/tom dos louvores' || fail 'Referência YouTube/tom dos louvores'
  sudo -u sgml "$PHP_BIN" scripts/smoke-reset-test-service.php && ok 'Reset seguro de culto de teste' || fail 'Reset seguro de culto de teste'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc75-recurrence-whatsapp.php && ok 'Recorrência especial e notificações RC75' || fail 'Recorrência especial e notificações RC75'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc76-availability-manual-alerts.php && ok 'Disponibilidade, exceção de mesmo dia e alertas RC76' || fail 'Disponibilidade, exceção de mesmo dia e alertas RC76'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc77-schedule-blade.php && ok 'Escalas Blade e pré-validação RC77' || fail 'Escalas Blade e pré-validação RC77'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc78-special-event-lifecycle.php && ok 'Ciclo de vida de Eventos especiais RC78' || fail 'Ciclo de vida de Eventos especiais RC78'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc79-agenda-custom-events.php && ok 'Agenda, ACL e programações RC79' || fail 'Agenda, ACL e programações RC79'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc80-account-phone.php && ok 'Telefone/WhatsApp self-service RC80' || fail 'Telefone/WhatsApp self-service RC80'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc81-birthdays.php && ok 'Aniversariantes e felicitação automática RC81' || fail 'Aniversariantes e felicitação automática RC81'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc82-minister-ux-performance.php && ok 'UX, perfis e desempenho RC82' || fail 'UX, perfis e desempenho RC82'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc83-uiux.php && ok 'UI/UX e navegação RC83' || fail 'UI/UX e navegação RC83'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc84-minister-song-keys.php && ok 'Tons por ministro RC84' || fail 'Tons por ministro RC84'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc85-friendly-403.php && ok '403 amigável e Central de Ações RC85' || fail '403 amigável e Central de Ações RC85'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc86-birthday-privacy-backup-runtime.php && ok 'Privacidade de aniversários e runtime de backup RC86' || fail 'Privacidade de aniversários e runtime de backup RC86'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc87-birthday-member-only-backup-path.php && ok 'Aniversariantes por perfil e travessia de backup RC87' || fail 'Aniversariantes por perfil e travessia de backup RC87'
  sudo -u sgml "$PHP_BIN" scripts/smoke-rc88-open-source.php && ok 'Metadados open source e documentação RC88' || fail 'Metadados open source e documentação RC88'
  if grep -qi 'livewire' /tmp/sgml-route-list.txt; then ok 'Rotas Livewire registradas'; else fail 'Rotas Livewire não encontradas'; fi
else
  info 'Migrations, rotas, backup e smoke WhatsApp não foram executados porque o vendor está incompatível.'
  info 'Repare com o pacote atual executando: sudo bash deploy/update.sh'
fi


# Valida a superfície estática real servida pelo Nginx.
# Existência do build não basta: se public/build estiver 0750/0640 para root:sgml,
# o PHP funciona mas o Nginx (www-data) devolve 403 para CSS/JS.
if [[ -f public/build/manifest.json ]]; then
  ok 'Manifest Vite presente'
  # RC68: além de legibilidade abstrata, valida os modos que funcionam com Nginx
  # em Ubuntu: diretórios públicos 0755 e arquivos 0644.
  for d in public public/build public/build/assets; do
    if [[ -d "$d" ]]; then
      mode="$(stat -c '%a' "$d" 2>/dev/null || true)"
      [[ "$mode" == "755" ]] && ok "Permissão pública correta: $d = 0755" || fail "Permissão de $d é ${mode:-?}; esperado 0755"
    fi
  done
  manifest_mode="$(stat -c '%a' public/build/manifest.json 2>/dev/null || true)"
  [[ "$manifest_mode" == "644" ]] && ok 'Permissão do manifest = 0644' || fail "Permissão do manifest é ${manifest_mode:-?}; esperado 0644"
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
  if [[ -z "$STATIC_ASSETS" ]]; then
    fail 'Manifest Vite não contém assets CSS/JS'
  else
    while IFS= read -r asset; do
      [[ -n "$asset" ]] || continue
      file="$APP_DIR/public/$asset"
      if [[ -r "$file" ]]; then
        ok "Asset presente: /$asset"
      else
        fail "Asset ausente/ilegível: /$asset"
        continue
      fi
      if sudo -u www-data test -r "$file" 2>/dev/null; then
        ok "Nginx/www-data consegue ler: /$asset"
      else
        fail "Nginx/www-data não consegue ler: /$asset"
      fi
    done <<< "$STATIC_ASSETS"
  fi
else
  fail 'public/build/manifest.json ausente'
fi

# O vhost oficial precisa apontar para public/. Um root antigo faz o Nginx
# encaminhar /build/* ao Laravel e o frontend aparece sem CSS.
APP_URL_FOR_ROOT="$(grep -m1 '^APP_URL=' .env | cut -d= -f2- | tr -d '"' || true)"
APP_HOST="${APP_URL_FOR_ROOT#*://}"; APP_HOST="${APP_HOST%%/*}"; APP_HOST="${APP_HOST%%:*}"
if [[ -f /etc/nginx/sites-enabled/sgml ]]; then
  grep -Eq "server_name[[:space:]]+([^;[:space:]]+[[:space:]]+)*${APP_HOST}([[:space:];]|$)" /etc/nginx/sites-enabled/sgml \
    && ok "Vhost contém server_name $APP_HOST" || fail "Vhost SGML não contém server_name $APP_HOST"
  grep -Eq "^[[:space:]]*root[[:space:]]+$APP_DIR/public;[[:space:]]*$" /etc/nginx/sites-enabled/sgml \
    && ok "Nginx root = $APP_DIR/public" || fail "Nginx root não aponta para $APP_DIR/public"
else
  info 'Vhost padrão /etc/nginx/sites-enabled/sgml não existe; root não pôde ser validado diretamente.'
fi

for service in nginx php8.3-fpm postgresql sgml-queue.service; do
  systemctl is-active --quiet "$service" && ok "Serviço ativo: $service" || fail "Serviço inativo: $service"
done
systemctl is-active --quiet sgml-scheduler.timer && ok 'Scheduler ativo' || fail 'Scheduler inativo'
nginx -t >/tmp/sgml-nginx-test.txt 2>&1 && ok 'Nginx válido' || { cat /tmp/sgml-nginx-test.txt; fail 'Configuração Nginx'; }
php-fpm8.3 -t >/tmp/sgml-fpm-test.txt 2>&1 && ok 'PHP-FPM válido' || { cat /tmp/sgml-fpm-test.txt; fail 'Configuração PHP-FPM'; }

APP_URL="$(grep -m1 '^APP_URL=' .env | cut -d= -f2- | tr -d '"' || true)"
if [[ "$VENDOR_OK" -eq 1 && -n "$APP_URL" ]]; then
  curl -kfsS "${APP_URL%/}/up" >/dev/null && ok "Healthcheck: ${APP_URL%/}/up" || fail "Healthcheck: ${APP_URL%/}/up"
  if [[ -n "${STATIC_ASSETS:-}" ]]; then
    while IFS= read -r asset; do
      [[ -n "$asset" ]] || continue
      HTTP_META="$(curl -k -sS -o /dev/null -w '%{http_code}|%{content_type}' "${APP_URL%/}/$asset" || true)"
      HTTP_CODE="${HTTP_META%%|*}"
      CONTENT_TYPE="${HTTP_META#*|}"
      if [[ "$HTTP_CODE" == "200" ]]; then
        ok "Asset HTTP 200: /$asset"
        case "$asset" in
          *.css) [[ "$CONTENT_TYPE" == text/css* ]] && ok "Content-Type CSS correto: $CONTENT_TYPE" || fail "CSS /$asset retornou Content-Type $CONTENT_TYPE" ;;
          *.js) [[ "$CONTENT_TYPE" == *javascript* ]] && ok "Content-Type JS correto: $CONTENT_TYPE" || fail "JS /$asset retornou Content-Type $CONTENT_TYPE" ;;
        esac
      else
        fail "Asset HTTP /$asset retornou ${HTTP_CODE:-erro}"
      fi
    done <<< "$STATIC_ASSETS"
  fi
elif [[ -z "$APP_URL" ]]; then
  fail 'APP_URL ausente no .env'
else
  info 'Healthcheck HTTP ignorado porque o vendor está incompatível.'
fi

[[ "$FAIL" -eq 0 ]] && echo 'VALIDAÇÃO TÉCNICA: APROVADA' || echo 'VALIDAÇÃO TÉCNICA: REPROVADA'
exit "$FAIL"
