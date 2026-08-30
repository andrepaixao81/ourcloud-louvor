# Solução de problemas

## Comando de diagnóstico principal

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

## Ver log do Laravel

```bash
sudo tail -n 200 /var/www/sgml/storage/logs/laravel.log
```

## Ver fila

```bash
sudo systemctl status sgml-queue.service --no-pager -l
sudo journalctl -u sgml-queue.service -n 100 --no-pager
```

Reiniciar:

```bash
sudo systemctl restart sgml-queue.service
```

## Ver scheduler

```bash
sudo systemctl status sgml-scheduler.timer --no-pager
sudo systemctl list-timers sgml-scheduler.timer --all
```

## Nginx não inicia

```bash
sudo nginx -t
sudo systemctl status nginx --no-pager -l
```

O vhost do SGML fica em:

```text
/etc/nginx/sites-available/sgml
```

## PHP-FPM

```bash
sudo php-fpm8.3 -t
sudo systemctl status php8.3-fpm --no-pager -l
ls -l /run/php/php8.3-fpm-sgml.sock
```

## Banco PostgreSQL

```bash
sudo systemctl status postgresql --no-pager
sudo -u postgres psql -c 'select version();'
```

Nunca libere a porta `5432` para toda a Internet para resolver problema de conexão local.

## Erro 500

Primeiro:

```bash
cd /var/www/sgml
sudo -u sgml php8.3 artisan optimize:clear
sudo tail -n 200 storage/logs/laravel.log
```

Depois teste Blade:

```bash
sudo -u sgml php8.3 scripts/validate-blade-views.php
```

## Página abre em HTTP mas não em HTTPS

Confira DNS:

```bash
getent ahosts louvor.minhaigreja.org.br
```

Confira Nginx:

```bash
sudo nginx -t
```

Depois execute:

```bash
cd /var/www/sgml
sudo bash deploy/enable-https-letsencrypt.sh \
  --domain louvor.minhaigreja.org.br \
  --email admin@minhaigreja.org.br
```

Na Oracle Cloud, confirme portas 80 e 443 na Security List/NSG.

## Backup não gera

```bash
cd /var/www/sgml
sudo -u sgml php8.3 scripts/check-backup-runtime.php
```

Confira:

```bash
ls -ld /var/backups/sgml/database
ls -ld /var/lib/sgml /var/lib/sgml/restore-requests /var/lib/sgml/restore-status
which pg_dump
which pg_restore
```

## Upload de backup não abre ou é rejeitado

Confira o limite do Nginx:

```bash
sudo grep -n 'client_max_body_size' /etc/nginx/sites-available/sgml
```

Deve existir apenas uma linha no bloco principal:

```text
client_max_body_size 512m;
```

Confira o pool PHP:

```bash
sudo grep -E 'upload_max_filesize|post_max_size' /etc/php/8.3/fpm/pool.d/sgml.conf
```

## Permissões

A aplicação web e os workers usam o usuário `sgml`.

```bash
id sgml
ls -ld /var/www/sgml/storage /var/www/sgml/bootstrap/cache
```

Para corrigir diretórios graváveis:

```bash
sudo chown -R sgml:sgml /var/www/sgml/storage /var/www/sgml/bootstrap/cache
sudo find /var/www/sgml/storage /var/www/sgml/bootstrap/cache -type d -exec chmod 770 {} \;
sudo find /var/www/sgml/storage /var/www/sgml/bootstrap/cache -type f -exec chmod 660 {} \;
```

## Limpar e reconstruir caches

```bash
cd /var/www/sgml
sudo -u sgml php8.3 artisan optimize:clear
sudo -u sgml php8.3 artisan config:cache
sudo -u sgml php8.3 artisan route:cache
sudo -u sgml php8.3 artisan view:cache
```


## Composer exige PHP >= 8.4.1 em uma VM PHP 8.3

Sintoma:

```text
Your Composer dependencies require a PHP version ">= 8.4.1". You are running 8.3.x.
```

Isso indica que dependências foram resolvidas usando um PHP diferente do runtime `php8.3-fpm`. Não use `--ignore-platform-reqs`. O pacote atual fixa a plataforma Composer em PHP 8.3.6 e resolve Symfony 7.4.

Para reparar uma instalação afetada:

```bash
cd /home/ubuntu/ourcloud-louvor
sudo bash deploy/update.sh
```

Depois:

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

## `npm: command not found` durante atualização

O pacote atual instala Node.js/npm **antes** de tocar na aplicação e compila o frontend em staging. Não é necessário instalar `npm` manualmente. Execute novamente a atualização usando o pacote atual.

## Fila `sgml-queue.service` ficou inativa após uma atualização interrompida

Depois de reparar com versão atual ou posterior, o atualizador reinicia a fila automaticamente. Para verificar:

```bash
systemctl status sgml-queue.service --no-pager
```

Se a release já estiver íntegra e você precisar apenas iniciar o worker:

```bash
sudo systemctl restart sgml-queue.service
```

## Página abre sem CSS/JS ou com logomarca gigante

Se o HTML carregar, mas a interface aparecer como texto puro, confirme primeiro os assets do Vite:

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

O pacote atual valida não apenas a existência de `public/build/manifest.json`, mas também se o usuário `www-data` do Nginx consegue ler cada CSS/JS e se os arquivos retornam HTTP 200.

Em uma instalação antiga deixada com permissões restritivas, o reparo manual seguro é:

```bash
sudo chmod 0711 /var/www/sgml
sudo find /var/www/sgml/public -type d -exec chmod 0755 {} +
sudo find /var/www/sgml/public -type f -exec chmod 0644 {} +
sudo nginx -t
sudo systemctl reload nginx
```

Depois faça um recarregamento forçado no navegador (`Ctrl+F5`).


## Manifest Vite e prefixo /build

O Vite grava no `public/build/manifest.json` caminhos internos como `assets/app-XXXX.css`. A URL pública correta é `/build/assets/app-XXXX.css`. Desde a RC71, instalador, atualizador e verificador normalizam automaticamente esse prefixo. Não teste `/assets/...` diretamente.


## RC86 - diretório administrativo de backup ausente

A RC86 autocorrige o diretório configurado em `SGML_BACKUP_PATH` durante `deploy/update.sh`.
Para reparo manual emergencial:

```bash
sudo install -d -o sgml -g sgml -m 0700 /var/backups/sgml/database
```

Depois reexecute a atualização.

## RC87 - backup existe, mas SGML não consegue gravar

Se uma versão anterior criou `/var/backups/sgml/database` corretamente, mas o teste ainda informa que o diretório não é gravável, verifique também o pai `/var/backups/sgml`. Um pai `0700/root` impede a travessia do usuário `sgml`.

A RC87 corrige automaticamente para:

- `/var/backups/sgml`: `root:sgml` e `0710`;
- `/var/backups/sgml/database`: `sgml:sgml` e `0700`.

A permissão `0710` no pai permite ao grupo `sgml` atravessar o caminho, mas não listar seu conteúdo.
