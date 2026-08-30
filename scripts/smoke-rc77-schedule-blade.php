<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root.'/resources/views/schedules/index.blade.php');
$update = file_get_contents($root.'/deploy/update.sh');
$fail = 0;
$ok = static function (bool $condition, string $message) use (&$fail): void {
    if ($condition) {
        echo "[OK] {$message}\n";
    } else {
        echo "[ERRO] {$message}\n";
        $fail++;
    }
};

$ok($view !== false, 'View de Escalas disponível.');
$ok($view !== false && str_contains($view, "⚠ já escalado neste dia"), 'Advertência de dois cultos no mesmo dia preservada.');
$ok($view !== false && !str_contains($view, '@php($candidatePerson'), 'Loop manual não depende de atribuição Blade inline intermediária.');
$ok($view !== false && !str_contains($view, '@php($minister ='), 'Loop de alteração de ministro usa expressão Blade conservadora.');
$ok($view !== false && !str_contains($view, '@php($extraPerson'), 'Loop de ministros adicionais usa expressão Blade conservadora.');
$ok($update !== false && str_contains($update, 'Views Blade validadas em staging antes da manutenção.'), 'Atualizador valida Blade antes de entrar em manutenção.');

if ($fail > 0) {
    echo "Resultado: FALHA ({$fail} teste(s))\n";
    exit(1);
}

echo "Resultado: OK\n";
