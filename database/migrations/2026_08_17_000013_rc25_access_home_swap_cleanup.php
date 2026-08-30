<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Reforça o contrato do papel Membro: conta básica sem módulos operacionais.
        $memberRoleIds = DB::table('roles')->where('slug', 'member')->pluck('id');
        if ($memberRoleIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('role_id', $memberRoleIds)->delete();

            // Durante a homologação algumas contas Membro receberam ACLs individuais.
            // Se a conta possui SOMENTE o papel Membro, removemos essas permissões antigas;
            // para liberar operação deve-se atribuir Participante do Louvor/Ministro/outro papel.
            $memberOnlyUsers = DB::table('role_user as ru')
                ->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.slug', 'member')
                ->select('ru.user_id')
                ->groupBy('ru.user_id')
                ->havingRaw('COUNT(*) = 1')
                ->pluck('user_id');
            if ($memberOnlyUsers->isNotEmpty()) {
                DB::table('user_permission_overrides')->whereIn('user_id', $memberOnlyUsers)->delete();
            }
        }

        // Garante o fluxo de troca para contas Ministro já existentes em bases atualizadas
        // desde versões antigas, sem depender de executar novamente o seeder completo.
        $swapPermissionId = DB::table('permissions')->where('name', 'schedule.swap')->value('id');
        if ($swapPermissionId) {
            foreach (DB::table('roles')->where('slug', 'minister')->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $swapPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Não restauramos ACLs antigas deliberadamente removidas.
    }
};
