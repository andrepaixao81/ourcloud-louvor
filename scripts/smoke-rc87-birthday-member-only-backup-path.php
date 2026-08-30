<?php

$root = dirname(__DIR__);
$checks = [];
$check = function (bool $ok, string $message) use (&$checks): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    $checks[] = $ok;
};
$read = fn (string $path) => file_get_contents($root.'/'.$path) ?: '';

$user = $read('app/Models/User.php');
$seeder = $read('database/seeders/SystemSeeder.php');
$migration = $read('database/migrations/2026_08_29_000031_rc87_birthday_access.php');
$update = $read('deploy/update.sh');
$install = $read('deploy/install.sh');
$verify = $read('scripts/verify-install.sh');
$actions = $read('app/Http/Controllers/ActionCenterController.php');

$check(in_array(trim($read('VERSION')), ['1.0.0-rc87', '1.0.0-rc88', '1.0.0-rc89', '1.0.0-rc90', '1.0.0-rc91', '1.0.0-rc92', '1.0.0-rc93', '1.0.0-rc94'], true), 'Linha RC87+ identificada.');
$check(str_contains($user, 'somente o Membro comum fica sem acesso') && str_contains($user, 'return ! $this->isMemberWithoutMinistryAccess();'), 'Somente Membro comum é bloqueado em aniversariantes.');
$check(str_contains($migration, "where('slug', 'member')") && str_contains($migration, "['administrator', 'ministry_manager', 'minister', 'participant', 'leadership']"), 'Migration RC87 retira Membro e concede visualização aos perfis autorizados.');
$check(str_contains($seeder, "'special_events.respond','birthdays.view'") && str_contains($seeder, "'participant' => ['Participante do Louvor'") && substr_count($seeder, "'birthdays.view'") >= 4, 'Seeder concede birthdays.view a Ministro/Participante e mantém Membro sem permissão.');
$check(str_contains($actions, 'if ($request->user()->canViewBirthdays())'), 'Central de Ações usa a autorização central de aniversários.');
$check(str_contains($update, 'install -d -o root -g sgml -m 0710 /var/backups/sgml') && str_contains($update, 'test -x "$parent"'), 'Atualizador corrige travessia segura do diretório pai de backups.');
$check(str_contains($install, 'install -d -o root -g sgml -m 0710 /var/backups/sgml'), 'Instalação limpa prepara pai seguro de backups.');
$check(str_contains($verify, 'install -d -o root -g sgml -m 0710 /var/backups/sgml'), 'Verificador pode autocorrigir runtime de backup quando executado com sudo.');

if (in_array(false, $checks, true)) { echo "Resultado: FALHA\n"; exit(1); }
echo "Resultado: OK\n";
