<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $view = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.view')->first();
        if (! $view) return;

        // RC86: apenas gestão/liderança pastoral visualiza aniversariantes.
        // Remove concessões históricas de papéis operacionais/comuns.
        Role::query()->withoutGlobalScopes()
            ->whereIn('slug', ['minister', 'participant', 'member'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach($view->id));

        Role::query()->withoutGlobalScopes()
            ->whereIn('slug', ['administrator', 'ministry_manager', 'leadership'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$view->id]));
    }

    public function down(): void
    {
        $view = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.view')->first();
        if (! $view) return;

        Role::query()->withoutGlobalScopes()
            ->where('slug', 'minister')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$view->id]));
    }
};
