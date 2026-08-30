<?php
$root = dirname(__DIR__);
$files = [
    'deploy/update.sh',
    'deploy/install.sh',
    'scripts/verify-install.sh',
];
$fail = 0;
$ok = function(bool $cond, string $msg) use (&$fail): void {
    if ($cond) echo "[OK] {$msg}\n";
    else { echo "[ERRO] {$msg}\n"; $fail++; }
};
foreach ($files as $file) {
    $text = file_get_contents($root.'/'.$file) ?: '';
    $ok(str_contains($text, 'return str_starts_with($path, "build/") ? $path : "build/".$path;'), "$file normaliza assets Vite para build/.");
}
$update = file_get_contents($root.'/deploy/update.sh') ?: '';
$ok(str_contains($update, 'recover_interrupted_update'), 'Atualizador possui recuperação após falha na janela de manutenção.');
$ok(str_contains($update, 'systemctl restart sgml-queue.service'), 'Recuperação reativa a fila do SGML.');
$ok(str_contains($update, 'artisan" up') || str_contains($update, 'artisan up'), 'Recuperação tenta retirar o Laravel de manutenção.');

// Demonstra a semântica esperada do manifest Vite.
$normalize = static function(string $path): string {
    $path = ltrim($path, '/');
    return str_starts_with($path, 'build/') ? $path : 'build/'.$path;
};
$ok($normalize('assets/app-X.css') === 'build/assets/app-X.css', 'assets/app-X.css vira build/assets/app-X.css.');
$ok($normalize('/assets/app-X.js') === 'build/assets/app-X.js', '/assets/app-X.js vira build/assets/app-X.js.');
$ok($normalize('build/assets/app-X.js') === 'build/assets/app-X.js', 'build/ existente não é duplicado.');

echo $fail === 0 ? "Resultado: OK\n" : "Resultado: FALHA ({$fail})\n";
exit($fail === 0 ? 0 : 1);
