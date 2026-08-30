<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\SwapRequest;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use App\Services\SwapService;
use App\Services\MinisterAssistanceContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SwapController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        $pendingSwapCount = app(\App\Services\SwapAlertService::class)->pendingCount($request->user(), $person);
        $query = SwapRequest::query()->with(['assignment.service.type', 'requester.user', 'targetPerson.user'])->latest();

        if (! $request->user()->hasPermission('schedule.force_swap')) {
            abort_unless($person, 403);
            $query->where(function ($where) use ($person): void {
                $where->where('requester_person_id', $person->id)
                    ->orWhere('target_person_id', $person->id);
            });
        }

        $swaps = $query->paginate(30);
        $eligibleTargetsBySwap = collect();
        $managementAssignments = collect();
        $managementTargetsByAssignment = collect();
        $managementRows = collect();
        $managementMonth = $request->query('month');
        if (! is_string($managementMonth) || ! preg_match('/^\d{4}-\d{2}$/', $managementMonth)) {
            $managementMonth = now()->format('Y-m');
        }
        $managementFrom = Carbon::createFromFormat('Y-m-d', $managementMonth.'-01')->startOfMonth();
        $managementTo = $managementFrom->copy()->endOfMonth();
        $managementMonthDate = $managementFrom->copy();
        if ($request->user()->hasPermission('schedule.force_swap')) {
            $ministerCandidates = Person::query()
                ->where('status', 'active')
                ->whereHas('ministryRoles', fn ($q) => $q->where('ministry_roles.is_minister_role', true)->where('ministry_roles.active', true))
                ->with('user')
                ->orderBy('display_name')->get();
            $eligibleTargetsBySwap = collect($swaps->items())->mapWithKeys(function (SwapRequest $swap) use ($ministerCandidates, $availability) {
                $service = $swap->assignment?->service;
                if (! $service || $swap->status !== 'requested') return [$swap->id => collect()];
                return [$swap->id => $ministerCandidates
                    ->reject(fn (Person $candidate) => $candidate->id === $swap->requester_person_id)
                    ->map(fn (Person $candidate) => $this->targetOption($candidate, $service, $availability))
                    ->values()];
            });

            // RC48: liderança/administração não depende de existir uma solicitação.
            // A própria tela Trocas permite substituir diretamente qualquer ministro
            // de uma escala publicada futura.
            // RC50: a visão administrativa é limitada ao mês selecionado e não
            // executa N x M consultas de disponibilidade ao abrir a página. A
            // validação completa é feita no POST, imediatamente antes da troca.
            $managementAssignments = ScheduleAssignment::query()
                ->with(['service.type', 'minister'])
                ->where('status', 'published')
                ->whereHas('service', fn ($q) => $q
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('starts_at', [$managementFrom, $managementTo])
                    ->where('starts_at', '>', now()))
                ->get()
                ->sortBy(fn (ScheduleAssignment $assignment) => $assignment->service?->starts_at)
                ->values();
            $managementTargetsByAssignment = $managementAssignments->mapWithKeys(function (ScheduleAssignment $scheduleAssignment) use ($ministerCandidates) {
                return [$scheduleAssignment->id => $ministerCandidates
                    ->reject(fn (Person $candidate) => $candidate->id === $scheduleAssignment->minister_person_id)
                    ->map(fn (Person $candidate) => [
                        'person' => $candidate,
                        'eligible' => true,
                        'selectable' => true,
                        'warnings' => [],
                        'blocking_reasons' => [],
                        'reasons' => [],
                    ])
                    ->values()];
            });

            // RC51: a Blade não recebe mais um objeto local chamado $assignment
            // para montar a rota administrativa. O controller entrega linhas
            // normalizadas e autocontidas, evitando variável fora de escopo na view.
            $managementRows = $managementAssignments->map(function (ScheduleAssignment $scheduleAssignment) use ($managementTargetsByAssignment) {
                $targets = $managementTargetsByAssignment->get($scheduleAssignment->id, collect());

                return [
                    'assignment_id' => (string) $scheduleAssignment->id,
                    'service_name' => $scheduleAssignment->service?->name ?? 'Culto',
                    'starts_at' => $scheduleAssignment->service?->starts_at?->format('d/m/Y H:i') ?? 'data não definida',
                    'current_minister' => $scheduleAssignment->minister?->display_name ?? 'não definido',
                    'targets' => $targets,
                    'eligible_count' => $targets->where('selectable', true)->count(),
                ];
            })->values();
        }

        return view('swaps.index', compact('swaps', 'eligibleTargetsBySwap', 'managementRows', 'managementMonth', 'managementMonthDate', 'pendingSwapCount'));
    }

    public function store(Request $request, AvailabilityService $availability, NotificationService $notifications)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        abort_unless($person, 403);
        $data = $request->validate([
            'schedule_assignment_id' => ['required', 'uuid'],
            'target_person_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $assignment = ScheduleAssignment::query()->with('service')->findOrFail($data['schedule_assignment_id']);
        abort_unless($assignment->minister_person_id === $person->id, 403);

        if ($assignment->status !== 'published') {
            throw ValidationException::withMessages([
                'schedule_assignment_id' => 'A troca só pode ser solicitada depois que a escala estiver validada e publicada.',
            ]);
        }

        if ($assignment->service->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'schedule_assignment_id' => 'Não é possível solicitar troca para um culto que já começou ou terminou.',
            ]);
        }

        if ($data['target_person_id'] === $person->id) {
            throw ValidationException::withMessages(['target_person_id' => 'Escolha outro ministro.']);
        }

        $target = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('ministry_roles.is_minister_role', true)->where('ministry_roles.active', true))
            ->with('user')
            ->findOrFail($data['target_person_id']);

        $evaluation = $availability->evaluateForSwap($target, $assignment->service);
        if (! ($evaluation['selectable'] ?? false)) {
            throw ValidationException::withMessages([
                'target_person_id' => 'Este ministro não pode assumir este culto: '.implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons']),
            ]);
        }

        if (SwapRequest::query()->where('schedule_assignment_id', $assignment->id)->where('requester_person_id', $person->id)->where('status', 'requested')->exists()) {
            throw ValidationException::withMessages(['schedule_assignment_id' => 'Já existe uma solicitação de troca aguardando resposta para este culto.']);
        }

        $swap = SwapRequest::query()->create([
            'organization_id' => $request->user()->organization_id,
            'schedule_assignment_id' => $assignment->id,
            'requester_person_id' => $person->id,
            'target_person_id' => $target->id,
            'status' => 'requested',
            'type' => 'replacement',
            'reason' => $data['reason'],
        ]);

        $target->loadMissing('user');
        if ($target->user) {
            $notifications->create(
                $target->user,
                'Solicitação de troca de escala',
                $person->display_name.' pediu que você assuma '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'.',
                'action',
                'high',
                route('my.agenda'),
                $assignment->service->starts_at->copy()->subDay(),
                ['swap_request_id' => $swap->id, 'assignment_id' => $assignment->id]
            );
        }

        return back()->with('status', $target->user && $target->user->active
            ? 'Troca solicitada. A mudança será efetivada quando '.$target->display_name.' aceitar.'
            : 'Troca registrada para '.$target->display_name.'. Como esse ministro ainda não possui acesso ativo ao sistema, a liderança pode concluir a troca diretamente pelo menu Trocas.');
    }

    public function setTarget(Request $request, SwapRequest $swap, AvailabilityService $availability, NotificationService $notifications)
    {
        abort_unless($request->user()->hasPermission('schedule.force_swap'), 403);
        abort_unless($swap->status === 'requested', 409);
        $data = $request->validate(['target_person_id' => ['required', 'uuid']]);
        $target = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('ministry_roles.is_minister_role', true)->where('ministry_roles.active', true))
            ->with('user')
            ->findOrFail($data['target_person_id']);
        $swap->loadMissing('assignment.service', 'requester');
        $evaluation = $availability->evaluateForSwap($target, $swap->assignment->service);
        if (! ($evaluation['selectable'] ?? false)) {
            throw ValidationException::withMessages(['target_person_id' => 'Este ministro não pode assumir: '.implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons'])]);
        }
        $swap->update(['target_person_id' => $target->id]);
        $target->loadMissing('user');
        if ($target->user) {
            $notifications->create($target->user, 'Solicitação de troca de escala', ($swap->requester?->display_name ?? 'Um ministro').' pediu substituição em '.$swap->assignment->service->name.'.', 'action', 'high', route('my.agenda'), $swap->assignment->service->starts_at->copy()->subDay(), ['swap_request_id' => $swap->id]);
        }
        return back()->with('status', 'Solicitação direcionada para '.$target->display_name.'. A troca será concluída quando a pessoa aceitar.');
    }

    public function forceReplace(Request $request, ScheduleAssignment $assignment, SwapService $service)
    {
        abort_unless($request->user()->hasPermission('schedule.force_swap'), 403);
        $data = $request->validate([
            'target_person_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $target = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('ministry_roles.is_minister_role', true)->where('ministry_roles.active', true))
            ->findOrFail($data['target_person_id']);

        try {
            $service->replaceDirectly($assignment, $target, $data['reason'], $request->user());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'target_person_id' => $e->getMessage(),
            ]);
        }
        return back()->with('status', 'Ministro alterado diretamente na escala publicada. Agenda e repertório permanecem vinculados ao culto.');
    }

    public function accept(Request $request, SwapRequest $swap, SwapService $service)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        abort_unless($person && $swap->target_person_id === $person->id, 403);

        $service->acceptAndApply($swap, $person);
        return back()->with('status', 'Troca aceita e aplicada. O culto agora está sob sua responsabilidade e o repertório pode ser atualizado.');
    }

    public function reject(Request $request, SwapRequest $swap, NotificationService $notifications)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        abort_unless($person && $swap->target_person_id === $person->id, 403);
        abort_unless($swap->status === 'requested', 409);

        $swap->update(['status' => 'rejected', 'rejected_at' => now()]);
        $swap->loadMissing('requester.user', 'assignment.service');
        if ($swap->requester?->user) {
            $notifications->create(
                $swap->requester->user,
                'Troca de escala recusada',
                $person->display_name.' não pôde assumir '.$swap->assignment?->service?->name.'.',
                'information',
                'normal',
                route('my.agenda'),
                null,
                ['swap_request_id' => $swap->id]
            );
        }

        return back()->with('status', 'Troca recusada. A escala original permanece sem alteração.');
    }

    public function approve(SwapRequest $swap, SwapService $service)
    {
        $service->forceApply($swap);
        return back()->with('status', 'Troca aplicada administrativamente.');
    }

    /**
     * Monta uma opção de substituição sem permitir que um dado legado/inconsistente
     * de um candidato derrube toda a tela de Trocas. A falha fica registrada no log
     * e o candidato aparece desabilitado com uma mensagem operacional.
     */
    private function targetOption(Person $candidate, $service, AvailabilityService $availability): array
    {
        if (! $service || ! $service->starts_at) {
            return [
                'person' => $candidate,
                'eligible' => false,
                'selectable' => false,
                'warnings' => [],
                'blocking_reasons' => ['Culto sem data/horário válido'],
                'reasons' => ['Culto sem data/horário válido'],
            ];
        }

        try {
            $evaluation = $availability->evaluateForSwap($candidate, $service);

            return [
                'person' => $candidate,
                'eligible' => (bool) ($evaluation['eligible'] ?? false),
                'selectable' => (bool) ($evaluation['selectable'] ?? $evaluation['eligible'] ?? false),
                'warnings' => array_values($evaluation['warnings'] ?? []),
                'blocking_reasons' => array_values($evaluation['blocking_reasons'] ?? []),
                'reasons' => array_values($evaluation['reasons'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::warning('Falha ao avaliar candidato de troca; opção mantida como indisponível.', [
                'candidate_person_id' => $candidate->id,
                'service_id' => $service->id ?? null,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'person' => $candidate,
                'eligible' => false,
                'selectable' => false,
                'warnings' => [],
                'blocking_reasons' => ['Não foi possível validar automaticamente este ministro'],
                'reasons' => ['Não foi possível validar automaticamente este ministro'],
            ];
        }
    }
}
