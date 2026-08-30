<?php

namespace App\Console\Commands;

use App\Services\RecurringServiceGeneratorService;
use Illuminate\Console\Command;

class ExpandRecurringServices extends Command
{
    protected $signature = 'sgml:expand-recurring {--days=90}';
    protected $description = 'Materializa ocorrências futuras de cultos recorrentes';
    public function handle(RecurringServiceGeneratorService $service): int
    {
        $count=$service->expandDays((int)$this->option('days'));
        $this->info("Ocorrências processadas: {$count}");
        return self::SUCCESS;
    }
}
