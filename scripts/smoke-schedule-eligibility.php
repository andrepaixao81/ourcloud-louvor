<?php

declare(strict_types=1);

use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? null;
$monthRaw = $argv[2] ?? now()->format('Y-m');

if (! $email) {
    fwrite(STDERR, "Uso: php scripts/smoke-schedule-eligibility.php admin@exemplo.com 2026-09\n");
    exit(2);
}

try {
    $month = Carbon::createFromFormat('Y-m', $monthRaw)->startOfMonth();
    $user = User::query()->where('email', $email)->firstOrFail();
    Auth::login($user);

    $availability = app(AvailabilityService::class);
    $assignments = ScheduleAssignment::query()
        ->with(['service.type', 'minister'])
        ->whereHas('service', fn ($q) => $q->whereBetween(
            'starts_at',
            [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]
        ))
        ->get();

    $invalid = [];
    foreach ($assignments as $assignment) {
        if (! $assignment->service || ! $assignment->minister) {
            $invalid[] = sprintf('%s: atribuição incompleta', $assignment->id);
            continue;
        }
        $evaluation = $availability->evaluate($assignment->minister, $assignment->service);
        if (! $evaluation['eligible']) {
            $invalid[] = sprintf(
                '%s | %s | %s | %s',
                $assignment->service->starts_at->format('d/m H:i'),
                $assignment->service->name,
                $assignment->minister->display_name,
                implode('; ', $evaluation['reasons'])
            );
        }
    }

    echo sprintf("[OK] Motor de elegibilidade executado para %s. Atribuições: %d.\n", $month->format('m/Y'), $assignments->count());
    if ($invalid === []) {
        echo "[OK] Nenhuma atribuição atual viola as habilitações/restrições.\n";
    } else {
        echo sprintf("[INFO] %d atribuição(ões) atual(is) precisam de revalidação:\n", count($invalid));
        foreach ($invalid as $line) {
            echo " - {$line}\n";
        }
        echo "[INFO] Após o hotfix, use 'Gerar / revalidar sugestão' para corrigir os rascunhos automaticamente.\n";
    }
    echo "Resultado: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] '.$e->getMessage()."\n");
    exit(1);
}
