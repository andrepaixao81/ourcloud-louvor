<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('swap_requests', function (Blueprint $table): void {
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
        });

        // Cultos excluídos logicamente não podem continuar aparecendo na agenda.
        // Preservamos o CalendarEntry para auditoria/integração, porém também o
        // marcamos como excluído logicamente quando a origem Service estiver excluída.
        DB::statement(<<<'SQL'
            UPDATE calendar_entries ce
               SET deleted_at = COALESCE(ce.deleted_at, NOW()),
                   status = 'cancelled',
                   updated_at = NOW()
              FROM services s
             WHERE ce.source_type = 'service'
               AND ce.source_id = s.id
               AND s.deleted_at IS NOT NULL
               AND ce.deleted_at IS NULL
        SQL);

        $ministerRoleIds = DB::table('roles')->where('slug', 'minister')->pluck('id');

        // O ministro responsável por uma escala PUBLICADA pode administrar o
        // repertório daquele culto. A autorização dinâmica ainda verifica se ele
        // continua sendo o ministro atual antes de cada alteração.
        $manageOwnId = DB::table('permissions')->where('name', 'repertoire.manage_own')->value('id');
        if ($manageOwnId) {
            foreach ($ministerRoleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $manageOwnId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // /schedule contém rascunhos, pontuação e explicações internas. Ministro
        // comum acompanha somente a escala PUBLICADA em Minha Agenda.
        $scheduleViewId = DB::table('permissions')->where('name', 'schedule.view')->value('id');
        if ($scheduleViewId) {
            DB::table('permission_role')
                ->where('permission_id', $scheduleViewId)
                ->whereIn('role_id', $ministerRoleIds)
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('swap_requests', function (Blueprint $table): void {
            $table->dropColumn(['accepted_at', 'rejected_at']);
        });
    }
};
