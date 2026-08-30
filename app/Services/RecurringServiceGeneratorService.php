<?php

namespace App\Services;

use App\Models\RecurringServiceRule;
use App\Models\RecurrenceException;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class RecurringServiceGeneratorService
{
    /** @var array<string,bool> */
    private array $specialOverrideCache = [];

    public function __construct(private InternalCalendarService $calendar) {}

    /**
     * Materializa o mês e, antes disso, reconcilia tudo que já existe.
     * Isso é usado pela geração/publicação da escala para que uma ocorrência
     * excluída nunca sobreviva apenas porque foi materializada por uma versão
     * antiga da regra.
     *
     * @return array{generated:int,checked:int,cancelled:int}
     */
    public function synchronizeMonth(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $reconcile = $this->reconcileOrganizationMaterialized(
            (string) auth()->user()?->organization_id,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );
        $generated = $this->generateMonth($month);

        return [
            'generated' => $generated,
            'checked' => $reconcile['checked'],
            'cancelled' => $reconcile['cancelled'],
        ];
    }

    public function generateMonth(Carbon $month): int
    {
        $this->specialOverrideCache = [];
        $count = 0;
        $rules = RecurringServiceRule::query()->where('active', true)
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth())
            ->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',$month->copy()->startOfMonth()))
            ->get();

        foreach ($rules as $rule) {
            foreach ($this->datesForRule($rule, $month) as $date) {
                $starts = Carbon::parse($date->format('Y-m-d').' '.$rule->starts_time, $rule->timezone);
                if ($starts->lt(Carbon::parse($rule->starts_on, $rule->timezone)->startOfDay())) continue;
                if ($rule->ends_on && $starts->gt(Carbon::parse($rule->ends_on, $rule->timezone)->endOfDay())) continue;
                $key = $starts->format('Y-m-d\TH:i:s').'@'.$rule->timezone;
                $exception = RecurrenceException::query()->where('recurring_rule_id',$rule->id)->where('occurrence_key',$key)->first();
                if ($exception?->exception_type === 'skipped') continue;

                $actualStart = $exception?->new_starts_at ?: $starts;
                $ends = $rule->ends_time ? Carbon::parse($actualStart->format('Y-m-d').' '.$rule->ends_time, $rule->timezone) : $actualStart->copy()->addHours(2);
                if ($ends->lte($actualStart)) $ends->addDay();
                $actualEnd = $exception?->new_ends_at ?: $ends;

                $actualServiceTypeId = $exception?->service_type_id ?: $rule->service_type_id;
                $actualCongregationId = $exception?->congregation_id ?? $rule->congregation_id;
                $actualLocationId = $exception?->location_id ?? $rule->location_id;
                $actualName = filled($exception?->name) ? $exception->name : $rule->name;
                $actualDescription = $exception?->description;
                $actualStatus = $exception?->status ?: ($exception?->exception_type === 'cancelled' ? 'cancelled' : 'scheduled');
                $actualNotes = $exception?->notes;

                $manualExisting = Service::query()->withoutGlobalScopes()
                    ->where('organization_id', $rule->organization_id)
                    ->where('service_type_id', $rule->service_type_id)
                    ->where('starts_at', $actualStart)
                    ->whereNull('deleted_at')
                    ->whereNull('recurring_rule_id')
                    ->first();

                if ($manualExisting) {
                    continue;
                }

                $service = DB::transaction(function () use ($rule,$key,$actualStart,$actualEnd,$actualServiceTypeId,$actualCongregationId,$actualLocationId,$actualName,$actualDescription,$actualStatus,$actualNotes) {
                    return Service::query()->withoutGlobalScopes()->updateOrCreate(
                        ['recurring_rule_id'=>$rule->id,'recurrence_occurrence_key'=>$key],
                        [
                            'organization_id'=>$rule->organization_id,
                            'service_type_id'=>$actualServiceTypeId,
                            'congregation_id'=>$actualCongregationId,
                            'location_id'=>$actualLocationId,
                            'name'=>$actualName,
                            'description'=>$actualDescription,
                            'starts_at'=>$actualStart,
                            'ends_at'=>$actualEnd,
                            'timezone'=>$rule->timezone,
                            'status'=>$actualStatus,
                            'notes'=>$actualNotes,
                            'origin'=>'recurrence',
                            'created_by'=>$rule->created_by,
                        ]
                    );
                });
                $this->calendar->syncService($service);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Reaplica uma regra sobre ocorrências futuras já materializadas.
     *
     * @return array{checked:int,cancelled:int}
     */
    public function reconcileRule(RecurringServiceRule $rule, Carbon $from, ?Carbon $until = null): array
    {
        $query = Service::query()->withoutGlobalScopes()
            ->with(['assignment', 'additionalMinisters', 'recurringRule', 'execution'])
            ->where('recurring_rule_id', $rule->id)
            ->where('starts_at', '>=', $from)
            ->whereNull('deleted_at');
        if ($until) {
            $query->where('starts_at', '<=', $until);
        }

        return $this->reconcileServices($query->get());
    }

    /**
     * Revalida TODAS as ocorrências recorrentes materializadas da organização,
     * inclusive as ligadas a regras antigas/desativadas. Isso corrige cenários
     * em que o mês já havia sido materializado antes de uma exclusão ser criada.
     *
     * @return array{checked:int,cancelled:int}
     */
    public function reconcileOrganizationMaterialized(string $organizationId, Carbon $from, ?Carbon $until = null): array
    {
        if ($organizationId === '') {
            return ['checked' => 0, 'cancelled' => 0];
        }

        $query = Service::query()->withoutGlobalScopes()
            ->with(['assignment', 'additionalMinisters', 'recurringRule', 'execution'])
            ->where('organization_id', $organizationId)
            ->where(function ($q): void {
                $q->whereNotNull('recurring_rule_id')->orWhere('origin', 'recurrence');
            })
            ->where('starts_at', '>=', $from)
            ->whereNull('deleted_at');
        if ($until) {
            $query->where('starts_at', '<=', $until);
        }

        return $this->reconcileServices($query->get());
    }

    /** @param \Illuminate\Support\Collection<int,Service> $services */
    private function reconcileServices($services): array
    {
        $this->specialOverrideCache = [];
        $checked = 0;
        $cancelled = 0;

        foreach ($services as $service) {
            $checked++;
            $rule = $service->recurringRule;
            $allowed = $rule
                && ! $rule->trashed()
                && (bool) $rule->active
                && $this->serviceOccurrenceAllowed($service, $rule);

            if ($allowed) {
                continue;
            }

            // Histórico/executado não deve ser apagado por uma mudança posterior de regra.
            if (in_array($service->status, ['completed', 'in_progress'], true) || $service->execution?->status === 'finished') {
                continue;
            }

            $changed = $service->status !== 'cancelled'
                || ($service->assignment && $service->assignment->status !== 'cancelled')
                || $service->additionalMinisters->contains(fn ($item) => $item->status !== 'cancelled');

            DB::transaction(function () use ($service): void {
                if ($service->assignment) {
                    $service->assignment->update(['status' => 'cancelled']);
                }
                $service->additionalMinisters()->update(['status' => 'cancelled']);
                $service->update(['status' => 'cancelled']);
            });
            $this->calendar->syncService($service->fresh());
            if ($changed) $cancelled++;
        }

        return ['checked' => $checked, 'cancelled' => $cancelled];
    }

    private function serviceOccurrenceAllowed(Service $service, RecurringServiceRule $rule): bool
    {
        $original = $this->originalOccurrenceDate($service, $rule);
        $allowed = collect($this->datesForRule($rule, $original->copy()->startOfMonth()))
            ->contains(fn (Carbon $date) => $date->format('Y-m-d') === $original->format('Y-m-d'));

        return $allowed;
    }

    private function originalOccurrenceDate(Service $service, RecurringServiceRule $rule): Carbon
    {
        $key = (string) $service->recurrence_occurrence_key;
        if ($key !== '') {
            $raw = str_contains($key, '@') ? strstr($key, '@', true) : $key;
            try {
                return Carbon::createFromFormat('Y-m-d\TH:i:s', $raw, $rule->timezone ?: $service->timezone ?: config('app.timezone'));
            } catch (\Throwable) {
                // cai para starts_at
            }
        }

        return $service->starts_at->copy()->timezone($rule->timezone ?: $service->timezone ?: config('app.timezone'));
    }

    public function expandDays(int $days = 90): int
    {
        $months = collect();
        $cursor = now()->startOfMonth();
        $end = now()->addDays($days)->endOfMonth();
        while ($cursor->lte($end)) { $months->push($cursor->copy()); $cursor->addMonth(); }
        return $months->sum(fn($month) => $this->generateMonth($month));
    }

    private function datesForRule(RecurringServiceRule $rule, Carbon $month): array
    {
        $dates = $this->rawDatesForRule($rule, $month);
        if ($rule->frequency !== 'weekly') {
            return $dates;
        }

        // Uma ocorrência especial mensal no mesmo local/horário tem precedência
        // sobre a regra semanal. Assim Santa Ceia no 2º domingo substitui o culto
        // semanal mesmo se existir uma ocorrência antiga materializada ou uma regra
        // semanal duplicada de versões anteriores.
        return array_values(array_filter(
            $dates,
            fn (Carbon $candidate) => ! $this->hasSpecialOverrideForDate($rule, $candidate)
        ));
    }

    private function rawDatesForRule(RecurringServiceRule $rule, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $dates = [];
        if ($rule->frequency === 'weekly') {
            $weeklyCandidates = [];
            foreach (CarbonPeriod::create($start, $end) as $day) {
                if ($day->dayOfWeek === (int) $rule->weekday) {
                    $weeklyCandidates[] = $day->copy();
                }
            }

            $excludedWeeks = array_map('intval', $rule->excluded_weeks_of_month ?? []);
            foreach ($weeklyCandidates as $index => $candidate) {
                $weekNumber = $index + 1;
                $isLast = $index === array_key_last($weeklyCandidates);
                if (in_array($weekNumber, $excludedWeeks, true)) {
                    continue;
                }
                if ($isLast && in_array(-1, $excludedWeeks, true)) {
                    continue;
                }
                $dates[] = $candidate;
            }
        } elseif ($rule->frequency === 'monthly_date' && $rule->day_of_month) {
            $day = min((int)$rule->day_of_month, $end->day);
            $dates[] = $start->copy()->day($day);
        } elseif ($rule->frequency === 'monthly_nth' && $rule->weekday !== null && $rule->week_of_month) {
            $candidates = [];
            foreach (CarbonPeriod::create($start, $end) as $day) if ($day->dayOfWeek === (int)$rule->weekday) $candidates[] = $day->copy();
            if ((int)$rule->week_of_month === -1 && $candidates) $dates[] = end($candidates);
            elseif (isset($candidates[(int)$rule->week_of_month - 1])) $dates[] = $candidates[(int)$rule->week_of_month - 1];
        }
        return $dates;
    }

    private function hasSpecialOverrideForDate(RecurringServiceRule $weeklyRule, Carbon $candidate): bool
    {
        $cacheKey = implode('|', [
            $weeklyRule->organization_id,
            $weeklyRule->congregation_id ?: '-',
            $weeklyRule->location_id ?: '-',
            substr((string) $weeklyRule->starts_time, 0, 5),
            $weeklyRule->timezone,
            $candidate->format('Y-m-d'),
        ]);
        if (array_key_exists($cacheKey, $this->specialOverrideCache)) {
            return $this->specialOverrideCache[$cacheKey];
        }

        $specialRules = RecurringServiceRule::query()
            ->where('organization_id', $weeklyRule->organization_id)
            ->where('active', true)
            ->whereIn('frequency', ['monthly_nth', 'monthly_date'])
            ->where('starts_time', $weeklyRule->starts_time)
            ->where('timezone', $weeklyRule->timezone)
            ->whereDate('starts_on', '<=', $candidate->copy()->endOfDay())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $candidate->copy()->startOfDay()))
            ->get();

        foreach ($specialRules as $specialRule) {
            $sameCongregation = ! $weeklyRule->congregation_id || ! $specialRule->congregation_id || $weeklyRule->congregation_id === $specialRule->congregation_id;
            $sameLocation = ! $weeklyRule->location_id || ! $specialRule->location_id || $weeklyRule->location_id === $specialRule->location_id;
            if (! $sameCongregation || ! $sameLocation) {
                continue;
            }

            $matches = collect($this->rawDatesForRule($specialRule, $candidate->copy()->startOfMonth()))
                ->contains(fn (Carbon $date) => $date->format('Y-m-d') === $candidate->format('Y-m-d'));
            if ($matches) {
                return $this->specialOverrideCache[$cacheKey] = true;
            }
        }

        return $this->specialOverrideCache[$cacheKey] = false;
    }
}
