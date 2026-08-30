<?php

namespace App\Http\Controllers;

use App\Models\MinistryRole;
use App\Models\Person;
use App\Models\RepertoireItem;
use App\Models\Service;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionMinister;
use App\Models\ServiceParticipant;
use App\Models\Song;
use App\Models\SongExecution;
use App\Services\AuditService;
use App\Services\ExecutionAccessService;
use App\Services\ExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExecutionController extends Controller
{
    public function show(Request $request, Service $service, ExecutionAccessService $access)
    {
        abort_unless($access->canView($request->user(), $service), 403, 'O Modo Culto só pode ser acessado pelo ministro publicado deste culto ou pela administração.');

        $execution = ServiceExecution::query()->firstOrCreate(
            ['service_id' => $service->id],
            ['organization_id' => $service->organization_id, 'status' => 'not_started']
        );
        $execution->load(['songs.song', 'songs.performer', 'participants.person', 'participants.roles', 'ministers.person']);
        $service->load(['repertoire.items.song', 'repertoire.items.minister', 'assignment.minister', 'additionalMinisters.minister']);

        $canManageRoster = $access->canManageRoster($request->user());
        $ministerOptions = collect([$service->assignment?->minister])
            ->filter()
            ->merge($service->additionalMinisters->pluck('minister')->filter())
            ->merge($execution->ministers->pluck('person')->filter())
            ->unique('id')->sortBy('display_name')->values();

        return view('executions.show', [
            'service' => $service,
            'execution' => $execution,
            'songs' => Song::query()->where('status', 'active')->orderBy('title')->get(),
            'people' => $canManageRoster
                ? Person::query()->where('status', 'active')->orderBy('display_name')->get()
                : collect(),
            'roles' => $canManageRoster
                ? MinistryRole::query()->where('active', true)->orderBy('name')->get()
                : collect(),
            'canOperate' => $access->canOperate($request->user(), $service),
            'canManageAny' => $access->canManageAny($request->user()),
            'canManageRoster' => $canManageRoster,
            'canCorrectSongs' => $access->canCorrectSongs($request->user(), $execution),
            'ministerOptions' => $ministerOptions,
        ]);
    }

    public function start(Request $request, Service $service, ExecutionService $executionService, ExecutionAccessService $access)
    {
        abort_unless($access->canOperate($request->user(), $service), 403, 'Somente o ministro publicado deste culto ou a administração pode iniciar a ministração.');

        try {
            $executionService->start($service);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['execution' => $e->getMessage()]);
        }

        return back()->with('status', 'Ministração iniciada.');
    }

    public function performRepertoire(Request $request, ServiceExecution $execution, RepertoireItem $item, ExecutionService $service, ExecutionAccessService $access)
    {
        $execution->loadMissing('service');
        abort_unless($execution->service && $access->canOperate($request->user(), $execution->service), 403, 'Você não é o ministro responsável por este culto.');

        $data = $request->validate([
            'performed_key' => ['nullable', 'string', 'max:20'],
            'performed_by_person_id' => ['nullable', 'uuid'],
        ]);
        // RC84: o repertório já define quem ministra cada louvor. No Modo Culto,
        // usamos esse vínculo como padrão; a administração ainda pode corrigi-lo explicitamente.
        $requestedPerformer = $data['performed_by_person_id'] ?? null;
        if (! $requestedPerformer && $item->minister_person_id) {
            $requestedPerformer = $item->minister_person_id;
        }
        $performedBy = $access->canManageAny($request->user())
            ? $this->resolvePerformedBy($request, $execution->service, $access, $requestedPerformer)
            : ($item->minister_person_id ?: $this->resolvePerformedBy($request, $execution->service, $access, null));
        try {
            $service->performFromRepertoire($execution, $item, $data['performed_key'] ?? null, $performedBy);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['execution' => $e->getMessage()]);
        }

        return back()->with('status', 'Louvor registrado como ministrado.');
    }

    public function performSong(Request $request, ServiceExecution $execution, ExecutionService $service, ExecutionAccessService $access)
    {
        $execution->loadMissing('service');
        abort_unless($execution->service && $access->canOperate($request->user(), $execution->service), 403, 'Você não é o ministro responsável por este culto.');

        $data = $request->validate([
            'song_id' => ['required', 'uuid'],
            'performed_key' => ['nullable', 'string', 'max:20'],
            'performed_by_person_id' => ['nullable', 'uuid'],
        ]);
        $song = Song::query()->findOrFail($data['song_id']);
        $performedBy = $this->resolvePerformedBy($request, $execution->service, $access, $data['performed_by_person_id'] ?? null);
        try {
            $service->performSong($execution, $song, $data['performed_key'] ?? null, $performedBy);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['execution' => $e->getMessage()]);
        }

        return back()->with('status', 'Louvor adicionado à execução.');
    }

    public function addParticipant(Request $request, ServiceExecution $execution, ExecutionAccessService $access)
    {
        abort_unless($access->canManageRoster($request->user()), 403, 'Somente a administração pode registrar músicos, backs e demais participantes efetivos.');
        abort_unless(in_array($execution->status, ['in_progress', 'finished', 'reopened'], true), 422);

        $data = $request->validate([
            'person_id' => ['required', 'uuid'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $person = Person::query()->findOrFail($data['person_id']);
        $validRoleCount = MinistryRole::query()->whereIn('id', array_unique($data['roles']))->count();
        if ($validRoleCount !== count(array_unique($data['roles']))) {
            throw ValidationException::withMessages(['roles' => 'Uma ou mais funções não pertencem à organização atual.']);
        }

        DB::transaction(function () use ($data, $request, $execution, $person): void {
            $participant = ServiceParticipant::query()->firstOrCreate(
                ['service_execution_id' => $execution->id, 'person_id' => $person->id],
                ['organization_id' => $execution->organization_id, 'recorded_by' => $request->user()->id, 'notes' => $data['notes'] ?? null]
            );
            $participant->roles()->sync($data['roles']);
        });

        return back()->with('status', 'Participante registrado pela administração.');
    }

    public function addMinister(Request $request, ServiceExecution $execution, ExecutionAccessService $access)
    {
        abort_unless($access->canManageRoster($request->user()), 403, 'Somente a administração pode registrar ministros efetivos.');
        abort_unless(in_array($execution->status, ['in_progress', 'finished', 'reopened'], true), 422);

        $data = $request->validate(['person_id' => ['required', 'uuid'], 'is_primary' => ['nullable', 'boolean']]);
        $person = Person::query()->findOrFail($data['person_id']);

        DB::transaction(function () use ($request, $execution, $person): void {
            if ($request->boolean('is_primary')) {
                ServiceExecutionMinister::query()->where('service_execution_id', $execution->id)->update(['is_primary' => false]);
            }
            ServiceExecutionMinister::query()->updateOrCreate(
                ['service_execution_id' => $execution->id, 'person_id' => $person->id],
                ['organization_id' => $execution->organization_id, 'is_primary' => $request->boolean('is_primary'), 'recorded_by' => $request->user()->id]
            );
        });

        return back()->with('status', 'Ministro efetivo registrado pela administração.');
    }


    public function removeParticipant(Request $request, ServiceExecution $execution, ServiceParticipant $participant, ExecutionAccessService $access)
    {
        abort_unless($access->canManageRoster($request->user()), 403, 'Somente a administração pode corrigir participantes efetivos.');
        abort_unless($participant->service_execution_id === $execution->id, 404);

        $participant->delete();

        return back()->with('status', 'Participante removido do registro efetivo.');
    }

    public function removeMinister(Request $request, ServiceExecution $execution, ServiceExecutionMinister $minister, ExecutionAccessService $access)
    {
        abort_unless($access->canManageRoster($request->user()), 403, 'Somente a administração pode corrigir ministros efetivos.');
        abort_unless($minister->service_execution_id === $execution->id, 404);

        $minister->delete();

        return back()->with('status', 'Ministro removido do registro efetivo.');
    }

    public function updatePerformedSong(Request $request, ServiceExecution $execution, SongExecution $songExecution, ExecutionService $service, ExecutionAccessService $access, AuditService $audit)
    {
        abort_unless($access->canCorrectSongs($request->user(), $execution), 403, 'Você não pode corrigir os registros efetivos deste Modo Culto.');
        abort_unless($songExecution->service_execution_id === $execution->id, 404);

        $data = $request->validate([
            'performed_key' => ['nullable', 'string', 'max:20'],
            'performed_by_person_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $old = $songExecution->only(['performed_key', 'performed_by_person_id']);
        $performedBy = $songExecution->performed_by_person_id;
        if ($access->canManageAny($request->user()) && array_key_exists('performed_by_person_id', $data)) {
            $performedBy = $this->resolvePerformedBy($request, $execution->service, $access, $data['performed_by_person_id'] ?? null, true);
        }
        $service->correctSongExecution($execution, $songExecution, $data['performed_key'] ?? null);
        $songExecution->update(['performed_by_person_id' => $performedBy]);
        $service->rememberPerformedKey($execution, $songExecution->fresh(), 'execution_correction');
        $audit->modelEvent('corrected', $songExecution, $old, $songExecution->fresh()?->only(['performed_key', 'performed_by_person_id']) ?? [], $data['reason'] ?? 'Correção durante o Modo Culto');

        return back()->with('status', 'Registro do louvor corrigido.');
    }

    public function removePerformedSong(Request $request, ServiceExecution $execution, SongExecution $songExecution, ExecutionService $service, ExecutionAccessService $access, AuditService $audit)
    {
        abort_unless($access->canCorrectSongs($request->user(), $execution), 403, 'Você não pode remover os registros efetivos deste Modo Culto.');
        abort_unless($songExecution->service_execution_id === $execution->id, 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $snapshot = [
            'song_id' => $songExecution->song_id,
            'title' => $songExecution->song?->title ?? $songExecution->song_title_snapshot,
            'position' => $songExecution->position,
            'performed_key' => $songExecution->performed_key,
            'performed_by_person_id' => $songExecution->performed_by_person_id,
            'execution_source' => $songExecution->execution_source,
        ];
        $audit->modelEvent('removed', $songExecution, $snapshot, [], $data['reason'] ?? 'Registro desfeito durante o Modo Culto');
        $service->removeSongExecution($execution, $songExecution);

        return back()->with('status', 'Registro efetivo removido. Se não houver outros registros efetivos, agora é possível cancelar a abertura do Modo Culto.');
    }

    public function finish(Request $request, ServiceExecution $execution, ExecutionService $service, ExecutionAccessService $access)
    {
        $execution->loadMissing('service');
        abort_unless($execution->service && $access->canOperate($request->user(), $execution->service), 403, 'Somente o ministro responsável deste culto ou a administração pode finalizar a ministração.');

        try {
            $service->finish($execution);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['execution' => $e->getMessage()]);
        }

        return back()->with('status', 'Ministração encerrada.');
    }

    public function cancel(Request $request, ServiceExecution $execution, ExecutionService $service, ExecutionAccessService $access)
    {
        $execution->loadMissing('service');
        abort_unless($execution->service && $access->canCancelOpening($request->user(), $execution), 403, 'Você não pode cancelar a abertura deste Modo Culto.');

        try {
            $service->cancelOpening($execution);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['execution' => $e->getMessage()]);
        }

        $route = $access->canManageAny($request->user()) ? 'services.show' : 'my.agenda';
        $parameters = $route === 'services.show' ? [$execution->service] : [];

        return redirect()->route($route, $parameters)->with('status', 'Abertura do Modo Culto cancelada. O culto continua agendado normalmente.');
    }
    private function resolvePerformedBy(Request $request, Service $service, ExecutionAccessService $access, ?string $requestedPersonId, bool $allowNull = false): ?string
    {
        $service->loadMissing(['assignment.minister', 'additionalMinisters.minister', 'execution.ministers.person']);

        $allowed = collect([$service->assignment?->minister])
            ->filter()
            ->merge($service->additionalMinisters->pluck('minister')->filter())
            ->merge($service->execution?->ministers?->pluck('person')->filter() ?? collect())
            ->unique('id')->values();

        if (! $access->canManageAny($request->user())) {
            $personId = $request->user()->person?->id;
            if ($personId && $allowed->contains('id', $personId)) return $personId;
            return $allowed->count() === 1 ? $allowed->first()->id : null;
        }

        if ($requestedPersonId) {
            abort_unless($allowed->contains('id', $requestedPersonId), 422, 'O ministro informado não pertence a este culto.');
            return $requestedPersonId;
        }

        if ($allowed->count() === 1) return $allowed->first()->id;
        return $allowNull ? null : null;
    }

}
