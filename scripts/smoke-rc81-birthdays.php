<?php

$root = dirname(__DIR__);
$checks = [];
$check = function (bool $ok, string $message) use (&$checks): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    $checks[] = $ok;
};

$routes = file_get_contents($root.'/routes/web.php') ?: '';
$account = file_get_contents($root.'/app/Http/Controllers/AccountController.php') ?: '';
$accountView = file_get_contents($root.'/resources/views/account/show.blade.php') ?: '';
$person = file_get_contents($root.'/app/Models/Person.php') ?: '';
$user = file_get_contents($root.'/app/Models/User.php') ?: '';
$birthdayController = file_get_contents($root.'/app/Http/Controllers/BirthdayController.php') ?: '';
$birthdayView = file_get_contents($root.'/resources/views/birthdays/index.blade.php') ?: '';
$birthdaySettings = file_get_contents($root.'/app/Services/BirthdaySettingsService.php') ?: '';
$command = file_get_contents($root.'/app/Console/Commands/SendBirthdayGreetings.php') ?: '';
$console = file_get_contents($root.'/routes/console.php') ?: '';
$layout = file_get_contents($root.'/resources/views/layouts/app.blade.php') ?: '';
$actions = file_get_contents($root.'/resources/views/actions/index.blade.php') ?: '';
$seeder = file_get_contents($root.'/database/seeders/SystemSeeder.php') ?: '';
$migration = file_get_contents($root.'/database/migrations/2026_08_22_000028_rc81_birthdays.php') ?: '';

$check(str_contains($migration, "birth_date") && str_contains($migration, "birthdays.view") && str_contains($migration, "birthdays.manage"), 'Migration RC81 adiciona data de nascimento e permissões de aniversários.');
$check(str_contains($person, "'birth_date' => 'date'"), 'Pessoa converte data de nascimento para date.');
$check(str_contains($routes, "account.birth-date.update") && str_contains($account, 'function updateBirthDate'), 'Minha conta possui autosserviço de data de nascimento.');
$check(str_contains($accountView, 'Data de nascimento') && str_contains($accountView, 'apenas dia e mês') && str_contains($accountView, 'ano e a idade não são exibidos'), 'Minha conta explica a privacidade da data de nascimento.');
$check(str_contains($user, 'function canViewBirthdays') && str_contains($user, 'function isPastor') && str_contains($user, 'isMemberWithoutMinistryAccess'), 'Autorização de aniversários possui regra explícita por perfil.');
$check(str_contains($birthdayController, 'whereMonth') && str_contains($birthdayView, 'Aniversariantes do mês') && str_contains($birthdayView, "\$person->birth_date->format('d/m')") && !str_contains($birthdayView, "\$person->birth_date->format('Y')"), 'Seção mensal lista aniversários sem exibir ano/idade.');
$check(str_contains($birthdaySettings, '{primeiro_nome}') && str_contains($birthdaySettings, '{igreja}'), 'Mensagem de aniversário possui variáveis personalizáveis.');
$check(str_contains($birthdayController, 'canManageBirthdaySettings') && str_contains($birthdayView, 'Mensagem automática de aniversário'), 'Administrador/Responsável podem personalizar felicitação.');
$check(str_contains($command, "birthday:") && str_contains($command, "whereIn('status', ['queued', 'sent', 'failed'])"), 'Envio automático evita duplicar felicitação no mesmo dia.');
$check(str_contains($command, 'SendWhatsappNotificationJob::dispatch') && str_contains($command, 'normalizePhone'), 'Felicitação usa fila e telefone normalizado da integração WhatsApp.');
$check(str_contains($console, "sgml:birthday-greetings") && str_contains($console, 'everyFifteenMinutes'), 'Scheduler processa aniversários periodicamente respeitando horário configurado.');
$check(str_contains($layout, '>Aniversariantes</a>') && str_contains($layout, '$canSeeBirthdays'), 'Menu Aniversariantes aparece somente a usuários autorizados.');
$check(str_contains($actions, 'Próximos aniversários') && str_contains($actions, "route('birthdays.index')"), 'Central de Ações mostra próximos aniversários para quem possui acesso.');
$check(str_contains($seeder, "'minister' => ['Ministro'") && str_contains($seeder, "'participant' => ['Participante do Louvor'") && str_contains($seeder, "'member' => ['Membro', []]") && substr_count($seeder, "'birthdays.view'") >= 4, 'Seeder mantém aniversários disponível aos perfis do louvor e bloqueia Membro comum.');

if (in_array(false, $checks, true)) {
    echo 'Resultado: FALHA'.PHP_EOL;
    exit(1);
}

echo 'Resultado: OK'.PHP_EOL;
