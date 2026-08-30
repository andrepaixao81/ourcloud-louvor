<?php

namespace App\Services;

use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleConfirmation;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SwapService
{
    public function __construct(
        private AvailabilityService $availability,
        private AuditService $audit,
        private NotificationService $notifications,
        private InternalCalendarService $calendar,
    ) {}

    public function acceptAndApply(SwapRequest $swap, Person $acceptingPerson): void
    {
        DB::transaction(function () use ($swap, $acceptingPerson): void {
            $swap = SwapRequest::query()->whereKey($swap->id)->lockForUpdate()->firstOrFail();
            if ($swap->status !== 'requested') {
                throw new RuntimeException('Esta solicitação de troca já foi respondida.');
            }
            if ($swap->target_person_id !== $acceptingPerson->id) {
                throw new RuntimeException('Esta solicitação foi direcionada a outro ministro.');
            }

            $this->applyLockedSwap($swap, $acceptingPerson, false);
        });
    }

    public function forceApply(SwapRequest $swap): void
    {
        DB::transaction(function () use ($swap): void {
            $swap = SwapRequest::query()->whereKey($swap->id)->lockForUpdate()->firstOrFail();
            if ($swap->status !== 'requested') {
                throw new RuntimeException('Solicitação não está apta para aplicação administrativa.');
            }
            if (! $swap->target_person_id) {
                throw new RuntimeException('Selecione o ministro substituto.');
            }

            $target = Person::query()->findOrFail($swap->target_person_id);
            $this->applyLockedSwap($swap, $target, true);
        });
    }

    public function replaceDirectly(ScheduleAssignment $assignment, Person $target, string $reason, User $actor): void
    {
        $serviceToSync = DB::transaction(function () use ($assignment, $target, $reason, $actor) {
            $assignment = ScheduleAssignment::query()
                ->with(['service', 'minister.user'])
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            if ($assignment->status !== 'published') {
                throw new RuntimeException('A alteração direta pelo menu Trocas exige uma escala publicada.');
            }
            if ($assignment->service->starts_at->lte(now())) {
                throw new RuntimeException('O culto já começou ou terminou e não aceita troca de ministro.');
            }
            if ($assignment->minister_person_id === $target->id) {
                throw new RuntimeException('Escolha um ministro diferente do atual.');
            }

            $evaluation = $this->availability->evaluateForSwap($target, $assignment->service);
            if (! ($evaluation['selectable'] ?? false)) {
                throw new RuntimeException('Substituto inválido: '.implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons']));
            }

            $oldMinister = $assignment->minister;
            $oldMinisterId = $assignment->minister_person_id;
            $assignment->update([
                'minister_person_id' => $target->id,
                'score' => null,
                'assignment_mode' => 'manual',
                'explanation' => [
                    'Troca direta realizada pela liderança/administração no menu Trocas',
                    'Habilitação específica por tipo dispensada; conflitos sobrepostos e indisponibilidades revalidados',
                    ...(($evaluation['warnings'] ?? []) ? ['Exceção operacional: '.implode('; ', $evaluation['warnings'])] : []),
                ],
                'assigned_by' => $actor->id,
            ]);

            ScheduleConfirmation::query()->where('schedule_assignment_id', $assignment->id)->delete();
            SwapRequest::query()
                ->where('schedule_assignment_id', $assignment->id)
                ->where('status', 'requested')
                ->update(['status' => 'cancelled']);

            if ($oldMinister?->user) {
                $this->notifications->create(
                    $oldMinister->user,
                    'Alteração de escala',
                    $target->display_name.' passou a ministrar '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'.',
                    'information', 'normal', route('my.agenda'), null,
                    ['assignment_id' => $assignment->id]
                );
            }
            $target->loadMissing('user');
            if ($target->user) {
                $this->notifications->create(
                    $target->user,
                    'Você foi incluído na escala',
                    'A liderança definiu você para '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'.',
                    'information', 'high', route('my.agenda'), null,
                    ['assignment_id' => $assignment->id]
                );
            }

            $this->audit->event('schedule.direct_replacement', $assignment, $reason, [
                'from' => $oldMinisterId,
                'to' => $target->id,
                'actor_user_id' => $actor->id,
            ]);

            return $assignment->service->fresh();
        });

        $this->calendar->syncService($serviceToSync);
    }

    private function applyLockedSwap(SwapRequest $swap, Person $target, bool $forced): void
    {
        $assignment = ScheduleAssignment::query()
            ->with(['service', 'minister.user'])
            ->lockForUpdate()
            ->findOrFail($swap->schedule_assignment_id);

        if ($assignment->status !== 'published') {
            throw new RuntimeException('Trocas entre ministros só podem ocorrer em escala já publicada.');
        }
        if ($assignment->service->starts_at->lte(now())) {
            throw new RuntimeException('O culto já começou ou terminou e não aceita nova troca.');
        }
        if ($assignment->minister_person_id !== $swap->requester_person_id) {
            throw new RuntimeException('A escala mudou depois da solicitação. Crie uma nova solicitação de troca.');
        }

        $evaluation = $this->availability->evaluateForSwap($target, $assignment->service);
        if (! ($evaluation['selectable'] ?? false)) {
            throw new RuntimeException('Substituto inválido: '.implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons']));
        }

        $oldMinisterId = $assignment->minister_person_id;
        $assignment->update([
            'minister_person_id' => $target->id,
            'score' => null,
            'explanation' => [
                $forced ? 'Troca aplicada pela administração' : 'Troca solicitada pelo ministro e aceita pelo substituto',
                'Conflitos sobrepostos e indisponibilidades revalidados; habilitação por tipo não é exigida em troca direta',
                ...(($evaluation['warnings'] ?? []) ? ['Exceção operacional: '.implode('; ', $evaluation['warnings'])] : []),
            ],
            'assigned_by' => $forced ? auth()->id() : $assignment->assigned_by,
        ]);

        // RC46/RC48: não existe confirmação de escala. Qualquer registro legado
        // de confirmação é removido quando a atribuição muda.
        ScheduleConfirmation::query()
            ->where('schedule_assignment_id', $assignment->id)
            ->delete();

        $swap->update([
            'status' => $forced ? 'approved' : 'accepted',
            'accepted_at' => now(),
            'approved_by' => $forced ? auth()->id() : null,
            'approved_at' => $forced ? now() : null,
        ]);

        $swap->loadMissing(['requester.user', 'targetPerson.user']);
        if ($swap->requester?->user) {
            $this->notifications->create(
                $swap->requester->user,
                'Troca de escala concluída',
                $target->display_name.' assumiu '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'.',
                'information',
                'normal',
                route('my.agenda'),
                null,
                ['swap_request_id' => $swap->id, 'assignment_id' => $assignment->id]
            );
        }
        $target->loadMissing('user');
        if ($target->user) {
            $this->notifications->create(
                $target->user,
                'Você assumiu uma ministração',
                'A troca foi concluída para '.$assignment->service->name.'. Você já pode revisar/editar o repertório deste culto.',
                'information',
                'high',
                route('repertoire.show', $assignment->service),
                null,
                ['swap_request_id' => $swap->id, 'assignment_id' => $assignment->id]
            );
        }

        $this->audit->event('schedule.swap.applied', $assignment, $swap->reason, [
            'from' => $oldMinisterId,
            'to' => $target->id,
            'swap_request_id' => $swap->id,
            'forced' => $forced,
        ]);

        // RC44: troca em escala publicada atualiza a agenda externa.
        $this->calendar->syncService($assignment->service->fresh());
    }
}
