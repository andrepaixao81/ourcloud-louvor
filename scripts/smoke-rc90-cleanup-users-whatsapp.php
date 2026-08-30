<?php

$root = dirname(__DIR__);
$read = fn (string $path): string => file_get_contents($root.'/'.$path) ?: '';
$checks = 0; $failures = 0;
$check = function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    echo ($condition ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    if (! $condition) $failures++;
};

$cleanup = $read('app/Services/OperationalCleanupService.php');
$cleanupView = $read('resources/views/admin/operational-cleanup.blade.php');
$cleanupController = $read('app/Http/Controllers/AdminOperationalCleanupController.php');
$users = $read('app/Http/Controllers/AdminUserController.php');
$userRemoval = $read('app/Services/UserAccountRemovalService.php');
$usersView = $read('resources/views/admin/users.blade.php');
$routes = $read('routes/web.php');
$team = $read('app/Services/TeamNotificationService.php');
$job = $read('app/Jobs/SendWhatsappNotificationJob.php');
$waController = $read('app/Http/Controllers/WhatsappSettingsController.php');
$waView = $read('resources/views/admin/whatsapp.blade.php');
$schedule = $read('app/Http/Controllers/ScheduleController.php');

$check(str_contains($cleanup, 'removeSpecialEvents') && str_contains($cleanup, "DB::table('special_events')") && str_contains($cleanup, 'Responses possuem cascade'), 'Limpeza RC90+ pode remover Eventos Especiais e respostas por cascade.');
$check(str_contains($cleanup, "special_event.%") && str_contains($cleanup, 'specialEventActionItems'), 'Avisos/WhatsApp de Eventos Especiais entram na limpeza.');
$check(str_contains($cleanupView, 'remove_special_events') && str_contains($cleanupView, 'Evento(s) Especial(is) de teste'), 'UI oferece remoção explícita dos Eventos Especiais de teste.');
$check(str_contains($cleanupController, "boolean('remove_special_events')"), 'Controller encaminha a opção de Eventos Especiais.');
$check(str_contains($users, "in:EXCLUIR CONTA") && str_contains($userRemoval, 'user.account.removed') && str_contains($userRemoval, "'email' => 'removed-'"), 'Remoção segura/anônima de conta exige confirmação forte e preserva histórico.');
$check(str_contains($users, 'última conta administrativa ativa') && str_contains($users, 'Você não pode remover a própria conta'), 'Proteções impedem autoexclusão e remoção do último Administrador.');
$check(str_contains($usersView, "admin.users.destroy") && str_contains($usersView, 'EXCLUIR CONTA'), 'Administração expõe opção amigável para remover usuário.');
$check(str_contains($routes, "name('admin.users.destroy')") && str_contains($routes, "name('admin.whatsapp.retry-failed')"), 'Rotas de exclusão de usuário e reenvio WhatsApp estão protegidas.');
$check(str_contains($team, 'batchSpacingSeconds') && str_contains($team, '->delay('), 'Lotes de WhatsApp são espaçados na fila.');
$check(str_contains($job, 'return [10, 30, 90]') && str_contains($job, "'retrying'"), 'Job WhatsApp usa backoff e estado de nova tentativa.');
$check(str_contains($waController, 'retryFailed') && str_contains($waController, "whereIn('status', ['failed', 'retrying'])"), 'Administrador pode reenfileirar falhas recentes.');
$check(str_contains($waView, 'API Key preservada') && str_contains($waView, 'Connection Closed') && str_contains($waView, 'Reenviar'), 'Tela WhatsApp explica credencial preservada e recuperação de Connection Closed.');
$check(str_contains($schedule, '$announcement = $teamNotifications->announce') && str_contains($schedule, "whatsapp_queued"), 'Publicação da escala informa quantos avisos WhatsApp foram efetivamente enfileirados.');

printf("Resultado: %s (%d verificações)\n", $failures === 0 ? 'OK' : 'FALHA', $checks);
exit($failures === 0 ? 0 : 1);
