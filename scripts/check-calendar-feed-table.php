<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$requiredColumns = [
    'id', 'organization_id', 'user_id', 'scope', 'service_id',
    'calendar_entry_id', 'event_starts_at', 'status', 'content_hash',
    'sequence', 'last_published_at', 'cancelled_at', 'created_at', 'updated_at',
];

if (! Schema::hasTable('calendar_feed_states')) {
    fwrite(STDERR, "[ERRO] calendar_feed_states não existe na conexão ativa.\n");
    fwrite(STDERR, '[INFO] Driver: '.DB::connection()->getDriverName()."\n");
    fwrite(STDERR, '[INFO] Banco: '.DB::connection()->getDatabaseName()."\n");
    exit(1);
}

$missing = array_values(array_filter(
    $requiredColumns,
    static fn (string $column): bool => ! Schema::hasColumn('calendar_feed_states', $column)
));

if ($missing !== []) {
    fwrite(STDERR, '[ERRO] calendar_feed_states existe, mas faltam colunas: '.implode(', ', $missing)."\n");
    exit(1);
}

echo '[OK] calendar_feed_states disponível e estruturalmente válida.' . "\n";
echo '[INFO] Driver: '.DB::connection()->getDriverName().' · Banco: '.DB::connection()->getDatabaseName()."\n";
