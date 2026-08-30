<?php

$root = dirname(__DIR__);
$checks = [];
$check = function (bool $ok, string $message) use (&$checks): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$message.PHP_EOL;
    $checks[] = $ok;
};

$routes = file_get_contents($root.'/routes/web.php') ?: '';
$controller = file_get_contents($root.'/app/Http/Controllers/AccountController.php') ?: '';
$view = file_get_contents($root.'/resources/views/account/show.blade.php') ?: '';
$js = file_get_contents($root.'/resources/js/app.js') ?: '';
$team = file_get_contents($root.'/app/Services/TeamNotificationService.php') ?: '';

$check(str_contains($routes, "account.phone.update") && str_contains($routes, "'/account/phone'"), 'Minha conta possui rota própria para atualizar telefone.');
$check(str_contains($controller, 'function updatePhone') && str_contains($controller, "DDD + número do telefone"), 'Controller valida DDD + número no autosserviço.');
$check(str_contains($controller, "str_starts_with(\$digits, '55')") && str_contains($controller, "\$person->update(['phone' => \$digits])"), 'Telefone é normalizado e persistido no cadastro da pessoa vinculada.');
$check(str_contains($view, 'Telefone / WhatsApp') && str_contains($view, 'data-br-phone') && str_contains($view, "route('account.phone.update')"), 'Minha conta permite cadastrar, alterar ou remover telefone.');
$check(str_contains($view, 'DDD + número do telefone') && str_contains($view, '(21) 99999-9999'), 'Interface explica claramente o formato DDD + telefone.');
$check(str_contains($js, 'formatBrazilianPhone') && str_contains($js, '[data-br-phone]'), 'Frontend aplica máscara brasileira sem substituir a validação do servidor.');
$check(str_contains($team, '$user->person?->phone') && str_contains($team, "'55'.\$digits"), 'Avisos WhatsApp continuam consumindo o telefone da pessoa e acrescentando DDI 55 no envio.');

if (in_array(false, $checks, true)) {
    echo 'Resultado: FALHA'.PHP_EOL;
    exit(1);
}

echo 'Resultado: OK'.PHP_EOL;
