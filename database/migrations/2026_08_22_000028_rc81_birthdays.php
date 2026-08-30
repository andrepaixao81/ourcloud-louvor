<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('phone');
            }
        });

        $permissions = [
            'birthdays.view' => 'Visualizar aniversariantes do mês sem exibir ano/idade',
            'birthdays.manage' => 'Configurar mensagem automática de aniversário',
        ];

        foreach ($permissions as $name => $description) {
            Permission::query()->withoutGlobalScopes()->firstOrCreate(
                ['name' => $name],
                ['module' => 'birthdays', 'description' => $description]
            );
        }

        $view = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.view')->first();
        $manage = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.manage')->first();

        Role::query()->withoutGlobalScopes()->whereIn('slug', ['administrator', 'ministry_manager', 'minister', 'leadership'])->get()
            ->each(function (Role $role) use ($view): void {
                if ($view) $role->permissions()->syncWithoutDetaching([$view->id]);
            });

        Role::query()->withoutGlobalScopes()->whereIn('slug', ['administrator', 'ministry_manager'])->get()
            ->each(function (Role $role) use ($manage): void {
                if ($manage) $role->permissions()->syncWithoutDetaching([$manage->id]);
            });
    }

    public function down(): void
    {
        $ids = Permission::query()->withoutGlobalScopes()->whereIn('name', ['birthdays.view', 'birthdays.manage'])->pluck('id');
        if ($ids->isNotEmpty()) {
            foreach (Role::query()->withoutGlobalScopes()->get() as $role) {
                $role->permissions()->detach($ids);
            }
            Permission::query()->withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        Schema::table('people', function (Blueprint $table): void {
            if (Schema::hasColumn('people', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
