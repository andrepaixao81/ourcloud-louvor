<?php

namespace App\Http\Controllers;

use App\Models\MinisterAvailability;
use App\Models\MinisterAvailabilityDateOverride;
use App\Models\MinisterUnavailability;
use App\Services\MinisterAssistanceContext;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    private function person(Request $request): ?\App\Models\Person
    {
        return app(MinisterAssistanceContext::class)->person($request->user());
    }

    public function index(Request $request)
    {
        $person = $this->person($request);
        abort_unless($person, 403, 'Seu usuário ainda não está vinculado a uma pessoa.');

        $rules = MinisterAvailability::query()
            ->where('person_id', $person->id)
            ->orderBy('rule_scope')
            ->orderBy('weekday')
            ->orderBy('starts_time')
            ->get();

        return view('calendar.availability', [
            'person' => $person,
            'parityRules' => $rules->where('rule_scope', 'month_parity')->values(),
            'weeklyRules' => $rules->where('rule_scope', '!=', 'month_parity')->values(),
            // Compatibilidade com referências antigas da view/testes.
            'fixed' => $rules,
            'dateOverrides' => MinisterAvailabilityDateOverride::query()->where('person_id', $person->id)->orderByDesc('override_date')->orderBy('starts_time')->get(),
            'temporary' => MinisterUnavailability::query()->where('person_id', $person->id)->orderByDesc('starts_at')->get(),
        ]);
    }

    public function storeFixed(Request $request)
    {
        $person = $this->person($request);
        abort_unless($person, 403);
        $data = $this->fixedData($request);

        MinisterAvailability::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'person_id' => $person->id,
            'active' => true,
        ]);

        return back()->with('status', $data['rule_scope'] === 'month_parity'
            ? 'Disponibilidade por dias pares/ímpares cadastrada.'
            : 'Disponibilidade por dia da semana cadastrada.');
    }

    public function updateFixed(Request $request, MinisterAvailability $availability)
    {
        $person = $this->person($request);
        abort_unless($person && $availability->person_id === $person->id, 403);

        $availability->update($this->fixedData($request) + ['active' => true]);
        return back()->with('status', 'Regra de disponibilidade atualizada.');
    }

    public function destroyFixed(Request $request, MinisterAvailability $availability)
    {
        $person = $this->person($request);
        abort_unless($person && $availability->person_id === $person->id, 403);

        $availability->delete();
        return back()->with('status', 'Regra de disponibilidade removida.');
    }

    public function storeDateOverride(Request $request)
    {
        $person = $this->person($request);
        abort_unless($person, 403);

        $data = $request->validate([
            'override_date' => ['required', 'date'],
            'kind' => ['required', 'in:available,unavailable'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i', 'after:starts_time'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        MinisterAvailabilityDateOverride::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'person_id' => $person->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', $data['kind'] === 'available'
            ? 'Disponibilidade específica por data registrada.'
            : 'Indisponibilidade específica por data registrada.');
    }

    public function destroyDateOverride(Request $request, MinisterAvailabilityDateOverride $override)
    {
        abort_unless($this->person($request)?->id === $override->person_id, 403);
        $override->delete();
        return back()->with('status', 'Exceção por data removida.');
    }

    public function storeTemporary(Request $request)
    {
        $person = $this->person($request);
        abort_unless($person, 403);
        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason_type' => 'required|in:personal,travel,work,health,other',
            'private_notes' => 'nullable|string|max:1000',
        ]);

        MinisterUnavailability::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'person_id' => $person->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Período de indisponibilidade registrado.');
    }

    public function destroyTemporary(Request $request, MinisterUnavailability $unavailability)
    {
        $person = $this->person($request);
        abort_unless($person && $person->id === $unavailability->person_id, 403);
        $unavailability->delete();

        return back()->with('status', 'Período de indisponibilidade removido.');
    }

    private function fixedData(Request $request): array
    {
        $scope = $request->input('rule_scope', 'weekday');

        if ($scope === 'month_parity') {
            $data = $request->validate([
                'rule_scope' => ['required', 'in:month_parity'],
                'starts_time' => ['nullable', 'date_format:H:i'],
                'ends_time' => ['nullable', 'date_format:H:i', 'after:starts_time'],
                'parity_rule' => ['required', 'in:even,odd'],
            ]);
            // weekday permanece obrigatório no esquema legado; é ignorado quando o escopo é month_parity.
            $data['weekday'] = 0;
            return $data;
        }

        $data = $request->validate([
            'rule_scope' => ['nullable', 'in:weekday'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i', 'after:starts_time'],
            'parity_rule' => ['required', 'in:any,even,odd'],
        ]);
        $data['rule_scope'] = 'weekday';
        return $data;
    }
}
