<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // A partir da RC11 a elegibilidade por tipo de culto é uma lista positiva:
        // sem linha allowed=true, o ministro não entra como candidato daquele tipo.
        // Para preservar o comportamento atual, ministros já cadastrados recebem
        // habilitação nos tipos comuns existentes. Tipos que exigem autorização
        // específica não são liberados automaticamente.
        $pairs = DB::table('people as p')
            ->join('person_ministry_role as pmr', 'pmr.person_id', '=', 'p.id')
            ->join('ministry_roles as mr', 'mr.id', '=', 'pmr.ministry_role_id')
            ->join('service_types as st', function ($join): void {
                $join->on('st.organization_id', '=', 'p.organization_id')
                    ->where('st.active', true)
                    ->where('st.requires_authorization', false)
                    ->whereNull('st.deleted_at');
            })
            ->leftJoin('minister_service_permissions as msp', function ($join): void {
                $join->on('msp.person_id', '=', 'p.id')
                    ->on('msp.service_type_id', '=', 'st.id');
            })
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->where('mr.is_minister_role', true)
            ->where('mr.active', true)
            ->whereNull('mr.deleted_at')
            ->whereNull('msp.id')
            ->distinct()
            ->get([
                'p.id as person_id',
                'p.organization_id',
                'st.id as service_type_id',
            ]);

        $now = now();
        foreach ($pairs->chunk(500) as $chunk) {
            DB::table('minister_service_permissions')->insert(
                $chunk->map(fn ($pair) => [
                    'id' => Str::uuid()->toString(),
                    'organization_id' => $pair->organization_id,
                    'person_id' => $pair->person_id,
                    'service_type_id' => $pair->service_type_id,
                    'allowed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        // Não removemos habilitações no rollback porque, após a RC11, elas podem
        // ter sido alteradas pelo administrador e já representam dado de negócio.
    }
};
