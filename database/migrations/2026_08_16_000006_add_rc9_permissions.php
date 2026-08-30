<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $definitions = [
            'services.delete' => 'Excluir logicamente cultos sem histórico',
            'services.restore' => 'Restaurar cultos excluídos logicamente',
        ];

        foreach ($definitions as $name => $description) {
            $permission = DB::table('permissions')->where('name', $name)->first();
            if (! $permission) {
                $id = (string) Str::uuid();
                DB::table('permissions')->insert([
                    'id' => $id,
                    'name' => $name,
                    'module' => 'services',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $permissionId = $id;
            } else {
                $permissionId = $permission->id;
                DB::table('permissions')->where('id', $permissionId)->update([
                    'description' => $description,
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('roles')
                ->whereIn('slug', ['administrator', 'ministry_manager'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['services.delete', 'services.restore'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('user_permission_overrides')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
