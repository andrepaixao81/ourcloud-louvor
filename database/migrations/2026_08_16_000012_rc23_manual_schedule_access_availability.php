<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            $table->boolean('manual_minister_assignment')->default(false)->after('requires_authorization');
        });

        Schema::table('schedule_assignments', function (Blueprint $table): void {
            $table->string('assignment_mode', 20)->default('automatic')->after('status');
            $table->text('manual_reason')->nullable()->after('explanation');
        });

        Schema::create('minister_availability_date_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->date('override_date');
            $table->string('kind', 20); // available / unavailable
            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['person_id', 'override_date']);
        });

        // Soft delete precisa permitir uma nova versão da escala para o mesmo culto.
        Schema::table('schedule_assignments', function (Blueprint $table): void {
            $table->dropUnique('schedule_assignments_service_id_unique');
        });
        DB::statement('CREATE UNIQUE INDEX schedule_assignments_service_id_active_unique ON schedule_assignments (service_id) WHERE deleted_at IS NULL');

        // O culto de mulheres foi explicitamente definido como escolha manual durante a homologação.
        DB::table('service_types')
            ->where('slug', 'rede-de-mulheres')
            ->update(['manual_minister_assignment' => true]);

        $permissionDefinitions = [
            'agenda.view' => ['agenda', 'Visualizar a própria agenda ministerial'],
            'availability.manage' => ['agenda', 'Gerenciar a própria disponibilidade'],
            'published_schedule.view' => ['schedule', 'Visualizar a escala publicada do ministério'],
            'schedule.reset' => ['schedule', 'Revogar/resetar escala para gerar nova versão'],
            'schedule.manual_assign' => ['schedule', 'Definir ministro manualmente em culto de atribuição manual'],
        ];

        $permissionIds = [];
        foreach ($permissionDefinitions as $name => [$module, $description]) {
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

        $attach = function (string $roleId, array $permissionNames) use ($permissionIds): void {
            foreach ($permissionNames as $name) {
                $permissionId = $permissionIds[$name] ?? DB::table('permissions')->where('name', $name)->value('id');
                if (! $permissionId) continue;
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        };

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            $roles = DB::table('roles')->where('organization_id', $organizationId)->get()->keyBy('slug');

            if ($roles->has('administrator')) {
                $attach($roles['administrator']->id, array_keys($permissionDefinitions));
            }
            if ($roles->has('ministry_manager')) {
                $attach($roles['ministry_manager']->id, array_keys($permissionDefinitions));
            }
            if ($roles->has('minister')) {
                $attach($roles['minister']->id, ['agenda.view', 'availability.manage', 'published_schedule.view']);
            }
            if ($roles->has('leadership')) {
                $attach($roles['leadership']->id, ['agenda.view', 'availability.manage', 'published_schedule.view']);
            }

            // Membro é acesso básico à conta, sem dados operacionais do ministério.
            if ($roles->has('member')) {
                DB::table('permission_role')->where('role_id', $roles['member']->id)->delete();
            }

            $participantRole = $roles->get('participant');
            if (! $participantRole) {
                $participantId = (string) Str::uuid();
                DB::table('roles')->insert([
                    'id' => $participantId,
                    'organization_id' => $organizationId,
                    'name' => 'Participante do Louvor',
                    'slug' => 'participant',
                    'description' => 'Músico/back que acompanha somente informações publicadas e sua disponibilidade.',
                    'system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $participantRole = (object) ['id' => $participantId];
            }

            $attach($participantRole->id, ['agenda.view', 'availability.manage', 'published_schedule.view']);
            foreach (['repertoire.view', 'songs.view'] as $name) {
                $permissionId = DB::table('permissions')->where('name', $name)->value('id');
                if ($permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $participantRole->id]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS schedule_assignments_service_id_active_unique');

        Schema::dropIfExists('minister_availability_date_overrides');

        Schema::table('schedule_assignments', function (Blueprint $table): void {
            $table->dropColumn(['assignment_mode', 'manual_reason']);
        });
        Schema::table('service_types', function (Blueprint $table): void {
            $table->dropColumn('manual_minister_assignment');
        });
    }
};
