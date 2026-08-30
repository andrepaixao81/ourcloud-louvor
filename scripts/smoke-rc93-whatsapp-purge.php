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
$routes = $read('routes/web.php');
$controller = $read('app/Http/Controllers/WhatsappSettingsController.php');
$view = $read('resources/views/admin/whatsapp.blade.php');
$job = $read('app/Jobs/SendWhatsappNotificationJob.php');

$check(in_array($version, ['1.0.0-rc93', '1.0.0-rc94'], true), 'Versão RC93 identificada.');
$check(str_contains($routes, "admin/whatsapp/purge-unsent") && str_contains($routes, "admin.whatsapp.purge-unsent"), 'Rota para remover não enviados está presente.');
$check(str_contains($controller, 'function purgeUnsentHistory'), 'Controller implementa remoção direta do histórico não entregue.');
$check(str_contains($controller, "whereIn('status', ['failed', 'cancelled', 'skipped'])") && str_contains($controller, '->delete()'), 'Somente falhas/cancelados/ignorados são removidos.');
$check(str_contains($controller, "whereIn('status', ['queued', 'retrying'])") && str_contains($controller, 'Use primeiro “Limpar fila pendente”'), 'Pendências de queue bloqueiam purge inseguro e orientam cancelamento prévio.');
$check(str_contains($view, 'Remover {{ $discardableUnsent }} falha(s)/cancelado(s)') && str_contains($view, "@method('DELETE')"), 'UI oferece remoção direta sem reenviar.');
$check(str_contains($view, 'Mensagens ENVIADAS serão preservadas'), 'Confirmação deixa explícita a preservação dos enviados.');
$check(str_contains($view, 'Reenviar {{ $failedRecent }} falha(s) recente(s)'), 'Reenvio permanece disponível como alternativa.');
$check(str_contains($job, "['queued', 'retrying']"), 'Worker continua impedindo envio de logs cancelados/removidos.');

printf("Resultado: %s (%d verificações)\n", $failures === 0 ? 'OK' : 'FALHA', $checks);
exit($failures === 0 ? 0 : 1);
