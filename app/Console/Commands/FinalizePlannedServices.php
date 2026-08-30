<?php

namespace App\Console\Commands;

use App\Services\InternalCalendarService;
use App\Services\ServiceHistorySyncService;
use App\Models\Service;
use Illuminate\Console\Command;

class FinalizePlannedServices extends Command
{
    protected $signature = 'sgml:finalize-planned-services {--limit=250}';
    protected $description = 'Converte automaticamente repertórios de cultos encerrados em histórico de louvores ministrados.';

    public function handle(ServiceHistorySyncService $history, InternalCalendarService $calendar): int
    {
        $count = $history->syncDue(max(1, (int) $this->option('limit')));

        Service::query()->withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->get()
            ->each(fn (Service $service) => $calendar->syncService($service));

        $this->info("Cultos sincronizados com o histórico: {$count}");
        return self::SUCCESS;
    }
}
