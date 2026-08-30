<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\MinisterAssistanceContext;

class AdministrationController extends Controller
{
    public function index(MinisterAssistanceContext $assistance)
    {
        $user = auth()->user();
        $allowed = collect([
            'administration.settings',
            'users.permissions',
            'administration.audit',
            'administration.integrations',
            'administration.whatsapp',
        ])->contains(fn (string $permission) => $user->hasPermission($permission));

        abort_unless($allowed, 403);

        $ministers = collect();
        $assistedPerson = null;
        $canMinisterAssistance = $user->canAssistMinisters();
        if ($canMinisterAssistance) {
            $ministers = Person::query()->where('status', 'active')
                ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
                ->whereHas('user', fn ($q) => $q->where('active', true))
                ->with('user.roles')
                ->orderBy('display_name')->get();
            $assistedPerson = $assistance->assistedPerson($user);
        }

        return view('admin.index', compact('ministers', 'assistedPerson', 'canMinisterAssistance'));
    }
}
