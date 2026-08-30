<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $name = 'calendar_event.delete';
        $permissionId = DB::table('permissions')->where('name', $name)->value('id');
        if (! $permissionId) {
            $permissionId = (string) Str::uuid();
            DB::table('permissions')->insert([
                'id' => $permissionId,
                'name' => $name,
                'module' => 'calendar',
                'description' => 'Excluir programação criada diretamente na Agenda',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permissions')->where('id', $permissionId)->update([
                'module' => 'calendar',
                'description' => 'Excluir programação criada diretamente na Agenda',
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roles')->whereIn('slug', ['administrator', 'ministry_manager'])->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'calendar_event.delete')->value('id');
        if (! $permissionId) return;
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('user_permission_overrides')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
