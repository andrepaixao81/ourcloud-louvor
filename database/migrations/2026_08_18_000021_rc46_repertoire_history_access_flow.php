<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repertoires', function (Blueprint $table): void {
            $table->foreignUuid('opening_song_id')->nullable()->after('service_id')->references('id')->on('songs')->nullOnDelete();
            $table->foreignUuid('opening_song_version_id')->nullable()->after('opening_song_id')->references('id')->on('song_versions')->nullOnDelete();
            $table->string('opening_key', 20)->nullable()->after('opening_song_version_id');
            $table->string('opening_youtube_url', 500)->nullable()->after('opening_key');
            $table->text('opening_notes')->nullable()->after('opening_youtube_url');
            $table->text('final_notes')->nullable()->after('notes');
        });

        Schema::table('service_executions', function (Blueprint $table): void {
            $table->timestampTz('auto_finalized_at')->nullable()->after('finished_at');
            $table->unsignedInteger('repertoire_revision_snapshot')->nullable()->after('auto_finalized_at');
        });

        $definitions = [
            'administration.mail' => ['administration', 'Administrar SMTP e testes de e-mail'],
        ];
        foreach ($definitions as $name => [$module, $description]) {
            $permission = DB::table('permissions')->where('name', $name)->first();
            if (! $permission) {
                DB::table('permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'module' => $module,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')->where('id', $permission->id)->update(['description' => $description, 'updated_at' => now()]);
            }
        }

        $permissionId = DB::table('permissions')->where('name', 'administration.mail')->value('id');
        $administratorRoleIds = DB::table('roles')->where('slug', 'administrator')->pluck('id');
        foreach ($administratorRoleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        // RC46: SMTP, auditoria e administração de usuários são estritamente do
        // papel Administrador. ACL individual não é usada para elevar esses menus.
        $strictAdminPermissions = DB::table('permissions')
            ->whereIn('name', ['administration.audit', 'users.permissions', 'administration.mail'])
            ->pluck('id');
        if ($strictAdminPermissions->isNotEmpty()) {
            DB::table('permission_role')
                ->whereIn('permission_id', $strictAdminPermissions)
                ->whereNotIn('role_id', $administratorRoleIds)
                ->delete();
        }

        // Ministro passa a visualizar a escala publicada em menu próprio.
        $scheduleViewId = DB::table('permissions')->where('name', 'schedule.view')->value('id');
        foreach (DB::table('roles')->where('slug', 'minister')->pluck('id') as $roleId) {
            if ($scheduleViewId) DB::table('permission_role')->insertOrIgnore(['permission_id' => $scheduleViewId, 'role_id' => $roleId]);
        }

        // Responsável pelo ministério precisa conseguir alterar/trocar ministro
        // diretamente, inclusive em escala publicada.
        $managerPermissions = DB::table('permissions')
            ->whereIn('name', ['schedule.view', 'schedule.update', 'schedule.force_swap', 'published_schedule.view'])
            ->pluck('id');
        foreach (DB::table('roles')->where('slug', 'ministry_manager')->pluck('id') as $roleId) {
            foreach ($managerPermissions as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('service_executions', function (Blueprint $table): void {
            $table->dropColumn(['auto_finalized_at', 'repertoire_revision_snapshot']);
        });

        Schema::table('repertoires', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opening_song_version_id');
            $table->dropConstrainedForeignId('opening_song_id');
            $table->dropColumn(['opening_key', 'opening_youtube_url', 'opening_notes', 'final_notes']);
        });
    }
};
