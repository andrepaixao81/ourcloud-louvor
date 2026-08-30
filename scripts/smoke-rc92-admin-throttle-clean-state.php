<?php

$root = dirname(__DIR__);
$read = fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';
$failures = 0;
$checks = 0;
$check = function (bool $condition, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($condition ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $condition) $failures++;
};

$version = trim($read('VERSION'));
$cleanup = $read('app/Services/OperationalCleanupService.php');
$cleanupController = $read('app/Http/Controllers/AdminOperationalCleanupController.php');
$cleanupView = $read('resources/views/admin/operational-cleanup.blade.php');
$routes = $read('routes/web.php');
$provider = $read('app/Providers/AppServiceProvider.php');
$error429 = $read('resources/views/errors/429.blade.php');
$wa = $read('app/Http/Controllers/WhatsappSettingsController.php');

$check(in_array($version, ['1.0.0-rc92', '1.0.0-rc93', '1.0.0-rc94'], true), 'Versão RC92 identificada.');
$check(str_contains($cleanup, 'bool $regenerateRecurringServices = false'), 'Recriação de recorrências é opcional e desativada por padrão.');
$check(str_contains($cleanup, 'if ($regenerateRecurringServices)') && str_contains($cleanup, 'limpeza deve realmente deixar a camada materializada zerada'), 'Limpeza mantém ocorrências materializadas zeradas quando não solicitado o contrário.');
$check(str_contains($cleanupView, 'regenerate_recurring_services') && str_contains($cleanupView, 'permanecerão zeradas'), 'UI explica estado limpo e oferece recriação opcional.');
$check(str_contains($cleanupController, "boolean('regenerate_recurring_services')") && str_contains($cleanupController, 'permanecerão zeradas'), 'Controller respeita opção de recriação e informa resultado ao Administrador.');
$check(str_contains($provider, "RateLimiter::for('admin-operational-cleanup'") && str_contains($provider, "RateLimiter::for('admin-whatsapp'") && str_contains($provider, "RateLimiter::for('admin-user-removal'"), 'Throttles administrativos são independentes por operação.');
$check(str_contains($routes, 'throttle:admin-whatsapp') && str_contains($routes, 'throttle:admin-operational-cleanup') && str_contains($routes, 'throttle:admin-user-removal'), 'Rotas críticas usam limitadores nomeados, sem compartilhar contador genérico.');
$check(! str_contains($routes, "admin/operational-cleanup', [AdminOperationalCleanupController::class, 'destroy'])->middleware(['administrator', 'throttle:1,30'])"), 'Throttle antigo de 30 minutos foi removido da limpeza.');
$check(str_contains($error429, 'Esta ação foi repetida muitas vezes') && str_contains($error429, 'Nenhum dado foi alterado'), 'Página 429 amigável está presente.');
$check(str_contains($wa, 'function clearQueue') && str_contains($wa, "'status' => 'cancelled'"), 'Limpeza da fila continua cancelando avisos pendentes com segurança.');

printf("Resultado: %s (%d verificações)\n", $failures === 0 ? 'OK' : 'FALHA', $checks);
exit($failures === 0 ? 0 : 1);
