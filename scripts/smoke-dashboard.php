<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? null;
if (!$email) {
    fwrite(STDERR, "Uso: php8.3 scripts/smoke-dashboard.php email@dominio\n");
    exit(2);
}

$user = User::query()->where('email', $email)->first();
if (!$user) {
    fwrite(STDERR, "Usuário não encontrado: {$email}\n");
    exit(3);
}

Auth::login($user);
$summary = app(DashboardService::class)->summary();

$required = [
    'services_month', 'active_ministers', 'published_assignments',
    'pending_confirmations', 'pending_swaps', 'songs',
    'songs_performed_month', 'pending_actions', 'upcoming',
];

foreach ($required as $key) {
    if (!array_key_exists($key, $summary)) {
        fwrite(STDERR, "Chave ausente no dashboard: {$key}\n");
        exit(4);
    }
}

echo "[OK] DashboardService executado para {$email}.\n";
echo json_encode([
    'services_month' => $summary['services_month'],
    'active_ministers' => $summary['active_ministers'],
    'pending_confirmations' => $summary['pending_confirmations'],
    'pending_actions' => $summary['pending_actions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
