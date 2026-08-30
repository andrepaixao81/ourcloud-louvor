<?php

namespace App\Services;

use App\Models\RecurringServiceRule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonthlyScheduleTemplateService
{
    public function candidates(Carbon $month): Collection
    {
        $services = Service::query()
            ->with(['type', 'congregation', 'location'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->where('status', '!=', 'cancelled')
            ->where('origin', 'manual')
            ->orderBy('starts_at')
            ->get();

        return $services
            ->groupBy(function (Service $service): string {
                return implode('|', [
                    $service->service_type_id,
                    $service->congregation_id ?: '-',
                    $service->location_id ?: '-',
                    $service->starts_at->format('w'),
                    $service->starts_at->format('H:i'),
                    $service->ends_at?->format('H:i') ?: '-',
                    Str::lower(trim($service->name)),
                ]);
            })
            ->map(function (Collection $group): array {
                /** @var Service $sample */
                $sample = $group->first();
                $dates = $group->pluck('starts_at')->map(fn ($date) => $date->format('d/m'))->values();
                $defaultMode = $group->count() >= 3 ? 'weekly' : 'none';
                $weekOfMonth = (int) ceil($sample->starts_at->day / 7);
                if ($sample->starts_at->copy()->addWeek()->month !== $sample->starts_at->month) {
                    $weekOfMonth = -1;
                }

                return [
                    'key' => sha1($group->pluck('id')->join('|')),
                    'sample_id' => $sample->id,
                    'name' => $sample->name,
                    'type' => $sample->type?->name ?? 'Tipo',
                    'weekday' => $sample->starts_at->dayOfWeek,
                    'weekday_label' => $this->weekdayLabel($sample->starts_at->dayOfWeek),
                    'starts_time' => $sample->starts_at->format('H:i'),
                    'ends_time' => $sample->ends_at?->format('H:i'),
                    'dates' => $dates,
                    'count' => $group->count(),
                    'default_mode' => $defaultMode,
                    'default_week_of_month' => $weekOfMonth,
                ];
            })
            ->values();
    }

    public function createRuleFromService(Service $service, string $mode, ?Carbon $endsOn, string $createdBy): ?RecurringServiceRule
    {
        if ($mode === 'none') {
            return null;
        }

        $frequency = 'weekly';
        $weekOfMonth = null;
        if (str_starts_with($mode, 'monthly_nth_')) {
            $frequency = 'monthly_nth';
            $suffix = str_replace('monthly_nth_', '', $mode);
            $weekOfMonth = $suffix === 'last' ? -1 : (int) $suffix;
        }

        $attributes = [
            'organization_id' => $service->organization_id,
            'service_type_id' => $service->service_type_id,
            'congregation_id' => $service->congregation_id,
            'location_id' => $service->location_id,
            'name' => $service->name,
            'frequency' => $frequency,
            'interval' => 1,
            'weekday' => $service->starts_at->dayOfWeek,
            'week_of_month' => $weekOfMonth,
            'day_of_month' => null,
            'starts_on' => $service->starts_at->copy()->startOfDay(),
            'ends_on' => $endsOn,
            'starts_time' => $service->starts_at->format('H:i:s'),
            'ends_time' => $service->ends_at?->format('H:i:s'),
            'timezone' => $service->timezone,
            'active' => true,
            'created_by' => $createdBy,
        ];

        $existing = RecurringServiceRule::query()
            ->where('service_type_id', $attributes['service_type_id'])
            ->where('name', $attributes['name'])
            ->where('frequency', $attributes['frequency'])
            ->where('weekday', $attributes['weekday'])
            ->where('starts_time', $attributes['starts_time'])
            ->where('congregation_id', $attributes['congregation_id'])
            ->where('location_id', $attributes['location_id'])
            ->where('active', true)
            ->when($frequency === 'monthly_nth', fn ($q) => $q->where('week_of_month', $weekOfMonth))
            ->first();

        if ($existing) {
            if ($endsOn && (! $existing->ends_on || $existing->ends_on->lt($endsOn))) {
                $existing->update(['ends_on' => $endsOn]);
            }
            return $existing;
        }

        return RecurringServiceRule::query()->create($attributes);
    }

    /**
     * Evita que uma regra semanal gere um segundo culto no mesmo horário quando
     * o mês-padrão definiu uma ocorrência mensal especial naquele slot.
     *
     * Ex.: Culto de Ensino todo domingo às 09h + Santa Ceia no 2º domingo.
     * A regra semanal passa a excluir a 2ª ocorrência mensal.
     *
     * @param array<int,array{service:Service,mode:string,rule:RecurringServiceRule}> $selections
     */
    public function reconcileWeeklyExclusions(array $selections): void
    {
        $groups = collect($selections)->groupBy(fn (array $selection) => $this->slotKey($selection['service']));

        foreach ($groups as $group) {
            $excludedWeeks = $group
                ->map(fn (array $selection) => $this->modeWeekIndex($selection['mode']))
                ->filter(fn ($week) => $week !== null)
                ->map(fn ($week) => (int) $week)
                ->unique()
                ->values()
                ->all();

            usort($excludedWeeks, static function (int $a, int $b): int {
                if ($a === -1) return 1;
                if ($b === -1) return -1;
                return $a <=> $b;
            });

            foreach ($group as $selection) {
                if ($selection['mode'] !== 'weekly') {
                    continue;
                }

                $selection['rule']->update([
                    'excluded_weeks_of_month' => $excludedWeeks,
                ]);
            }
        }
    }

    public function weekdayLabel(int $weekday): string
    {
        return ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'][$weekday] ?? 'dia';
    }

    private function slotKey(Service $service): string
    {
        return implode('|', [
            $service->organization_id,
            $service->congregation_id ?: '-',
            $service->location_id ?: '-',
            $service->starts_at->dayOfWeek,
            $service->starts_at->format('H:i'),
            $service->ends_at?->format('H:i') ?: '-',
        ]);
    }

    private function modeWeekIndex(string $mode): ?int
    {
        if (! str_starts_with($mode, 'monthly_nth_')) {
            return null;
        }

        $suffix = str_replace('monthly_nth_', '', $mode);
        return $suffix === 'last' ? -1 : (int) $suffix;
    }
}
