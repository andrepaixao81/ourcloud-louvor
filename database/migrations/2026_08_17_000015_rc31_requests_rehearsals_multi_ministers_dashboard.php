<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedTinyInteger('minister_slots')->default(1)->after('status');
        });

        Schema::table('song_requests', function (Blueprint $table): void {
            $table->foreignUuid('requested_for_service_id')->nullable()->after('requested_by')->references('id')->on('services')->nullOnDelete();
            $table->foreignUuid('suggested_category_id')->nullable()->after('suggested_key')->references('id')->on('song_categories')->nullOnDelete();
        });

        Schema::create('service_additional_ministers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->unsignedTinyInteger('position')->default(2);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignUuid('assigned_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('published_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['service_id', 'status']);
            $table->index(['person_id', 'status']);
        });
        DB::statement('CREATE UNIQUE INDEX service_additional_ministers_active_person_unique ON service_additional_ministers (service_id, person_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX service_additional_ministers_active_position_unique ON service_additional_ministers (service_id, position) WHERE deleted_at IS NULL');

        Schema::create('rehearsal_song_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('song_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('practicing'); // practicing / ready / removed
            $table->unsignedTinyInteger('priority')->default(2); // 1 alta, 2 normal, 3 baixa
            $table->text('notes')->nullable();
            $table->foreignUuid('added_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['song_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX rehearsal_song_items_active_song_unique ON rehearsal_song_items (organization_id, song_id) WHERE status = 'practicing'");

        $definitions = [
            'rehearsals.view' => ['rehearsals', 'Visualizar louvores em ensaio'],
            'rehearsals.manage' => ['rehearsals', 'Gerenciar louvores em ensaio'],
            'schedule.additional_ministers' => ['schedule', 'Definir ministros adicionais em cultos especiais'],
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
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        };

        foreach (DB::table('roles')->get() as $role) {
            if (in_array($role->slug, ['administrator', 'ministry_manager'], true)) {
                foreach ($permissionIds as $permissionId) $attach($role->id, $permissionId);
            }
            if (in_array($role->slug, ['minister', 'participant', 'leadership'], true)) {
                $attach($role->id, $permissionIds['rehearsals.view']);
            }
        }

        // Dashboard analítico: somente administração e responsável pelo ministério por padrão.
        $dashboardId = DB::table('permissions')->where('name', 'reports.dashboard')->value('id');
        if ($dashboardId) {
            $leadershipRoleIds = DB::table('roles')->where('slug', 'leadership')->pluck('id');
            if ($leadershipRoleIds->isNotEmpty()) {
                DB::table('permission_role')->where('permission_id', $dashboardId)->whereIn('role_id', $leadershipRoleIds)->delete();
            }
            foreach (DB::table('roles')->whereIn('slug', ['administrator', 'ministry_manager'])->pluck('id') as $roleId) {
                $attach($roleId, $dashboardId);
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rehearsal_song_items_active_song_unique');
        DB::statement('DROP INDEX IF EXISTS service_additional_ministers_active_person_unique');
        DB::statement('DROP INDEX IF EXISTS service_additional_ministers_active_position_unique');
        Schema::dropIfExists('rehearsal_song_items');
        Schema::dropIfExists('service_additional_ministers');
        Schema::table('song_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_for_service_id');
            $table->dropConstrainedForeignId('suggested_category_id');
        });
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('minister_slots'));

        $ids = DB::table('permissions')->whereIn('name', ['rehearsals.view', 'rehearsals.manage', 'schedule.additional_ministers'])->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('user_permission_overrides')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
