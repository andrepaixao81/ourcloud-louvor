<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $view = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.view')->first();
        if (! $view) return;

        // RC87: somente Membro comum não visualiza aniversariantes.
        Role::query()->withoutGlobalScopes()
            ->where('slug', 'member')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach($view->id));

        Role::query()->withoutGlobalScopes()
            ->whereIn('slug', ['administrator', 'ministry_manager', 'minister', 'participant', 'leadership'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$view->id]));
    }

    public function down(): void
    {
        $view = Permission::query()->withoutGlobalScopes()->where('name', 'birthdays.view')->first();
        if (! $view) return;

        Role::query()->withoutGlobalScopes()
            ->whereIn('slug', ['minister', 'participant'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach($view->id));
    }
};
