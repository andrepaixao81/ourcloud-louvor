<?php

$root = dirname(__DIR__);
$checks = [
    'whatsapp clear route' => ["routes/web.php", "admin.whatsapp.clear-queue"],
    'whatsapp clear method' => ["app/Http/Controllers/WhatsappSettingsController.php", "function clearQueue"],
    'whatsapp direct test' => ["app/Http/Controllers/WhatsappSettingsController.php", "Mensagem de teste enviada imediatamente"],
    'whatsapp cancelled status' => ["resources/views/admin/whatsapp.blade.php", "CANCELADO"],
    'whatsapp cancelled job guard' => ["app/Jobs/SendWhatsappNotificationJob.php", "['queued', 'retrying']"],
    'queue stalled UX' => ["resources/views/admin/whatsapp.blade.php", "A fila parece parada"],
    'user removal service' => ["app/Services/UserAccountRemovalService.php", "class UserAccountRemovalService"],
    'user tombstone' => ["app/Services/UserAccountRemovalService.php", "deleted_at"],
    'cleanup cascades' => ["app/Services/OperationalCleanupService.php", "Excluir o culto é a forma mais segura"],
    'cleanup special event text' => ["app/Services/OperationalCleanupService.php", "evento especial"],
    'cleanup warnings' => ["app/Services/OperationalCleanupService.php", "warnings"],
];

$fail = 0;
foreach ($checks as $label => [$file, $needle]) {
    $content = @file_get_contents($root.'/'.$file);
    $ok = is_string($content) && str_contains($content, $needle);
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $ok) $fail++;
}

echo 'Resultado: '.($fail === 0 ? 'OK' : 'FALHA ('.$fail.')').PHP_EOL;
exit($fail === 0 ? 0 : 1);
