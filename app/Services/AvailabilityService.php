<?php

namespace App\Services;

use App\Models\MinisterAvailability;
use App\Models\MinisterAvailabilityDateOverride;
use App\Models\MinisterCongregationPermission;
use App\Models\MinisterServicePermission;
use App\Models\MinisterUnavailability;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\ServiceAdditionalMinister;
use App\Models\Service;

class AvailabilityService
{
    public function evaluate(Person $person, Service $service): array
    {
        return $this->evaluateInternal($person, $service, false, false);
    }

    /**
     * Atribuição manual estrita: ignora somente habilitação por tipo de culto.
     * Mantém disponibilidade, congregação e a regra normal de um culto por dia.
     */
    public function evaluateManual(Person $person, Service $service): array
    {
        return $this->evaluateInternal($person, $service, true, false);
    }

    /**
     * Avaliação usada pelo gerador mensal. O gerador continua evitando, por padrão,
     * mais de um culto por ministro no mesmo dia para preservar o rodízio.
     */
    public function evaluateForGeneration(Person $person, Service $service): array
    {
        return $this->evaluateInternal($person, $service, false, true);
    }

    /**
     * RC76: alteração deliberada pela liderança/administração pode aceitar o mesmo
     * ministro em dois cultos do mesmo dia, desde que não haja sobreposição real de
     * horário nem indisponibilidade declarada. O sistema mantém a advertência para
     * deixar a exceção evidente antes da escolha.
     */
    public function evaluateForManualOverride(Person $person, Service $service): array
    {
        $evaluation = $this->evaluateManual($person, $service);
        $softReasons = ['Já escalado como ministro em outro culto neste dia'];
        $warnings = array_values(array_filter(
            $evaluation['reasons'] ?? [],
            fn (string $reason): bool => in_array($reason, $softReasons, true)
        ));
        $blockingReasons = array_values(array_filter(
            $evaluation['reasons'] ?? [],
            fn (string $reason): bool => ! in_array($reason, $softReasons, true)
        ));

        return array_merge($evaluation, [
            'selectable' => $blockingReasons === [],
            'warnings' => $warnings,
            'blocking_reasons' => $blockingReasons,
            'same_day_emergency_override' => $warnings !== [],
        ]);
    }

    public function evaluateForSwap(Person $person, Service $service): array
    {
        return $this->evaluateForManualOverride($person, $service);
    }

    private function evaluateInternal(Person $person, Service $service, bool $ignoreServiceTypePermission, bool $ignoreSameDayAssignments): array
    {
        $reasons = [];
        if ($person->status !== 'active') {
            $reasons[] = 'Pessoa inativa';
        }

        $serviceEnd = $service->ends_at ?? $service->starts_at->copy()->addHours(2);

        $blocked = MinisterUnavailability::query()
            ->where('person_id', $person->id)
            ->where('starts_at', '<', $serviceEnd)
            ->where('ends_at', '>', $service->starts_at)
            ->exists();
        if ($blocked) {
            $reasons[] = 'Indisponibilidade temporária';
        }

        $dateOverrides = MinisterAvailabilityDateOverride::query()
            ->where('person_id', $person->id)
            ->whereDate('override_date', $service->starts_at->toDateString())
            ->get();

        $matchingUnavailable = $dateOverrides
            ->where('kind', 'unavailable')
            ->contains(fn ($override) => $this->overrideMatchesService($override, $service));
        if ($matchingUnavailable) {
            $reasons[] = 'Indisponível especificamente nesta data';
        }

        $explicitAvailable = $dateOverrides
            ->where('kind', 'available')
            ->contains(fn ($override) => $this->overrideMatchesService($override, $service));

        // RC76: a disponibilidade pode ser declarada de duas formas independentes:
        // 1) por dias pares/ímpares do mês, sem escolher dia da semana; ou
        // 2) por dia da semana, com filtro par/ímpar opcional.
        // Se existir ao menos uma regra, basta uma delas combinar com o culto.
        $allAvailabilityRules = MinisterAvailability::query()
            ->where('person_id', $person->id)
            ->where('active', true)
            ->get();

        if (! $explicitAvailable && $allAvailabilityRules->isNotEmpty()) {
            $matches = $allAvailabilityRules->contains(fn ($rule) => $this->availabilityRuleMatchesService($rule, $service));

            if (! $matches) {
                $reasons[] = 'Fora da disponibilidade cadastrada';
            }
        }

        if (! $ignoreServiceTypePermission) {
            $servicePermission = MinisterServicePermission::query()
                ->where('person_id', $person->id)
                ->where('service_type_id', $service->service_type_id)
                ->first();

            if (! $servicePermission?->allowed) {
                $reasons[] = $service->type?->requires_authorization
                    ? 'Autorização específica obrigatória ausente para este tipo de culto'
                    : 'Ministro não habilitado para este tipo de culto';
            }
        }

        if ($service->congregation_id) {
            $congregationPermission = MinisterCongregationPermission::query()
                ->where('person_id', $person->id)
                ->where('congregation_id', $service->congregation_id)
                ->first();
            if ($congregationPermission && ! $congregationPermission->allowed) {
                $reasons[] = 'Não autorizado para esta congregação';
            }
        }

        if (! $ignoreSameDayAssignments) {
            $dayStart = $service->starts_at->copy()->startOfDay();
            $dayEnd = $service->starts_at->copy()->endOfDay();

            $alreadyPrimarySameDay = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereIn('status', ['draft', 'published'])
                ->whereHas('service', fn ($q) => $q
                    ->where('id', '!=', $service->id)
                    ->whereBetween('starts_at', [$dayStart, $dayEnd])
                    ->whereNotIn('status', ['cancelled']))
                ->exists();

            $alreadyAdditionalSameDay = ServiceAdditionalMinister::query()
                ->where('person_id', $person->id)
                ->whereIn('status', ['draft', 'published'])
                ->whereHas('service', fn ($q) => $q
                    ->where('id', '!=', $service->id)
                    ->whereBetween('starts_at', [$dayStart, $dayEnd])
                    ->whereNotIn('status', ['cancelled']))
                ->exists();

            if ($alreadyPrimarySameDay || $alreadyAdditionalSameDay) {
                $reasons[] = 'Já escalado como ministro em outro culto neste dia';
            }

            $conflict = ScheduleAssignment::query()
                ->where('minister_person_id', $person->id)
                ->whereIn('status', ['draft', 'published'])
                ->whereHas('service', fn ($q) => $q
                    ->where('id', '!=', $service->id)
                    ->where('starts_at', '<', $serviceEnd)
                    ->where(function ($overlap) use ($service) {
                        $overlap->where('ends_at', '>', $service->starts_at)
                            ->orWhere(function ($openEnded) use ($service) {
                                $openEnded->whereNull('ends_at')
                                    ->where('starts_at', '>', $service->starts_at->copy()->subHours(2));
                            });
                    }))
                ->exists();

            $additionalConflict = ServiceAdditionalMinister::query()
                ->where('person_id', $person->id)
                ->whereIn('status', ['draft', 'published'])
                ->whereHas('service', fn ($q) => $q
                    ->where('id', '!=', $service->id)
                    ->where('starts_at', '<', $serviceEnd)
                    ->where(function ($overlap) use ($service) {
                        $overlap->where('ends_at', '>', $service->starts_at)
                            ->orWhere(function ($openEnded) use ($service) {
                                $openEnded->whereNull('ends_at')
                                    ->where('starts_at', '>', $service->starts_at->copy()->subHours(2));
                            });
                    }))
                ->exists();

            if ($conflict || $additionalConflict) {
                $reasons[] = 'Conflito de horário';
            }
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'explicit_available' => $explicitAvailable,
            'manual_type_override' => $ignoreServiceTypePermission,
            'same_day_assignments_ignored' => $ignoreSameDayAssignments,
        ];
    }

    private function availabilityRuleMatchesService(object $rule, Service $service): bool
    {
        $scope = $rule->rule_scope ?? 'weekday';
        $day = $service->starts_at->day;

        if ($scope === 'month_parity') {
            if ($rule->parity_rule === 'even' && $day % 2 !== 0) return false;
            if ($rule->parity_rule === 'odd' && $day % 2 === 0) return false;
        } else {
            if ((int) $rule->weekday !== (int) $service->starts_at->dayOfWeek) return false;
            if ($rule->parity_rule === 'even' && $day % 2 !== 0) return false;
            if ($rule->parity_rule === 'odd' && $day % 2 === 0) return false;
        }

        $starts = $service->starts_at->format('H:i:s');
        $ends = ($service->ends_at ?? $service->starts_at->copy()->addHours(2))->format('H:i:s');
        if ($rule->starts_time && $starts < $rule->starts_time) return false;
        if ($rule->ends_time && $ends > $rule->ends_time) return false;
        return true;
    }

    private function overrideMatchesService(object $override, Service $service): bool
    {
        if (! $override->starts_time && ! $override->ends_time) {
            return true;
        }

        $starts = $service->starts_at->format('H:i:s');
        $ends = ($service->ends_at ?? $service->starts_at->copy()->addHours(2))->format('H:i:s');

        if ($override->starts_time && $starts < $override->starts_time) return false;
        if ($override->ends_time && $ends > $override->ends_time) return false;
        return true;
    }
}
