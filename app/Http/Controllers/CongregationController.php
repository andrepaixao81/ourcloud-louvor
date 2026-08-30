<?php

namespace App\Http\Controllers;

use App\Models\Congregation;
use App\Models\Location;
use App\Models\Person;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CongregationController extends Controller
{
    public function index()
    {
        return view('congregations.index', [
            'congregations' => Congregation::query()->withTrashed()->orderByRaw('deleted_at is not null')->orderBy('name')->get(),
            'locations' => Location::query()->withTrashed()->with('congregation')->orderByRaw('deleted_at is not null')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:180', Rule::unique('congregations','name')->where('organization_id',$request->user()->organization_id)->whereNull('deleted_at')],
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:3000',
        ]);
        Congregation::query()->create($data + ['organization_id' => $request->user()->organization_id, 'active' => true]);
        return back()->with('status','Congregação cadastrada.');
    }

    public function update(Request $request, Congregation $congregation)
    {
        $data = $request->validate([
            'name' => ['required','string','max:180', Rule::unique('congregations','name')->ignore($congregation->id)->where('organization_id',$request->user()->organization_id)->whereNull('deleted_at')],
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:3000',
            'active' => 'nullable|boolean',
        ]);
        $data['active'] = $request->boolean('active');
        $congregation->update($data);
        return back()->with('status','Congregação atualizada.');
    }

    public function destroy(Request $request, Congregation $congregation)
    {
        $futureServices = Service::query()->where('congregation_id',$congregation->id)->where('starts_at','>=',now())->exists();
        $activePeople = Person::query()->where('congregation_id',$congregation->id)->where('status','active')->exists();
        $activeLocations = Location::query()->where('congregation_id',$congregation->id)->where('active',true)->exists();
        if ($futureServices || $activePeople || $activeLocations) {
            throw ValidationException::withMessages([
                'congregation' => 'Não é possível excluir esta congregação enquanto houver culto futuro, pessoa ativa ou local ativo vinculado. Reatribua os vínculos ou apenas desative a congregação.',
            ]);
        }
        $congregation->update(['active' => false]);
        $congregation->delete();
        return back()->with('status','Congregação excluída logicamente. O histórico foi preservado.');
    }

    public function restore(string $congregation)
    {
        $item = Congregation::query()->withTrashed()->findOrFail($congregation);
        $item->restore();
        $item->update(['active' => true]);
        return back()->with('status','Congregação restaurada.');
    }

    public function storeLocation(Request $request)
    {
        $data = $this->validateLocation($request);
        Location::query()->create($data + ['organization_id' => $request->user()->organization_id, 'active' => true]);
        return back()->with('status','Local cadastrado.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $data = $this->validateLocation($request, $location);
        $data['active'] = $request->boolean('active');
        $location->update($data);
        return back()->with('status','Local atualizado.');
    }

    public function destroyLocation(Request $request, Location $location)
    {
        if (Service::query()->where('location_id',$location->id)->where('starts_at','>=',now())->exists()) {
            throw ValidationException::withMessages([
                'location' => 'Não é possível excluir este local enquanto houver culto futuro vinculado. Altere o local dos cultos futuros ou apenas desative-o.',
            ]);
        }
        $location->update(['active' => false]);
        $location->delete();
        return back()->with('status','Local excluído logicamente. O histórico foi preservado.');
    }

    public function restoreLocation(string $location)
    {
        $item = Location::query()->withTrashed()->findOrFail($location);
        $item->restore();
        $item->update(['active' => true]);
        return back()->with('status','Local restaurado.');
    }

    private function validateLocation(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => ['required','string','max:180'],
            'congregation_id' => ['nullable','uuid',Rule::exists('congregations','id')->where('organization_id',$request->user()->organization_id)->whereNull('deleted_at')],
            'address' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);
    }
}
