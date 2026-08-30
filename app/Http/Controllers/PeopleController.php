<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Models\Congregation;
use App\Models\MinisterServicePermission;
use App\Models\MinistryRole;
use App\Models\Person;
use App\Models\ServiceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($data['q'] ?? ''));

        $people = Person::query()
            ->with([
                'congregation',
                'ministryRoles',
                'user',
                'servicePermissions' => fn ($q) => $q->where('allowed', true)->with('serviceType'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($filter) use ($like): void {
                    $filter->whereRaw('LOWER(display_name) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE LOWER(?)', [$like]);
                });
            })
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return view('people.index', compact('people', 'search'));
    }

    public function create()
    {
        $serviceTypes = $this->serviceTypes();

        return view('people.form', [
            'person' => new Person(),
            'congregations' => $this->congregations(),
            'roles' => $this->roles(),
            'serviceTypes' => $serviceTypes,
            // Para um novo ministro, tipos comuns começam habilitados; tipos com
            // autorização específica precisam ser marcados deliberadamente.
            'selectedServiceTypeIds' => $serviceTypes
                ->where('requires_authorization', false)
                ->pluck('id')
                ->all(),
        ]);
    }

    public function store(PersonRequest $request)
    {
        DB::transaction(function () use ($request): void {
            $person = Person::query()->create(
                $request->safe()->except(['ministry_roles', 'minister_service_types']) + [
                    'organization_id' => $request->user()->organization_id,
                ]
            );

            $person->ministryRoles()->sync($request->input('ministry_roles', []));
            $this->syncMinisterServiceTypes($person, $request->input('minister_service_types', []));
        });

        return redirect()->route('people.index')->with('status', 'Pessoa cadastrada.');
    }

    public function edit(Person $person)
    {
        $person->load(['ministryRoles', 'servicePermissions']);

        return view('people.form', [
            'person' => $person,
            'congregations' => $this->congregations(),
            'roles' => $this->roles(),
            'serviceTypes' => $this->serviceTypes(),
            'selectedServiceTypeIds' => $person->allowedServiceTypeIds(),
        ]);
    }

    public function update(PersonRequest $request, Person $person)
    {
        DB::transaction(function () use ($request, $person): void {
            $person->update($request->safe()->except(['ministry_roles', 'minister_service_types']));
            $person->ministryRoles()->sync($request->input('ministry_roles', []));
            $this->syncMinisterServiceTypes($person, $request->input('minister_service_types', []));
        });

        return redirect()->route('people.index')->with('status', 'Pessoa atualizada.');
    }

    public function destroy(Person $person)
    {
        $person->delete();

        return back()->with('status', 'Pessoa arquivada.');
    }

    private function congregations()
    {
        return Congregation::query()->where('active', true)->orderBy('name')->get();
    }

    private function roles()
    {
        return MinistryRole::query()->where('active', true)->orderBy('name')->get();
    }

    private function serviceTypes()
    {
        return ServiceType::query()->where('active', true)->orderBy('name')->get();
    }

    /** @param array<string> $serviceTypeIds */
    private function syncMinisterServiceTypes(Person $person, array $serviceTypeIds): void
    {
        $person->load('ministryRoles');

        // Somente pessoas com uma função marcada como "ministro" entram no motor
        // de escala. Ao retirar essa função, as habilitações deixam de fazer sentido.
        if (! $person->isMinister()) {
            MinisterServicePermission::query()->where('person_id', $person->id)->delete();
            return;
        }

        $selected = collect($serviceTypeIds)->unique()->values();
        $validIds = ServiceType::query()
            ->where('active', true)
            ->whereIn('id', $selected)
            ->pluck('id');

        MinisterServicePermission::query()
            ->where('person_id', $person->id)
            ->whereNotIn('service_type_id', $validIds)
            ->delete();

        foreach ($validIds as $serviceTypeId) {
            MinisterServicePermission::query()->updateOrCreate(
                [
                    'person_id' => $person->id,
                    'service_type_id' => $serviceTypeId,
                ],
                [
                    'organization_id' => $person->organization_id,
                    'allowed' => true,
                ]
            );
        }
    }
}
