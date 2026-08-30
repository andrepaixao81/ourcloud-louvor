<?php

namespace App\Services;

use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\ServiceAdditionalMinister;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleGeneratorService
{
    public function __construct(
        private AvailabilityService $availability,
        private RecurringServiceGeneratorService $recurring,
    ) {}

    public function generate(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Antes de distribuir ministros, revalida as regras recorrentes do mês.
        // Isso impede que uma ocorrência semanal antiga sobreviva a uma regra
        // do tipo "exceto 2º domingo" ou a uma Santa Ceia mensal no mesmo slot.
        $recurrenceSync = $this->recurring->synchronizeMonth($month);

        $services = Service::query()
            ->with(['type', 'assignment.minister', 'additionalMinisters'])
            ->whereBetween('starts_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'completed', 'in_progress'])
            ->orderBy('starts_at')
            ->get();

        $ministers = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q
                ->where('is_minister_role', true)
                ->where('active', true))
            ->orderBy('display_name')
            ->get();

        $result = [
            'assigned' => 0,
            'reassigned' => 0,
            'kept' => 0,
            'unassigned' => [],
            'published_invalid' => [],
            'suggestions' => [],
            'recurrence_sync' => $recurrenceSync,
        ];

        DB::transaction(function () use ($services, $ministers, $start, $end, &$result): void {
            $toPlan = collect();

            // RC48: uma sugestão ainda não publicada é recalculada por inteiro. O
            // algoritmo antigo preservava rascunhos elegíveis e, por isso, podia
            // cristalizar um desequilíbrio criado na primeira passagem do mês.
            foreach ($services as $service) {
                $assignment = $service->assignment;

                if ($service->type?->manual_minister_assignment || $assignment?->assignment_mode === 'manual') {
                    if (! $assignment) {
                        $result['unassigned'][] = [
                            'service_id' => $service->id,
                            'service' => $service->name,
                            'starts_at' => $service->starts_at->toIso8601String(),
                            'reason' => 'Este culto exige definição manual do ministro.',
                            'manual' => true,
                        ];
                        continue;
                    }

                    $currentMinister = $assignment->minister;
                    $currentEvaluation = $currentMinister
                        ? $this->availability->evaluateManual($currentMinister, $service)
                        : ['eligible' => false, 'reasons' => ['Ministro da atribuição não encontrado']];

                    if ($currentEvaluation['eligible']) {
                        $result['kept']++;
                        continue;
                    }

                    if ($assignment->status === 'published') {
                        $result['published_invalid'][] = [
                            'assignment_id' => $assignment->id,
                            'service_id' => $service->id,
                            'service' => $service->name,
                            'minister' => $currentMinister?->display_name,
                            'reasons' => $currentEvaluation['reasons'] ?: ['Atribuição manual incompatível com as regras atuais'],
                        ];
                        continue;
                    }

                    $assignment->forceDelete();
                    $service->unsetRelation('assignment');
                    $result['unassigned'][] = [
                        'service_id' => $service->id,
                        'service' => $service->name,
                        'starts_at' => $service->starts_at->toIso8601String(),
                        'reason' => 'A escolha manual anterior deixou de ser válida; defina novamente o ministro.',
                        'manual' => true,
                    ];
                    continue;
                }

                if ($assignment?->status === 'published') {
                    $currentMinister = $assignment->minister;
                    $currentEvaluation = $currentMinister
                        ? $this->availability->evaluate($currentMinister, $service)
                        : ['eligible' => false, 'reasons' => ['Ministro da atribuição não encontrado']];

                    if (! $currentEvaluation['eligible']) {
                        $result['published_invalid'][] = [
                            'assignment_id' => $assignment->id,
                            'service_id' => $service->id,
                            'service' => $service->name,
                            'minister' => $currentMinister?->display_name,
                            'reasons' => $currentEvaluation['reasons'],
                        ];
                    } else {
                        $result['kept']++;
                    }
                    continue;
                }

                if ($assignment) {
                    $assignment->forceDelete();
                    $service->unsetRelation('assignment');
                    $result['reassigned']++;
                }

                $toPlan->push($service);
            }

            // Pessoas já comprometidas em atribuições fixas/publicadas ou como
            // ministro adicional não podem receber outro culto na mesma data.
            $usedByDate = [];
            $fixedAssignments = ScheduleAssignment::query()
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$start, $end]))
                ->whereIn('status', ['draft', 'published'])
                ->with('service')
                ->get();
            foreach ($fixedAssignments as $fixed) {
                $dateKey = $fixed->service?->starts_at?->toDateString();
                if ($dateKey && $fixed->minister_person_id) {
                    $usedByDate[$dateKey][$fixed->minister_person_id] = true;
                }
            }
            $fixedAdditional = ServiceAdditionalMinister::query()
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$start, $end]))
                ->whereIn('status', ['draft', 'published'])
                ->with('service')
                ->get();
            foreach ($fixedAdditional as $extra) {
                $dateKey = $extra->service?->starts_at?->toDateString();
                if ($dateKey && $extra->person_id) {
                    $usedByDate[$dateKey][$extra->person_id] = true;
                }
            }

            // Planejamento por dia: cultos com menos candidatos viáveis são
            // resolvidos primeiro. Dentro deles, rankCandidates prioriza menor
            // carga no TIPO, depois menor carga total. Isso produz, por exemplo,
            // 2/1/1 em quatro ocorrências com três ministros quando as regras
            // permitem, em vez de 2/2/0 por efeito da ordem cronológica.
            foreach ($toPlan->groupBy(fn (Service $service) => $service->starts_at->toDateString()) as $dateKey => $dayServices) {
                $ordered = $dayServices->sort(function (Service $left, Service $right) use ($ministers, $usedByDate): int {
                    $used = array_keys($usedByDate[$left->starts_at->toDateString()] ?? []);
                    $leftCount = $ministers->filter(fn (Person $person) => ! in_array($person->id, $used, true)
                        && $this->availability->evaluateForGeneration($person, $left)['eligible'])->count();
                    $rightCount = $ministers->filter(fn (Person $person) => ! in_array($person->id, $used, true)
                        && $this->availability->evaluateForGeneration($person, $right)['eligible'])->count();
                    if ($leftCount !== $rightCount) return $leftCount <=> $rightCount;
                    return $left->starts_at->getTimestamp() <=> $right->starts_at->getTimestamp();
                })->values();

                foreach ($ordered as $service) {
                    $used = array_keys($usedByDate[$dateKey] ?? []);
                    $ranked = $this->rankCandidates($ministers, $service, $start, $end, $used, true);
                    $best = $ranked->first(fn ($item) => $item['eligible']);

                    if (! $best) {
                        $result['unassigned'][] = [
                            'service_id' => $service->id,
                            'service' => $service->name,
                            'starts_at' => $service->starts_at->toIso8601String(),
                            'reason' => 'Nenhum ministro habilitado e disponível para este culto, considerando também o limite de um culto por ministro no dia.',
                        ];
                        continue;
                    }

                    ScheduleAssignment::query()->create([
                        'organization_id' => $service->organization_id,
                        'service_id' => $service->id,
                        'minister_person_id' => $best['person']->id,
                        'status' => 'draft',
                        'score' => $best['score'],
                        'explanation' => $best['explanation'],
                        'assigned_by' => auth()->id(),
                    ]);

                    $usedByDate[$dateKey][$best['person']->id] = true;
                    $result['assigned']++;
                    $result['suggestions'][] = [
                        'service' => $service->name,
                        'minister' => $best['person']->display_name,
                        'score' => $best['score'],
                        'explanation' => $best['explanation'],
                    ];
                }
            }
        });

        return $result;
    }

    public function rankCandidates(Collection $people, Service $service, Carbon $monthStart, Carbon $monthEnd, array $usedPersonIds = [], bool $plannerMode = false): Collection
    {
        $serviceTypeId = $service->service_type_id;

        $ranked = $people->map(function (Person $person) use ($service, $monthStart, $monthEnd, $serviceTypeId, $usedPersonIds, $plannerMode) {
            if (in_array($person->id, $usedPersonIds, true)) {
                return [
                    'person' => $person, 'eligible' => false, 'score' => -999,
                    'type_month_count' => PHP_INT_MAX, 'month_count' => PHP_INT_MAX,
                    'days_since_same_type' => -1, 'explanation' => ['Já utilizado em outro culto nesta data'],
                ];
            }
            $availability = $plannerMode
                ? $this->availability->evaluateForGeneration($person, $service)
                : $this->availability->evaluate($person, $service);
            if (! $availability['eligible']) {
                return [
                    'person' => $person,
                    'eligible' => false,
                    'score' => -999,
                    'type_month_count' => PHP_INT_MAX,
                    'month_count' => PHP_INT_MAX,
                    'days_since_same_type' => -1,
                    'explanation' => $availability['reasons'],
                ];
            }

            // RC40: o rodízio passa a priorizar equilíbrio DENTRO do tipo de culto.
            // Isso evita que um ministro habilitado em vários tipos "consuma" a sua
            // cota global e seja ignorado em um tipo específico durante todo o mês.
            // Participações como ministro adicional também entram na carga mensal.
            $primaryMonthCount = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$monthStart, $monthEnd]))
                ->whereIn('status', ['draft', 'published'])
                ->count();
            $additionalMonthCount = ServiceAdditionalMinister::query()
                ->where('person_id', $person->id)
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$monthStart, $monthEnd]))
                ->whereIn('status', ['draft', 'published'])
                ->count();
            $monthCount = $primaryMonthCount + $additionalMonthCount;

            $primaryTypeMonthCount = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q
                    ->whereBetween('starts_at', [$monthStart, $monthEnd])
                    ->where('service_type_id', $serviceTypeId))
                ->whereIn('status', ['draft', 'published'])
                ->count();
            $additionalTypeMonthCount = ServiceAdditionalMinister::query()
                ->where('person_id', $person->id)
                ->whereHas('service', fn ($q) => $q
                    ->whereBetween('starts_at', [$monthStart, $monthEnd])
                    ->where('service_type_id', $serviceTypeId))
                ->whereIn('status', ['draft', 'published'])
                ->count();
            $typeMonthCount = $primaryTypeMonthCount + $additionalTypeMonthCount;

            $last30 = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [now()->subDays(30), now()]))
                ->whereIn('status', ['draft', 'published'])
                ->count();

            $last90 = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [now()->subDays(90), now()]))
                ->whereIn('status', ['draft', 'published'])
                ->count();

            $lastAssignment = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q->where('starts_at', '<', $service->starts_at))
                ->with('service')
                ->get()
                ->sortByDesc(fn ($a) => $a->service?->starts_at)
                ->first();

            $lastSameTypeAssignment = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereHas('service', fn ($q) => $q
                    ->where('service_type_id', $serviceTypeId)
                    ->where('starts_at', '<', $service->starts_at))
                ->with('service')
                ->get()
                ->sortByDesc(fn ($a) => $a->service?->starts_at)
                ->first();

            $daysSince = $lastAssignment?->service?->starts_at?->diffInDays($service->starts_at) ?? 120;
            $daysSinceSameType = $lastSameTypeAssignment?->service?->starts_at?->diffInDays($service->starts_at) ?? 180;

            // O peso por tipo é propositalmente o mais forte. Em um culto semanal com
            // três ministros habilitados, por exemplo, o motor tende a produzir A/B/C/A
            // em vez de A/B/A/B, desde que todos estejam disponíveis.
            $score = 220
                - ($typeMonthCount * 70)
                - ($monthCount * 12)
                - ($last30 * 5)
                - ($last90 * 1.5)
                + min(30, $daysSinceSameType / 4)
                + min(10, $daysSince / 10);

            if ($lastSameTypeAssignment && $daysSinceSameType <= 7) {
                $score -= 35;
            } elseif ($lastSameTypeAssignment && $daysSinceSameType <= 14) {
                $score -= 12;
            }

            if ($lastAssignment?->service?->starts_at?->isSunday()
                && $service->starts_at->isSunday()
                && $daysSince <= 7) {
                $score -= 15;
            }

            return [
                'person' => $person,
                'eligible' => true,
                'score' => round($score, 2),
                'type_month_count' => $typeMonthCount,
                'month_count' => $monthCount,
                'days_since_same_type' => $daysSinceSameType,
                'explanation' => [
                    'Habilitado para este tipo de culto',
                    'Disponível e sem bloqueios operacionais para esta ocorrência',
                    'Rodízio RC41 prioriza equilíbrio por tipo e limita cada ministro a um culto por dia',
                    "Neste tipo no mês: {$typeMonthCount}",
                    "Total de ministrações no mês: {$monthCount}",
                    "Últimos 30 dias: {$last30}",
                    "Últimos 90 dias: {$last90}",
                    "Dias desde a última neste tipo: {$daysSinceSameType}",
                ],
            ];
        });

        // Critério determinístico: menor carga no tipo, menor carga global, maior
        // intervalo desde o mesmo tipo, maior score e, por fim, nome. Assim empates
        // deixam de depender da ordem física retornada pelo banco.
        return $ranked->sort(function (array $left, array $right): int {
            if ($left['eligible'] !== $right['eligible']) {
                return $left['eligible'] ? -1 : 1;
            }
            if (! $left['eligible']) {
                return strcmp($left['person']->display_name, $right['person']->display_name);
            }

            foreach ([
                ['type_month_count', 'asc'],
                ['month_count', 'asc'],
                ['days_since_same_type', 'desc'],
                ['score', 'desc'],
            ] as [$field, $direction]) {
                if ($left[$field] == $right[$field]) continue;
                $comparison = $left[$field] <=> $right[$field];
                return $direction === 'asc' ? $comparison : -$comparison;
            }

            return strcasecmp($left['person']->display_name, $right['person']->display_name);
        })->values();
    }

}
