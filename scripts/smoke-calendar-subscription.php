<?php

declare(strict_types=1);

use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\CalendarSubscriptionService;
use App\Services\IcsService;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php8.3 scripts/smoke-calendar-subscription.php admin@dominio\n");
    exit(2);
}

$user = User::query()->where('email', $email)->where('active', true)->firstOrFail();
$subscriptions = $app->make(CalendarSubscriptionService::class);
$ics = $app->make(IcsService::class);

$team = $subscriptions->team($user);
$personal = $subscriptions->personal($user);

$published = ScheduleAssignment::query()->withoutGlobalScopes()
    ->where('organization_id', $user->organization_id)
    ->where('status', 'published')
    ->pluck('service_id')
    ->unique();

$teamServiceEntries = $team->filter(fn ($entry) => $entry->source_type === 'service');
$unexpected = $teamServiceEntries
    ->pluck('source_id')
    ->filter(fn ($id) => ! $published->contains($id));

if ($unexpected->isNotEmpty()) {
    fwrite(STDERR, '[ERRO] Feed de equipe contém serviço sem escala publicada: '.$unexpected->implode(', ')."\n");
    exit(1);
}

$cancelledCount = $team->where('status', 'cancelled')->count() + $personal->where('status', 'cancelled')->count();
if ($cancelledCount > 0) {
    fwrite(STDERR, '[ERRO] RC43: o snapshot do feed contém '.$cancelledCount." evento(s) cancelado(s); escalas revogadas devem desaparecer do ICS.\n");
    exit(1);
}

if ($published->isEmpty() && $teamServiceEntries->isNotEmpty()) {
    fwrite(STDERR, "[ERRO] Não há escala publicada, mas a agenda completa retornou eventos.\n");
    exit(1);
}

$teamIcs = $ics->render($team, 'Smoke agenda completa');
$personalIcs = $ics->render($personal, 'Smoke agenda pessoal');
foreach ([$teamIcs, $personalIcs] as $payload) {
    if (! str_contains($payload, 'BEGIN:VCALENDAR')
        || ! str_contains($payload, 'END:VCALENDAR')
        || ! str_contains($payload, 'REFRESH-INTERVAL;VALUE=DURATION:PT1M')) {
        fwrite(STDERR, "[ERRO] Payload ICS inválido/incompleto.\n");
        exit(1);
    }
}

echo '[OK] Agenda do sistema: '.$team->count().' evento(s) (escala publicada + eventos internos públicos).' . "\n";
echo '[OK] Agenda pessoal: '.$personal->count().' evento(s) PUBLICADO(s).' . "\n";
echo "[OK] Snapshot sem nenhum evento cancelado/histórico.\n";
echo "[OK] Nenhum evento ativo sem escala publicada foi exposto.\n";
echo "Resultado: OK\n";
