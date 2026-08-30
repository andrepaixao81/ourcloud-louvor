<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('special_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('special_events', 'created_by')) {
                $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('special_events', 'updated_by')) {
                $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('special_event_responses')) {
            Schema::create('special_event_responses', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('special_event_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
                $table->string('response', 20); // yes / no
                $table->text('note')->nullable();
                $table->foreignUuid('responded_by')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->timestampTz('responded_at')->nullable();
                $table->timestamps();
                $table->unique(['special_event_id', 'person_id']);
                $table->index(['organization_id', 'response']);
            });
        }

        if (! Schema::hasTable('whatsapp_settings')) {
            Schema::create('whatsapp_settings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('enabled')->default(false);
                $table->string('graph_version', 20)->default('v26.0');
                $table->string('phone_number_id', 120)->nullable();
                $table->text('access_token')->nullable();
                $table->string('template_name', 160)->default('sgml_aviso');
                $table->string('language_code', 20)->default('pt_BR');
                $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_delivery_logs')) {
            Schema::create('whatsapp_delivery_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('phone', 40);
                $table->string('event_key', 100)->nullable();
                $table->string('title', 180);
                $table->text('summary')->nullable();
                $table->text('action_url')->nullable();
                $table->string('status', 30)->default('queued');
                $table->string('provider_message_id', 255)->nullable();
                $table->text('error')->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status']);
                $table->index(['organization_id', 'created_at']);
            });
        }

        Schema::table('repertoires', function (Blueprint $table): void {
            if (! Schema::hasColumn('repertoires', 'announced_revision')) {
                $table->unsignedInteger('announced_revision')->nullable();
            }
            if (! Schema::hasColumn('repertoires', 'announced_at')) {
                $table->timestampTz('announced_at')->nullable();
            }
            if (! Schema::hasColumn('repertoires', 'announced_by')) {
                $table->foreignUuid('announced_by')->nullable()->references('id')->on('users')->nullOnDelete();
            }
        });

        $permissions = [
            'special_events.view' => ['special_events', 'Visualizar eventos especiais do ministério'],
            'special_events.respond' => ['special_events', 'Confirmar participação em eventos especiais'],
            'special_events.manage' => ['special_events', 'Criar, alterar e administrar eventos especiais'],
            'administration.whatsapp' => ['administration', 'Configurar avisos automáticos pelo WhatsApp Business'],
        ];

        foreach ($permissions as $name => [$module, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => DB::table('permissions')->where('name', $name)->value('id') ?: (string) Str::uuid(),
                    'module' => $module,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id', 'name');
        $roles = DB::table('roles')->whereIn('slug', ['administrator', 'ministry_manager', 'minister', 'participant', 'leadership'])->get();
        foreach ($roles as $role) {
            $allowed = match ($role->slug) {
                'administrator' => ['special_events.view', 'special_events.respond', 'special_events.manage', 'administration.whatsapp'],
                'ministry_manager' => ['special_events.view', 'special_events.respond', 'special_events.manage'],
                'minister', 'participant', 'leadership' => ['special_events.view', 'special_events.respond'],
                default => [],
            };
            foreach ($allowed as $permissionName) {
                if (! isset($permissionIds[$permissionName])) continue;
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionIds[$permissionName],
                    'role_id' => $role->id,
                ]);
            }
        }

        // Participante do Louvor (músico/back) não possui Minha Agenda pessoal.
        $agendaPermissionId = DB::table('permissions')->where('name', 'agenda.view')->value('id');
        if ($agendaPermissionId) {
            DB::table('roles')->where('slug', 'participant')->pluck('id')->each(function ($roleId) use ($agendaPermissionId): void {
                DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $agendaPermissionId)->delete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('repertoires', function (Blueprint $table): void {
            $columns = array_values(array_filter(['announced_revision', 'announced_at', 'announced_by'], fn ($column) => Schema::hasColumn('repertoires', $column)));
            if ($columns) $table->dropColumn($columns);
        });
        Schema::dropIfExists('whatsapp_delivery_logs');
        Schema::dropIfExists('whatsapp_settings');
        Schema::dropIfExists('special_event_responses');
    }
};
