<?php

namespace App\Http\Controllers;

use App\Models\MinisterServicePermission;
use App\Models\Person;
use App\Models\ServiceType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminMinisterEligibilityController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->organization_id;

        $serviceTypes = ServiceType::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->withCount([
                'ministerPermissions as allowed_ministers_count' => fn ($q) => $q
                    ->where('allowed', true)
                    ->whereHas('person', fn ($personQuery) => $personQuery
                        ->where('status', 'active')
                        ->whereHas('ministryRoles', fn ($roleQuery) => $roleQuery
                            ->where('is_minister_role', true)
                            ->where('active', true))),
            ])
            ->orderBy('name')
            ->get();

        $selectedType = $serviceTypes->firstWhere('id', (string) $request->query('type')) ?: $serviceTypes->first();

        $ministers = Person::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q
                ->where('is_minister_role', true)
                ->where('active', true))
            ->with(['user', 'congregation'])
            ->orderBy('display_name')
            ->get();

        $allowedIds = collect();
        $allowedMinisters = collect();
        $availableMinisters = $ministers;

        if ($selectedType) {
            $allowedIds = MinisterServicePermission::query()
                ->where('organization_id', $organizationId)
                ->where('service_type_id', $selectedType->id)
                ->where('allowed', true)
                ->pluck('person_id');

            $allowedMinisters = $ministers->whereIn('id', $allowedIds)->values();
            $availableMinisters = $ministers->whereNotIn('id', $allowedIds)->values();
        }

        return view('admin.minister-eligibility', compact(
            'serviceTypes',
            'selectedType',
            'allowedMinisters',
            'availableMinisters'
        ));
    }

    public function store(Request $request, ServiceType $serviceType, AuditService $audit)
    {
        $organizationId = $request->user()->organization_id;
        abort_unless($serviceType->organization_id === $organizationId && $serviceType->active, 404);

        $data = $request->validate([
            'person_id' => ['required', 'uuid'],
        ]);

        $person = Person::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->findOrFail($data['person_id']);

        if (! $person->isMinister()) {
            throw ValidationException::withMessages([
                'person_id' => 'A pessoa precisa possuir uma função ministerial marcada como Ministro para entrar na escala.',
            ]);
        }

        $permission = MinisterServicePermission::query()->updateOrCreate(
            [
                'person_id' => $person->id,
                'service_type_id' => $serviceType->id,
            ],
            [
                'organization_id' => $organizationId,
                'allowed' => true,
            ]
        );

        $audit->event('minister.service_permission.granted', $permission, null, [
            'person_id' => $person->id,
            'person_name' => $person->display_name,
            'service_type_id' => $serviceType->id,
            'service_type_name' => $serviceType->name,
        ]);

        return redirect()->route('admin.minister-eligibility', ['type' => $serviceType->id])
            ->with('status', $person->display_name.' foi habilitado para '.$serviceType->name.'.');
    }

    public function destroy(Request $request, ServiceType $serviceType, Person $person, AuditService $audit)
    {
        $organizationId = $request->user()->organization_id;
        abort_unless(
            $serviceType->organization_id === $organizationId
            && $person->organization_id === $organizationId,
            404
        );

        $permission = MinisterServicePermission::query()
            ->where('organization_id', $organizationId)
            ->where('service_type_id', $serviceType->id)
            ->where('person_id', $person->id)
            ->where('allowed', true)
            ->first();

        if ($permission) {
            $audit->event('minister.service_permission.revoked', $permission, null, [
                'person_id' => $person->id,
                'person_name' => $person->display_name,
                'service_type_id' => $serviceType->id,
                'service_type_name' => $serviceType->name,
            ]);
            $permission->delete();
        }

        return redirect()->route('admin.minister-eligibility', ['type' => $serviceType->id])
            ->with('status', $person->display_name.' foi removido da habilitação de '.$serviceType->name.'.');
    }
}
