<?php

declare(strict_types=1);

$errors = [];
$warnings = [];

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $errors[] = 'PHP 8.3.0 ou superior é obrigatório. Detectado: '.PHP_VERSION;
}

$required = ['ctype','curl','dom','fileinfo','filter','hash','mbstring','openssl','pcre','pdo','pdo_pgsql','session','tokenizer','xml'];
foreach ($required as $extension) {
    if (!extension_loaded($extension)) $errors[] = "Extensão PHP ausente: {$extension}";
}

foreach (['storage/framework/cache','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache'] as $directory) {
    $path = dirname(__DIR__).'/'.$directory;
    if (!is_dir($path) && !@mkdir($path, 0775, true)) $errors[] = "Não foi possível criar {$directory}";
    if (is_dir($path) && !is_writable($path)) $warnings[] = "Diretório sem escrita para o usuário atual: {$directory}";
}

if (!file_exists(dirname(__DIR__).'/.env')) $warnings[] = '.env ainda não existe.';
if (!file_exists(dirname(__DIR__).'/vendor/autoload.php')) $warnings[] = 'Dependências Composer ainda não foram instaladas.';

printf("SGML preflight\nPHP: %s (%s)\nArquitetura: %s\n", PHP_VERSION, PHP_SAPI, php_uname('m'));
foreach ($warnings as $warning) echo "[AVISO] {$warning}\n";
foreach ($errors as $error) echo "[ERRO] {$error}\n";

if ($errors) exit(1);
echo "[OK] Pré-requisitos PHP essenciais aprovados.\n";
