<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;
$ok = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "[OK] {$message}\n";
    } else {
        echo "[ERRO] {$message}\n";
        $failures++;
    }
};

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$ok(is_array($composer), 'composer.json é JSON válido.');
$ok(($composer['require']['php'] ?? null) === '^8.3', 'Projeto declara PHP ^8.3.');
$ok(($composer['config']['platform']['php'] ?? null) === '8.3.6', 'Composer resolve dependências para PHP 8.3.6.');
foreach (['symfony/console','symfony/error-handler','symfony/finder','symfony/http-foundation','symfony/http-kernel','symfony/mailer','symfony/mime','symfony/process','symfony/routing','symfony/uid','symfony/var-dumper'] as $package) {
    $ok(($composer['require'][$package] ?? null) === '^7.4', "{$package} permanece na linha 7.4.");
}

$update = (string) file_get_contents($root.'/deploy/update.sh');
$install = (string) file_get_contents($root.'/deploy/install.sh');
$ok(str_contains($update, 'composer83(){ "$PHP_BIN" "$COMPOSER_BIN"'), 'Atualizador executa Composer explicitamente com PHP 8.3.');
$ok(str_contains($install, 'composer83(){ "$PHP_BIN" "$COMPOSER_BIN"'), 'Instalador executa Composer explicitamente com PHP 8.3.');
$ok((bool) preg_match('/mktemp -d \/var\/tmp\/sgml-rc[0-9]+-stage\\.XXXXXX/', $update), 'Atualizador prepara release em staging.');
$ok(str_contains($update, 'install_node24'), 'Atualizador instala Node/npm quando necessário.');
$ok(str_contains($update, 'public/build/manifest.json'), 'Atualizador exige build frontend válido antes do deploy.');
$ok(str_contains($update, 'Symfony HttpFoundation'), 'Atualizador verifica Symfony compatível com PHP 8.3.');
$ok(str_contains($update, 'normalize_public_permissions'), 'Atualizador normaliza permissões públicas após o deploy.');
$ok(str_contains($update, 'chmod 0755 "$APP_DIR/public/build"'), 'Atualizador força public/build em 0755.');
$ok(str_contains($update, 'chmod 0644 "$APP_DIR/public/build/manifest.json"'), 'Atualizador força manifest Vite em 0644.');
$ok(str_contains($update, 'validate_nginx_document_root'), 'Atualizador valida o document root ativo do Nginx.');
$ok(str_contains($install, 'normalize_public_permissions'), 'Instalador aplica permissões públicas compatíveis com Nginx.');
$nginx = (string) file_get_contents($root.'/deploy/nginx-site.conf');
$ok(str_contains($nginx, 'root /var/www/sgml/public;'), 'Template Nginx aponta para public/.');
$ok(str_contains($nginx, 'location ^~ /build/'), 'Template Nginx trata assets Vite sem fallback para Laravel.');

$package = json_decode((string) file_get_contents($root.'/package.json'), true);
$ok(is_array($package), 'package.json é JSON válido.');
$ok(($package['engines']['node'] ?? null) === '>=20.19.0', 'Frontend declara runtime Node compatível com Vite 7.');

echo $failures === 0 ? "Resultado: OK\n" : "Resultado: FALHA ({$failures})\n";
exit($failures === 0 ? 0 : 1);
