<?php

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$check = function (bool $ok, string $message) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? '[OK] ' : '[FALHA] ').$message.PHP_EOL;
    if (! $ok) $failed++;
};

$user = file_get_contents($root.'/app/Models/User.php') ?: '';
$layout = file_get_contents($root.'/resources/views/layouts/app.blade.php') ?: '';
$agenda = file_get_contents($root.'/resources/views/calendar/my-agenda.blade.php') ?: '';
$account = file_get_contents($root.'/app/Http/Controllers/AccountController.php') ?: '';
$admin = file_get_contents($root.'/app/Http/Controllers/AdminUserController.php') ?: '';
$profiles = file_get_contents($root.'/app/Services/UserPersonProfileService.php') ?: '';
$team = file_get_contents($root.'/app/Services/TeamNotificationService.php') ?: '';
$calendar = file_get_contents($root.'/app/Services/InternalCalendarService.php') ?: '';
$calendarController = file_get_contents($root.'/app/Http/Controllers/CalendarController.php') ?: '';
$routes = file_get_contents($root.'/routes/web.php') ?: '';
$migration = file_get_contents($root.'/database/migrations/2026_08_28_000029_rc82_user_profile_and_agenda_performance.php') ?: '';
$programmed = file_get_contents($root.'/resources/views/calendar/programmed-songs.blade.php') ?: '';

$check(str_contains($user, 'usesMinisterOnlyNavigation') && str_contains($layout, '$ministerOnlyNavigation'), 'Ministro possui navegação principal simplificada.');
$check(str_contains($agenda, 'Disponibilidade') && str_contains($agenda, 'Ensaios') && str_contains($agenda, 'Louvores programados') && str_contains($agenda, 'Minha conta'), 'Minha Agenda centraliza os atalhos operacionais do Ministro.');
$check(str_contains($profiles, 'whereNull(\'user_id\')') && str_contains($profiles, 'LOWER(email) = LOWER(?)') && str_contains($account, '$profiles->ensure'), 'Minha conta autorrepara vínculo usuário-pessoa.');
$check(str_contains($admin, "'phone' =>") && str_contains($admin, "'birth_date' =>") && str_contains($admin, "'email' =>") && str_contains($admin, '$profiles->ensure'), 'Administração persiste e-mail, telefone e nascimento no cadastro vinculado.');
$check(str_contains($migration, 'users') && str_contains($migration, "LOWER(email) = LOWER(?)") && str_contains($migration, 'idx_services_org_status_start_rc82'), 'Migration RC82 faz backfill e cria índices de acesso frequente.');
$check(str_contains($team, 'hasActiveMinistryRole') && str_contains($team, "hasPermission('calendar.view')"), 'Avisos de Agenda incluem integrantes ativos do louvor mesmo com papel Membro.');
$check(str_contains($calendar, 'Cache::lock') && str_contains($calendar, 'addSeconds(20)') && str_contains($calendar, "'assignment.minister'"), 'Reconciliação da Agenda usa lock, janela de frescor e eager loading.');
$check(str_contains($calendarController, 'function swapOptions') && ! str_contains(substr($calendarController, strpos($calendarController, 'function myAgenda'), strpos($calendarController, 'function swapOptions') - strpos($calendarController, 'function myAgenda')), 'evaluateForSwap'), 'Candidatos de troca são calculados apenas ao abrir uma solicitação específica.');
$check(str_contains($routes, "'/louvores-programados'") && str_contains($calendarController, 'function programmedSongs') && str_contains($programmed, 'próximos 60 dias'), 'Louvores programados possuem consulta autenticada e leve.');

if ($failed) {
    fwrite(STDERR, "Resultado: FALHA ($failed de $checks)\n");
    exit(1);
}
echo "Resultado: OK\n";
