<?php

$root = dirname(__DIR__);
$checks = [];
$check = function (bool $ok, string $message) use (&$checks): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    $checks[] = $ok;
};
$read = fn (string $path) => file_get_contents($root.'/'.$path) ?: '';

$user = $read('app/Models/User.php');
$update = $read('deploy/update.sh');
$verify = $read('scripts/verify-install.sh');
$actions = $read('app/Http/Controllers/ActionCenterController.php');
$version = trim($read('VERSION'));

$check(in_array($version, ['1.0.0-rc86', '1.0.0-rc87', '1.0.0-rc88', '1.0.0-rc89', '1.0.0-rc90', '1.0.0-rc91', '1.0.0-rc92', '1.0.0-rc93', '1.0.0-rc94'], true), 'Linha RC86+ identificada.');
$check(str_contains($user, 'canViewBirthdays()'), 'Autorização de aniversariantes está centralizada no modelo User.');
$check(str_contains($actions, 'if ($request->user()->canViewBirthdays())'), 'Central de Ações respeita a mesma autorização de aniversários.');
$check(str_contains($update, 'ensure_backup_runtime') && str_contains($update, 'SGML_BACKUP_PATH'), 'Atualizador autocorrige runtime de backup antes da validação.');
$check(str_contains($verify, 'check-backup-runtime.php'), 'Verificação final valida runtime de backup.');

if (in_array(false, $checks, true)) { echo "Resultado: FALHA\n"; exit(1); }
echo "Resultado: OK\n";
