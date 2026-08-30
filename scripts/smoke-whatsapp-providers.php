<?php

$root = dirname(__DIR__);
$checks = [];

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = [$condition, $message];
    echo ($condition ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
};

$setting = file_get_contents($root.'/app/Models/WhatsappSetting.php');
$gateway = file_get_contents($root.'/app/Services/WhatsappGatewayService.php');
$controller = file_get_contents($root.'/app/Http/Controllers/WhatsappSettingsController.php');
$job = file_get_contents($root.'/app/Jobs/SendWhatsappNotificationJob.php');
$view = file_get_contents($root.'/resources/views/admin/whatsapp.blade.php');
$routes = file_get_contents($root.'/routes/web.php');
$migration = file_get_contents($root.'/database/migrations/2026_08_19_000024_rc64_whatsapp_provider_evolution.php');

$assert(str_contains($setting, "PROVIDER_EVOLUTION = 'evolution'"), 'Modelo reconhece Evolution API como provedor.');
$assert(str_contains($setting, "'evolution_api_key' => 'encrypted'"), 'API Key da Evolution é armazenada com cast criptografado.');
$assert(str_contains($gateway, '/message/sendText/'), 'Gateway usa endpoint sendText da Evolution API.');
$assert(str_contains($gateway, "'apikey' =>"), 'Gateway envia cabeçalho apikey para Evolution API.');
$assert(str_contains($gateway, "'number' => \$log->phone") && str_contains($gateway, "'text' => \$this->plainTextMessage"), 'Payload Evolution contém número e texto.');
$assert(str_contains($gateway, 'sendMeta(') && str_contains($gateway, 'sendEvolution('), 'Gateway mantém Meta Cloud API e adiciona Evolution API.');
$assert(str_contains($controller, 'WhatsappSetting::PROVIDER_EVOLUTION') && str_contains($controller, 'WhatsappSetting::PROVIDER_META'), 'Controller valida os dois provedores.');
$assert(str_contains($controller, 'checkConnection'), 'Controller oferece diagnóstico de conexão.');
$assert(str_contains($job, 'WhatsappGatewayService'), 'Job de fila delega envio ao gateway de provedores.');
$assert(str_contains($view, 'Evolution API') && str_contains($view, 'Meta WhatsApp Cloud API'), 'Interface permite selecionar Evolution ou Meta.');
$assert(str_contains($routes, "admin.whatsapp.check"), 'Rota de teste de conexão está registrada.');
$assert(str_contains($migration, 'evolution_base_url') && str_contains($migration, 'evolution_api_key'), 'Migration RC64 adiciona configuração da Evolution API.');

$failed = count(array_filter($checks, static fn (array $check): bool => ! $check[0]));
echo 'Resultado: '.($failed === 0 ? 'OK' : 'FALHA ('.$failed.')').PHP_EOL;
exit($failed === 0 ? 0 : 1);
