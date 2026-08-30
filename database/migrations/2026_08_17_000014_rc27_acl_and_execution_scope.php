<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $definitions = [
            'participation.execute_own' => ['participation', 'Operar o Modo Culto somente da própria escala publicada'],
            'participation.manage_execution' => ['participation', 'Administrar Modo Culto de qualquer culto'],
            'participation.manage_roster' => ['participation', 'Registrar ministros, músicos, backs e participantes efetivos'],
        ];

        $permissionIds = [];
        foreach ($definitions as $name => [$module, $description]) {
            $existing = DB::table('permissions')->where('name', $name)->first();
            if ($existing) {
                $permissionIds[$name] = $existing->id;
                continue;
            }

            $id = (string) Str::uuid();
            DB::table('permissions')->insert([
                'id' => $id,
                'name' => $name,
                'module' => $module,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionIds[$name] = $id;
        }

        $attach = static function (string $roleId, string $permissionId): void {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        };

        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $attach($roleId, $permissionId);
            }
        }

        foreach (DB::table('roles')->where('slug', 'minister')->pluck('id') as $roleId) {
            $attach($roleId, $permissionIds['participation.execute_own']);

            // RC27 separa execução própria de cadastro administrativo de equipe.
            $legacyIds = DB::table('permissions')
                ->whereIn('name', ['participation.register', 'participation.update'])
                ->pluck('id');
            if ($legacyIds->isNotEmpty()) {
                DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->whereIn('permission_id', $legacyIds)
                    ->delete();
            }
        }

        // Compatibilidade com a instalação homologada: algumas contas de administração
        // usam papel Ministro + ACL individual, em vez do papel Administrador. Quem já
        // possui users.permissions é tratado como administrador de acessos e recebe as
        // novas capacidades administrativas do Modo Culto sem perder o modelo ACL.
        $usersPermissionsId = DB::table('permissions')->where('name', 'users.permissions')->value('id');
        if ($usersPermissionsId) {
            $adminUserIds = DB::table('role_user as ru')
                ->join('permission_role as pr', 'pr.role_id', '=', 'ru.role_id')
                ->where('pr.permission_id', $usersPermissionsId)
                ->pluck('ru.user_id')
                ->merge(
                    DB::table('user_permission_overrides')
                        ->where('permission_id', $usersPermissionsId)
                        ->where('allowed', true)
                        ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->pluck('user_id')
                )
                ->unique()
                ->values();

            foreach ($adminUserIds as $userId) {
                foreach (['participation.manage_execution', 'participation.manage_roster'] as $permissionName) {
                    $permissionId = $permissionIds[$permissionName];
                    $exists = DB::table('user_permission_overrides')
                        ->where('user_id', $userId)
                        ->where('permission_id', $permissionId)
                        ->exists();
                    if ($exists) continue;

                    DB::table('user_permission_overrides')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                        'allowed' => true,
                        'expires_at' => null,
                        'reason' => 'RC27 - compatibilidade de administração existente',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Importante: não removemos ACLs de contas Membro. O papel continua sem
        // permissões por padrão, mas uma ACL explícita PERMITIR/NEGAR é soberana.
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', [
            'participation.execute_own',
            'participation.manage_execution',
            'participation.manage_roster',
        ])->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('user_permission_overrides')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
