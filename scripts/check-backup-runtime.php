<?php

declare(strict_types=1);

use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$d = $app->make(DatabaseBackupService::class)->diagnostics();
$errors = [];
$check = static function (bool $condition, string $ok, string $error) use (&$errors): void {
    if ($condition) { echo "[OK] {$ok}\n"; return; }
    $errors[] = $error; fwrite(STDERR, "[ERRO] {$error}\n");
};

$check(config('database.default') === 'pgsql', 'Banco oficial PostgreSQL identificado.', 'Backup administrativo exige DB_CONNECTION=pgsql.');
$check((bool) $d['pg_dump_available'], 'pg_dump disponível em '.$d['pg_dump'].'.', 'pg_dump ausente em '.$d['pg_dump'].'.');
$check((bool) $d['pg_restore_available'], 'pg_restore disponível em '.$d['pg_restore'].'.', 'pg_restore ausente em '.$d['pg_restore'].'.');
$check((bool) $d['path_exists'], 'Diretório de backups existe.', 'Diretório de backups não existe: '.$d['path']);
$check((bool) $d['path_writable'], 'Diretório de backups é gravável pelo SGML.', 'Diretório de backups não é gravável por SGML: '.$d['path']);
$check((bool) ($d['restore_dispatcher_available'] ?? false), 'Despachante seguro de restauração web disponível.', 'Despachante de restauração web ausente: '.($d['restore_dispatcher'] ?? 'não configurado'));
$check((bool) ($d['restore_request_writable'] ?? false), 'Fila segura de solicitações de restauração é gravável pelo SGML.', 'Diretório de solicitações de restauração não é gravável: '.($d['restore_request_path'] ?? 'não configurado'));
$check((bool) ($d['restore_status_writable'] ?? false), 'Diretório de status de restauração é gravável pelo SGML.', 'Diretório de status de restauração não é gravável: '.($d['restore_status_path'] ?? 'não configurado'));
$check((bool) ($d['restore_parent_traversable'] ?? false), 'Diretório pai do runtime de restauração permite travessia pelo SGML.', 'Diretório pai do runtime de restauração não permite travessia: '.($d['restore_parent_path'] ?? 'não configurado'));
$check($d['app_key_fingerprint'] !== 'ausente', 'APP_KEY presente; fingerprint '.$d['app_key_fingerprint'].'.', 'APP_KEY ausente; restauração portátil ficaria incompleta.');

if ($errors !== []) { echo 'Resultado: FALHA ('.count($errors)." teste(s))\n"; exit(1); }
echo "Resultado: OK\n";
