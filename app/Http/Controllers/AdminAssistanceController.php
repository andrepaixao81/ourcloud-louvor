<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\AuditService;
use App\Services\MinisterAssistanceContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAssistanceController extends Controller
{
    public function start(Request $request, MinisterAssistanceContext $context, AuditService $audit)
    {
        $actor = $request->user();
        abort_unless($actor?->canAssistMinisters(), 403);

        $data = $request->validate([
            'person_id' => [
                'required',
                'uuid',
                Rule::exists('people', 'id')
                    ->where('organization_id', $actor->organization_id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $person = Person::query()->with(['ministryRoles', 'user.roles.permissions'])->findOrFail($data['person_id']);
        abort_unless($person->isMinister(), 422, 'A pessoa selecionada não possui função ministerial ativa.');

        $context->start($actor, $person);
        $audit->event('minister.assistance.started', $person, $data['reason'], [
            'assisted_person_id' => $person->id,
            'assisted_person_name' => $person->display_name,
            'actor_user_id' => $actor->id,
            'actor_is_administrator' => $actor->isAdministrator(),
            'actor_is_ministry_manager' => $actor->isMinistryManager(),
        ]);

        $destination = $person->user?->preferredHomeRouteName() ?: 'home';

        return redirect()->route($destination)->with(
            'status',
            'Assistência iniciada para '.$person->display_name.'. A interface e as permissões agora são exatamente as da conta assistida; a auditoria mantém seu usuário real como executor.'
        );
    }

    public function stop(Request $request, MinisterAssistanceContext $context, AuditService $audit)
    {
        $actor = $request->user();
        abort_unless($actor?->canAssistMinisters(), 403);

        $person = $context->assistedPerson($actor);
        if ($person) {
            $audit->event('minister.assistance.ended', $person, null, [
                'assisted_person_id' => $person->id,
                'assisted_person_name' => $person->display_name,
                'actor_user_id' => $actor->id,
                'actor_is_administrator' => $actor->isAdministrator(),
                'actor_is_ministry_manager' => $actor->isMinistryManager(),
            ]);
        }

        $context->stop();

        return redirect()->route('admin.index')->with('status', 'Modo de assistência encerrado.');
    }
}
