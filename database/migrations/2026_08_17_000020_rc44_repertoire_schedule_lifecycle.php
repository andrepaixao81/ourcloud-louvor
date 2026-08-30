<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('repertoires')) return;

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE repertoires DROP CONSTRAINT IF EXISTS repertoires_service_id_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS repertoires_service_id_active_unique ON repertoires (service_id) WHERE deleted_at IS NULL');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS repertoires_service_id_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS repertoires_service_id_active_unique ON repertoires (service_id) WHERE deleted_at IS NULL');
            return;
        }

        // Compatibilidade defensiva para ambientes MySQL/MariaDB: não removemos
        // a constraint antiga sem uma estratégia equivalente de unicidade ativa.
        // A produção oficial do SGML usa PostgreSQL.
    }

    public function down(): void
    {
        if (! Schema::hasTable('repertoires')) return;

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS repertoires_service_id_active_unique');
            DB::statement('ALTER TABLE repertoires ADD CONSTRAINT repertoires_service_id_unique UNIQUE (service_id)');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS repertoires_service_id_active_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS repertoires_service_id_unique ON repertoires (service_id)');
        }
    }
};
