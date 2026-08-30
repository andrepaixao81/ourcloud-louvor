<?php

$root = dirname(__DIR__);
$checks = [];
$check = function (bool $ok, string $message) use (&$checks): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    $checks[] = $ok;
};

$routes = file_get_contents($root.'/routes/web.php') ?: '';
$controller = file_get_contents($root.'/app/Http/Controllers/CalendarEventController.php') ?: '';
$calendar = file_get_contents($root.'/resources/views/calendar/index.blade.php') ?: '';
$form = file_get_contents($root.'/resources/views/calendar/form.blade.php') ?: '';
$layout = file_get_contents($root.'/resources/views/layouts/app.blade.php') ?: '';
$team = file_get_contents($root.'/app/Services/TeamNotificationService.php') ?: '';
$seeder = file_get_contents($root.'/database/seeders/SystemSeeder.php') ?: '';
$migration = file_get_contents($root.'/database/migrations/2026_08_20_000026_rc79_calendar_agenda_acl.php') ?: '';

$check(str_contains($routes, "calendar.events.edit") && str_contains($routes, "calendar.events.update"), 'Programações da Agenda possuem rotas de edição.');
$check(str_contains($routes, "calendar.events.cancel") && str_contains($routes, "calendar.events.destroy"), 'Programações da Agenda possuem cancelamento e exclusão.');
$check(str_contains($controller, "source_type === 'custom'") && str_contains($controller, "creates_schedule' => false"), 'Evento comum da Agenda permanece independente de Culto/Escala.');
$check(!str_contains($controller, 'Service::query()->create') && !str_contains($controller, 'ScheduleAssignment::query()->create'), 'Controller da Agenda não cria culto nem atribuição de escala.');
$check(str_contains($calendar, 'Agenda do Ministério') && str_contains($calendar, 'Programação / aviso'), 'Agenda diferencia programação informativa de culto.');
$check(str_contains($calendar, "route('calendar.events.edit'"), 'Agenda oferece edição de programação criada diretamente nela.');
$check(str_contains($form, 'Este item não cria culto, escala, ministro nem repertório.'), 'Formulário explica claramente a independência entre Agenda e Escala.');
$check(str_contains($form, 'Digite EXCLUIR') && str_contains($form, 'Excluir programação'), 'Exclusão exige confirmação explícita.');
$check(str_contains($layout, '$canManageServices') && str_contains($layout, 'Agenda do Ministério') && str_contains($layout, '>Cultos</a>'), 'Menu simplifica Agenda/Cultos e reserva Cultos à gestão operacional.');
$check(str_contains($team, 'announceCalendar') && str_contains($team, "hasPermission('calendar.view')"), 'Avisos da Agenda seguem usuários autorizados por RBAC/ACL calendar.view.');
$check(str_contains($seeder, "'calendar_event.delete'"), 'Seeder inclui permissão ACL de exclusão da Agenda.');
$check(str_contains($migration, "calendar_event.delete") && str_contains($migration, "ministry_manager"), 'Migration RC79 instala a nova permissão para Administração/Liderança responsável.');

if (in_array(false, $checks, true)) {
    echo 'Resultado: FALHA'.PHP_EOL;
    exit(1);
}

echo 'Resultado: OK'.PHP_EOL;
