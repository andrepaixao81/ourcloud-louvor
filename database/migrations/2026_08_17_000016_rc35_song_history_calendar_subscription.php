<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('song_executions', function (Blueprint $table): void {
            $table->foreignUuid('performed_by_person_id')
                ->nullable()
                ->after('performed_key')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
            $table->index(['performed_by_person_id', 'song_id'], 'song_exec_performer_song_idx');
        });

        $definitions = [
            'song_history.view' => ['songs', 'Visualizar histórico de músicas ministradas, ministros e tons'],
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

        foreach (DB::table('roles')->whereIn('slug', [
            'administrator', 'ministry_manager', 'minister', 'participant', 'leadership',
        ])->get() as $role) {
            $attach($role->id, $permissionIds['song_history.view']);
        }
    }

    public function down(): void
    {
        Schema::table('song_executions', function (Blueprint $table): void {
            $table->dropIndex('song_exec_performer_song_idx');
            $table->dropConstrainedForeignId('performed_by_person_id');
        });

        $ids = DB::table('permissions')->whereIn('name', ['song_history.view'])->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('user_permission_overrides')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
