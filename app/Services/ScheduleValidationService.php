<?php

namespace App\Services;

use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceAdditionalMinister;
use Carbon\Carbon;

class ScheduleValidationService
{
    public function __construct(private AvailabilityService $availability) {}

    public function validateMonth(Carbon $month): array
    {
        $services = Service::query()
            ->with(['type', 'assignment.minister', 'additionalMinisters.minister'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotIn('status', ['cancelled', 'completed', 'in_progress'])
            ->get();

        $errors = [];
        $warnings = [];

        foreach ($services as $service) {
            if (! $service->assignment) {
                $errors[] = $service->type?->manual_minister_assignment
                    ? "Ministro precisa ser definido manualmente: {$service->name} em {$service->starts_at->format('d/m H:i')}"
                    : "Culto sem ministro: {$service->name} em {$service->starts_at->format('d/m H:i')}";
                continue;
            }

            $minister = $service->assignment->minister;
            if (! $minister) {
                $errors[] = "Atribuição sem ministro válido: {$service->name} em {$service->starts_at->format('d/m H:i')}";
                continue;
            }

            $manualOverride = $service->type?->manual_minister_assignment || $service->assignment->assignment_mode === 'manual';
            $evaluation = $manualOverride
                ? $this->availability->evaluateForManualOverride($minister, $service)
                : $this->availability->evaluate($minister, $service);

            if ($manualOverride) {
                if (! ($evaluation['selectable'] ?? false)) {
                    $errors[] = sprintf(
                        '%s não está elegível para %s em %s: %s',
                        $minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons'])
                    );
                }
                foreach (($evaluation['warnings'] ?? []) as $warning) {
                    $warnings[] = sprintf(
                        '%s em %s (%s): %s. Exceção manual permitida pela liderança.',
                        $minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        $warning
                    );
                }
            } else {
                $sameDayWarnings = array_values(array_filter(
                    $evaluation['reasons'] ?? [],
                    fn (string $reason): bool => $reason === 'Já escalado como ministro em outro culto neste dia'
                ));
                $blockingReasons = array_values(array_filter(
                    $evaluation['reasons'] ?? [],
                    fn (string $reason): bool => $reason !== 'Já escalado como ministro em outro culto neste dia'
                ));
                if ($blockingReasons !== []) {
                    $errors[] = sprintf(
                        '%s não está elegível para %s em %s: %s',
                        $minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        implode('; ', $blockingReasons)
                    );
                }
                foreach ($sameDayWarnings as $warning) {
                    $warnings[] = sprintf(
                        '%s em %s (%s): %s. Verifique se a duplicidade do dia foi uma escolha deliberada da liderança.',
                        $minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        $warning
                    );
                }
            }

            $slots = max(1, (int) ($service->minister_slots ?? 1));
            $neededAdditional = max(0, $slots - 1);
            $extras = $service->additionalMinisters;
            if ($extras->count() < $neededAdditional) {
                $errors[] = sprintf(
                    '%s em %s precisa de %d ministro(s) no total; faltam %d ministro(s) adicional(is).',
                    $service->name,
                    $service->starts_at->format('d/m H:i'),
                    $slots,
                    $neededAdditional - $extras->count()
                );
            } elseif ($extras->count() > $neededAdditional) {
                $errors[] = sprintf(
                    '%s em %s possui %d ministro(s) adicionais, mas o culto foi configurado para %d ministro(s) no total.',
                    $service->name,
                    $service->starts_at->format('d/m H:i'),
                    $extras->count(),
                    $slots
                );
            }

            foreach ($extras as $extra) {
                if (! $extra->minister) {
                    $errors[] = "Ministro adicional inválido em {$service->name} em {$service->starts_at->format('d/m H:i')}";
                    continue;
                }
                $extraEvaluation = $this->availability->evaluateForManualOverride($extra->minister, $service);
                if (! ($extraEvaluation['selectable'] ?? false)) {
                    $errors[] = sprintf(
                        '%s (ministro adicional) não está elegível para %s em %s: %s',
                        $extra->minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        implode('; ', $extraEvaluation['blocking_reasons'] ?? $extraEvaluation['reasons'])
                    );
                }
                foreach (($extraEvaluation['warnings'] ?? []) as $warning) {
                    $warnings[] = sprintf(
                        '%s (ministro adicional) em %s (%s): %s. Exceção manual permitida pela liderança.',
                        $extra->minister->display_name,
                        $service->name,
                        $service->starts_at->format('d/m H:i'),
                        $warning
                    );
                }
            }
        }

        $primary = ScheduleAssignment::query()
            ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->whereNotIn('status', ['cancelled', 'completed', 'in_progress']))
            ->with('minister')->get()
            ->map(fn ($item) => ['person_id' => $item->minister_person_id, 'name' => $item->minister?->display_name]);

        $additional = ServiceAdditionalMinister::query()
            ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->whereNotIn('status', ['cancelled', 'completed', 'in_progress']))
            ->with('minister')->get()
            ->map(fn ($item) => ['person_id' => $item->person_id, 'name' => $item->minister?->display_name]);

        $distribution = $primary->concat($additional)
            ->groupBy('person_id')
            ->map(fn ($items) => ['name' => $items->first()['name'], 'count' => $items->count()])
            ->values()->all();

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'services' => $services->count(),
            'distribution' => $distribution,
        ];
    }
}
