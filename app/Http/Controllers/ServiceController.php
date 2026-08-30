<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\CalendarEntry;
use App\Models\Congregation;
use App\Models\Location;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\RecurringServiceRule;
use App\Models\RecurrenceException;
use App\Models\Service;
use App\Models\ServiceType;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\InternalCalendarService;
use App\Services\MonthlyScheduleTemplateService;
use App\Services\RecurringServiceGeneratorService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $monthValue = $request->input('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $state = $request->input('state', 'active');
        $view = $request->input('view', 'calendar');
        $calendarStart = $month->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->endOfDay();

        $query = Service::query()
            ->with(['type' => fn ($q) => $q->withTrashed(), 'congregation', 'location', 'assignment.minister'])
            ->whereBetween('starts_at', [$calendarStart, $calendarEnd])
            ->when($request->user()->isMemberWithoutMinistryAccess(), fn ($q) => $q->whereNull('special_event_id'))
            ->orderBy('starts_at');

        if ($state === 'all') {
            $query->withTrashed();
        } elseif ($state === 'deleted') {
            $query->onlyTrashed();
        }

        $services = $query->get();
        $servicesByDate = $services->groupBy(fn (Service $service) => $service->starts_at->format('Y-m-d'));
        $calendarDays = collect(CarbonPeriod::create($calendarStart, $calendarEnd))->map(fn ($day) => Carbon::instance($day));

        return view('services.index', [
            'services' => $services,
            'servicesByDate' => $servicesByDate,
            'calendarDays' => $calendarDays,
            'month' => $month,
            'state' => $state,
            'view' => $view,
            'types' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedDate = $request->input('date');
        if (! is_string($selectedDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = now()->toDateString();
        }

        return view('services.form', [
            'service' => new Service(),
            'selectedDate' => $selectedDate,
            'types' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ServiceRequest $request, InternalCalendarService $calendar)
    {
        $data = $this->normalizedServiceData($request);
        $service = Service::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'origin' => 'manual',
            'created_by' => $request->user()->id,
        ]);
        $calendar->syncService($service);

        if ($request->input('return_to') === 'calendar') {
            return redirect()->route('services.index', ['month' => $service->starts_at->format('Y-m')])->with('status', 'Culto adicionado à agenda.');
        }

        return redirect()->route('services.show', $service)->with('status', 'Culto cadastrado.');
    }

    public function storeMonthBatch(Request $request, InternalCalendarService $calendar, AuditService $audit)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'service_type_id' => ['required', 'uuid'],
            'congregation_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'name' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $type = ServiceType::query()->where('active', true)->findOrFail($data['service_type_id']);
        if (! empty($data['congregation_id'])) Congregation::query()->findOrFail($data['congregation_id']);
        if (! empty($data['location_id'])) Location::query()->findOrFail($data['location_id']);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $timezone = $request->user()->organization?->timezone ?: config('app.timezone');
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $request, $calendar, $audit, $month, $timezone, $type, &$created, &$skipped): void {
            foreach (CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()) as $date) {
                $day = Carbon::instance($date);
                if ($day->dayOfWeek !== (int) $data['weekday']) continue;

                $startsAt = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$data['starts_time'], $timezone);
                $exists = Service::query()->where('service_type_id', $type->id)->where('starts_at', $startsAt)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $endsAt = null;
                if (! empty($data['ends_time'])) {
                    $endsAt = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$data['ends_time'], $timezone);
                    if ($endsAt->lte($startsAt)) $endsAt->addDay();
                }

                $service = Service::query()->create([
                    'organization_id' => $request->user()->organization_id,
                    'service_type_id' => $type->id,
                    'congregation_id' => $data['congregation_id'] ?? null,
                    'location_id' => $data['location_id'] ?? null,
                    'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : $type->name,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'timezone' => $timezone,
                    'status' => 'scheduled',
                    'origin' => 'manual',
                    'notes' => $data['notes'] ?? null,
                    'minister_slots' => 1,
                    'created_by' => $request->user()->id,
                ]);
                $calendar->syncService($service);
                $created++;
            }

            $audit->event('services.month_batch.created', null, null, [
                'month' => $data['month'],
                'weekday' => (int) $data['weekday'],
                'service_type_id' => $type->id,
                'created' => $created,
                'skipped' => $skipped,
            ]);
        });

        return redirect()->route('services.index', ['month' => $month->format('Y-m')])
            ->with('status', "Agenda do mês atualizada: {$created} culto(s) criados e {$skipped} duplicidade(s) ignorada(s). Depois, use “Validar mês como padrão” se quiser repetir nos próximos meses.");
    }

    public function show(Request $request, Service $service)
    {
        abort_if($service->special_event_id && $request->user()->isMemberWithoutMinistryAccess(), 403);
        $service->load(['type' => fn ($q) => $q->withTrashed(), 'congregation', 'location', 'assignment.minister', 'additionalMinisters.minister', 'repertoire.openingSong.versions', 'repertoire.openingSongVersion', 'repertoire.openingMinister', 'repertoire.items.song.versions', 'repertoire.items.songVersion', 'repertoire.items.minister', 'execution']);

        $returnTo = $request->string('return_to')->toString();
        $backUrl = $this->safeReturnUrl($returnTo, $service);

        return view('services.show', compact('service', 'backUrl'));
    }

    public function edit(Service $service)
    {
        $service->loadMissing([
            'type' => fn ($query) => $query->withTrashed(),
            'congregation',
            'location',
            'recurringRule' => fn ($query) => $query->withTrashed(),
            'assignment.minister',
            'additionalMinisters.minister',
        ]);

        // Exibe somente tipos ativos, mas preserva o tipo atual caso ele tenha sido
        // inativado depois da criação do culto. A consulta explícita evita depender
        // de combinações ambíguas entre SoftDeletes e orWhereKey no formulário.
        $types = ServiceType::query()
            ->withTrashed()
            ->where(function ($query) use ($service): void {
                $query->where(function ($active): void {
                    $active->where('active', true)->whereNull('deleted_at');
                })->orWhere('id', $service->service_type_id);
            })
            ->orderBy('name')
            ->get();

        $currentTypeName = trim((string) ($service->type?->name ?? ''));
        $storedName = trim((string) $service->name);
        $customTitle = ($storedName !== '' && $currentTypeName !== '' && strcasecmp($storedName, $currentTypeName) !== 0)
            ? $storedName
            : '';

        return view('services.form', [
            'service' => $service,
            'selectedDate' => $service->starts_at->format('Y-m-d'),
            'types' => $types,
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
            'customTitle' => $customTitle,
            'isRecurringOccurrence' => filled($service->recurring_rule_id),
            'manualMinisters' => Person::query()->where('status', 'active')->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))->orderBy('display_name')->get(),
            'selectedManualMinisterId' => $service->assignment?->assignment_mode === 'manual' ? $service->assignment?->minister_person_id : null,
        ]);
    }

    public function update(ServiceRequest $request, Service $service, InternalCalendarService $calendar, AuditService $audit, AvailabilityService $availability)
    {
        $before = $service->only([
            'service_type_id', 'congregation_id', 'location_id', 'name', 'description',
            'starts_at', 'ends_at', 'timezone', 'status', 'notes',
        ]);
        $data = $this->normalizedServiceData($request, $service);

        DB::transaction(function () use ($request, $service, $calendar, $audit, $availability, $before, $data): void {
            $service->update($data + ['updated_by' => $request->user()->id]);

            // Se esta ocorrência veio de uma regra recorrente, a edição deve valer
            // apenas para este culto. Registramos a exceção para impedir que uma
            // futura materialização da regra restaure o tipo/horário originais.
            if ($service->recurring_rule_id && $service->recurrence_occurrence_key) {
                RecurrenceException::query()->updateOrCreate(
                    [
                        'recurring_rule_id' => $service->recurring_rule_id,
                        'occurrence_key' => $service->recurrence_occurrence_key,
                    ],
                    [
                        'organization_id' => $service->organization_id,
                        'exception_type' => 'modified',
                        'original_starts_at' => $this->originalOccurrenceStart($service),
                        'new_starts_at' => $service->starts_at,
                        'new_ends_at' => $service->ends_at,
                        'service_type_id' => $service->service_type_id,
                        'congregation_id' => $service->congregation_id,
                        'location_id' => $service->location_id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'status' => $service->status,
                        'notes' => $service->notes,
                        'reason' => 'Alteração individual realizada no calendário.',
                        'created_by' => $request->user()->id,
                    ]
                );
            }

            if ($request->user()->hasPermission('schedule.manual_assign') && $request->exists('manual_minister_person_id')) {
                $this->syncManualMinisterFromEdit($request, $service->fresh(['assignment']), $availability, $audit);
            }

            $calendar->syncService($service);
            $audit->event('service.single_occurrence.updated', $service, null, [
                'scope' => 'single_occurrence',
                'before' => $before,
                'after' => $service->only(array_keys($before)),
            ]);
        });

        return redirect()->route('services.show', $service)->with('status', 'Culto atualizado. A alteração foi aplicada somente a esta ocorrência.');
    }

    public function cancel(Request $request, Service $service, InternalCalendarService $calendar, AuditService $audit)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $oldStatus = $service->status;
        $service->update([
            'status' => 'cancelled',
            'notes' => trim(($service->notes ? $service->notes."\n" : '').'Cancelamento: '.$data['reason']),
            'updated_by' => $request->user()->id,
        ]);
        $calendar->syncService($service);
        $audit->event('service.cancelled', $service, $data['reason'], ['old_status' => $oldStatus]);

        return back()->with('status', 'Culto cancelado.');
    }

    public function destroy(Request $request, Service $service, InternalCalendarService $calendar, AuditService $audit)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:2000']);
        $blocking = $this->blockingHistoryDependencies($service);

        if ($blocking !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Este culto já possui histórico efetivo ('.implode(', ', $blocking).') e não pode ser excluído. Use “Cancelar culto” para preservar a rastreabilidade.',
            ]);
        }

        DB::transaction(function () use ($service, $request, $calendar, $audit, $data): void {
            // Artefatos ainda em rascunho podem ser descartados junto com um culto
            // de teste/cadastro equivocado. Dados publicados ou executados são
            // bloqueados antes deste ponto.
            $assignments = $service->assignment()->withTrashed()->get();
            foreach ($assignments as $assignment) {
                $assignment->forceDelete();
            }

            // RC44: um culto de teste pode possuir mais de uma versão arquivada
            // de repertório depois de revogações. Ao excluir o próprio culto (quando
            // não há histórico efetivo), removemos TODAS as versões para que nenhum
            // artefato planejado sobreviva ao cadastro excluído.
            $service->repertoire()->withTrashed()->get()->each(fn ($repertoire) => $repertoire->forceDelete());
            $service->additionalMinisters()->withTrashed()->get()->each(fn ($minister) => $minister->forceDelete());
            \App\Models\SongRequest::query()
                ->where('requested_for_service_id', $service->id)
                ->update(['requested_for_service_id' => null]);

            $execution = $service->execution()->with(['songs', 'participants', 'ministers'])->first();
            if ($execution && $execution->songs->isEmpty() && $execution->participants->isEmpty() && $execution->ministers->isEmpty()) {
                $execution->delete();
            }

            $audit->event('service.deleted', $service, $data['reason'], [
                'starts_at' => $service->starts_at?->toIso8601String(),
                'name' => $service->name,
                'draft_dependencies_removed' => true,
            ]);

            $calendar->deleteServiceProjection($service, $request->user()->id);
            $service->deleteQuietly();
        });

        return redirect()->route('services.index')->with('status', 'Culto de rascunho/teste excluído. Artefatos não publicados associados também foram removidos; o registro do culto permanece restaurável.');
    }

    public function resetTestData(Request $request, Service $service, InternalCalendarService $calendar, AuditService $audit)
    {
        abort_unless($request->user()->isAdministrator() || $request->user()->isMinistryManager(), 403, 'Somente Administrador ou Responsável pelo Ministério pode zerar dados de teste de um culto.');
        abort_if($service->trashed(), 404);

        $data = $request->validateWithBag('resetTest', [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'confirmation' => ['required', Rule::in(['ZERAR'])],
            'acknowledge' => ['accepted'],
        ], [
            'confirmation.in' => 'Digite ZERAR exatamente para confirmar a limpeza dos dados de teste.',
            'acknowledge.accepted' => 'Confirme que entende que escala, repertório e histórico ministrado deste culto serão removidos.',
        ]);

        $summary = DB::transaction(function () use ($service, $request, $calendar, $audit, $data): array {
            $assignmentCount = ScheduleAssignment::query()->withTrashed()->where('service_id', $service->id)->count();
            $additionalCount = \App\Models\ServiceAdditionalMinister::query()->withTrashed()->where('service_id', $service->id)->count();
            $repertoires = \App\Models\Repertoire::query()->withTrashed()->where('service_id', $service->id)->get();
            $repertoireCount = $repertoires->count();
            $repertoireItemCount = \App\Models\RepertoireItem::query()->withTrashed()->whereIn('repertoire_id', $repertoires->pluck('id'))->count();

            $execution = \App\Models\ServiceExecution::query()->where('service_id', $service->id)->first();
            $songExecutionCount = $execution
                ? \App\Models\SongExecution::query()->withTrashed()->where('service_execution_id', $execution->id)->count()
                : 0;
            $participantCount = $execution
                ? \App\Models\ServiceParticipant::query()->withTrashed()->where('service_execution_id', $execution->id)->count()
                : 0;
            $executionMinisterCount = $execution
                ? \App\Models\ServiceExecutionMinister::query()->withTrashed()->where('service_execution_id', $execution->id)->count()
                : 0;

            // O registro de auditoria permanece, mas os fatos operacionais de teste
            // deixam de participar de relatórios, rodízio e histórico musical. A
            // execução é removida fisicamente para eliminar inclusive SongExecution
            // soft-deletado que pudesse reaparecer em consultas administrativas.
            if ($execution) {
                $execution->delete();
            }

            foreach ($repertoires as $repertoire) {
                $repertoire->forceDelete();
            }

            \App\Models\ServiceAdditionalMinister::query()
                ->withTrashed()
                ->where('service_id', $service->id)
                ->get()
                ->each(fn ($item) => $item->forceDelete());

            ScheduleAssignment::query()
                ->withTrashed()
                ->where('service_id', $service->id)
                ->get()
                ->each(fn ($assignment) => $assignment->forceDelete());

            \App\Models\SongRequest::query()
                ->where('requested_for_service_id', $service->id)
                ->update(['requested_for_service_id' => null]);

            // Estados de assinatura antigos deste teste não devem manter referência
            // operacional. A próxima publicação recria o estado a partir da escala nova.
            \App\Models\CalendarFeedState::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $service->organization_id)
                ->where('service_id', $service->id)
                ->delete();

            $oldStatus = $service->status;
            $service->forceFill([
                'status' => 'scheduled',
                'updated_by' => $request->user()->id,
            ])->saveQuietly();

            $calendar->deleteServiceProjection($service, $request->user()->id);

            $summary = [
                'old_status' => $oldStatus,
                'assignments_removed' => $assignmentCount,
                'additional_ministers_removed' => $additionalCount,
                'repertoires_removed' => $repertoireCount,
                'repertoire_items_removed' => $repertoireItemCount,
                'song_executions_removed' => $songExecutionCount,
                'participants_removed' => $participantCount,
                'execution_ministers_removed' => $executionMinisterCount,
                'service_status' => 'scheduled',
            ];

            $audit->event('service.test_data.reset', $service, $data['reason'], $summary);

            return $summary;
        });

        $message = 'Dados de teste zerados. O culto voltou para Agendado e poderá entrar novamente na geração da escala.';
        if (($summary['song_executions_removed'] ?? 0) > 0) {
            $message .= ' '.($summary['song_executions_removed']).' registro(s) de louvor ministrado foram removidos do histórico.';
        }

        return redirect()->route('services.show', $service)->with('status', $message);
    }

    public function restore(Request $request, string $service, InternalCalendarService $calendar, AuditService $audit)
    {
        $model = Service::query()->withTrashed()->findOrFail($service);
        abort_unless($model->trashed(), 404);

        DB::transaction(function () use ($model, $request, $calendar, $audit): void {
            $model->restoreQuietly();
            $model->forceFill(['updated_by' => $request->user()->id])->saveQuietly();
            $calendar->syncService($model);
            $audit->event('service.restored', $model, null, ['name' => $model->name]);
        });

        return redirect()->route('services.show', $model)->with('status', 'Culto restaurado.');
    }

    public function monthTemplate(Request $request, MonthlyScheduleTemplateService $template)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('Y-m', $data['month'] ?? now()->format('Y-m'))->startOfMonth();

        return view('services.month-template', [
            'month' => $month,
            'candidates' => $template->candidates($month),
        ]);
    }

    public function applyMonthTemplate(Request $request, MonthlyScheduleTemplateService $template, RecurringServiceGeneratorService $generator, AuditService $audit)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'horizon' => ['required', Rule::in(['3', '6', '12', 'none'])],
            'patterns' => ['required', 'array'],
            'patterns.*.service_id' => ['required', 'uuid'],
            'patterns.*.mode' => ['required', Rule::in(['none', 'weekly', 'monthly_nth_1', 'monthly_nth_2', 'monthly_nth_3', 'monthly_nth_4', 'monthly_nth_5', 'monthly_nth_last'])],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $endsOn = $data['horizon'] === 'none' ? null : $month->copy()->addMonths((int) $data['horizon'])->endOfMonth();
        $rulesCreated = 0;

        DB::transaction(function () use ($data, $request, $template, $endsOn, &$rulesCreated): void {
            $selections = [];

            foreach ($data['patterns'] as $pattern) {
                if ($pattern['mode'] === 'none') {
                    continue;
                }

                $service = Service::query()->findOrFail($pattern['service_id']);
                $rule = $template->createRuleFromService($service, $pattern['mode'], $endsOn, (string) $request->user()->id);
                if ($rule) {
                    $rulesCreated++;
                    $selections[] = [
                        'service' => $service,
                        'mode' => $pattern['mode'],
                        'rule' => $rule,
                    ];
                }
            }

            // Quando existe um padrão semanal e uma ocorrência mensal especial no
            // mesmo dia/horário (ex.: Culto de Ensino + Santa Ceia no 2º domingo),
            // a semana especial é excluída da regra semanal para evitar dois cultos
            // simultâneos nos meses seguintes.
            $template->reconcileWeeklyExclusions($selections);
        });

        $monthsToGenerate = $data['horizon'] === 'none' ? 3 : (int) $data['horizon'];
        $occurrences = 0;
        for ($i = 1; $i <= $monthsToGenerate; $i++) {
            $occurrences += $generator->generateMonth($month->copy()->addMonths($i));
        }

        $audit->event('services.month_template.applied', null, null, [
            'month' => $data['month'],
            'horizon' => $data['horizon'],
            'rules_processed' => $rulesCreated,
            'occurrences_processed' => $occurrences,
        ]);

        return redirect()->route('services.index', ['month' => $month->copy()->addMonth()->format('Y-m')])
            ->with('status', "Padrão validado. Regras processadas: {$rulesCreated}. Ocorrências futuras processadas: {$occurrences}.");
    }

    public function recurringRules(Request $request)
    {
        $state = $request->input('state', 'active');
        $query = RecurringServiceRule::query()->with(['type' => fn ($q) => $q->withTrashed(), 'congregation', 'location'])->orderBy('name');
        if ($state === 'all') {
            $query->withTrashed();
        } elseif ($state === 'deleted') {
            $query->onlyTrashed();
        }

        return view('services.recurring', [
            'rules' => $query->get(),
            'state' => $state,
            'types' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeRecurringRule(Request $request, RecurringServiceGeneratorService $generator)
    {
        $data = $this->validateRecurringRule($request);
        RecurringServiceRule::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            'interval' => 1,
            'active' => true,
        ]);

        // Uma regra especial nova (ex.: Santa Ceia no 2º domingo) pode invalidar
        // ocorrências semanais já materializadas. Reconciliamos toda a organização
        // para retirar imediatamente essas ocorrências das próximas escalas.
        $reconcile = $generator->reconcileOrganizationMaterialized(
            (string) $request->user()->organization_id,
            now()->startOfDay()
        );

        $message = 'Regra de agenda cadastrada.';
        if (($reconcile['cancelled'] ?? 0) > 0) {
            $message .= ' '.($reconcile['cancelled']).' ocorrência(s) futura(s) incompatível(is) foram retiradas da escala.';
        }
        return back()->with('status', $message);
    }

    public function updateRecurringRule(Request $request, RecurringServiceRule $rule, AuditService $audit, RecurringServiceGeneratorService $generator)
    {
        $data = $this->validateRecurringRule($request);
        $old = $rule->only(['name', 'frequency', 'weekday', 'week_of_month', 'excluded_weeks_of_month', 'day_of_month', 'starts_time', 'ends_time', 'starts_on', 'ends_on', 'active']);
        $data['active'] = $request->boolean('active');
        $rule->update($data);
        $reconcile = $generator->reconcileOrganizationMaterialized(
            (string) $request->user()->organization_id,
            now()->startOfDay()
        );
        $audit->event('recurring_rule.updated', $rule, null, ['old' => $old, 'new' => $data, 'reconcile' => $reconcile]);

        $message = 'Regra atualizada.';
        if (($reconcile['cancelled'] ?? 0) > 0) {
            $message .= ' '.($reconcile['cancelled']).' ocorrência(s) futura(s) que deixaram de se aplicar foram canceladas para não aparecerem na escala.';
        }
        return back()->with('status', $message);
    }

    public function destroyRecurringRule(Request $request, RecurringServiceRule $rule, AuditService $audit, RecurringServiceGeneratorService $generator)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $rule->update(['active' => false]);
        $rule->delete();
        $audit->event('recurring_rule.deleted', $rule, $data['reason']);
        $reconcile = $generator->reconcileOrganizationMaterialized(
            (string) $request->user()->organization_id,
            now()->startOfDay()
        );

        return back()->with('status', 'Regra removida. Ocorrências futuras da regra foram retiradas da escala; histórico passado foi preservado. '.(($reconcile['cancelled'] ?? 0) > 0 ? $reconcile['cancelled'].' ocorrência(s) reconciliada(s).' : ''));
    }

    public function restoreRecurringRule(Request $request, string $rule, AuditService $audit, RecurringServiceGeneratorService $generator)
    {
        $model = RecurringServiceRule::query()->withTrashed()->findOrFail($rule);
        $model->restore();
        $model->update(['active' => true]);
        $audit->event('recurring_rule.restored', $model);
        $generator->reconcileOrganizationMaterialized((string) $request->user()->organization_id, now()->startOfDay());

        return back()->with('status', 'Regra restaurada e ocorrências futuras reconciliadas.');
    }

    public function generateMonth(Request $request, RecurringServiceGeneratorService $generator)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $result = $generator->synchronizeMonth(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth());

        return back()->with('status', "Ocorrências processadas: {$result['generated']}. Ocorrências antigas incompatíveis retiradas: {$result['cancelled']}.");
    }

    private function safeReturnUrl(string $returnTo, Service $service): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo !== '' && str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return $returnTo;
        }

        return route('services.index', ['month' => $service->starts_at->format('Y-m')]);
    }

    private function normalizedServiceData(ServiceRequest $request, ?Service $existing = null): array
    {
        $data = $request->validated();
        $type = ServiceType::query()->findOrFail($data['service_type_id']);
        $timezone = $request->user()->organization?->timezone ?: config('app.timezone');
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['service_date'].' '.$data['starts_time'], $timezone);
        $endsAt = null;
        if (! empty($data['ends_time'])) {
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $data['service_date'].' '.$data['ends_time'], $timezone);
            if ($endsAt->lte($startsAt)) {
                $endsAt->addDay();
            }
        }

        return [
            'service_type_id' => $data['service_type_id'],
            'congregation_id' => $data['congregation_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : $type->name,
            'description' => $data['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $timezone,
            'status' => $data['status'] ?? $existing?->status ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
            'minister_slots' => (int) ($data['minister_slots'] ?? $existing?->minister_slots ?? 1),
        ];
    }

    private function originalOccurrenceStart(Service $service): Carbon
    {
        $key = (string) $service->recurrence_occurrence_key;
        $raw = str_contains($key, '@') ? strstr($key, '@', true) : $key;

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i:s', $raw, $service->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            return $service->getOriginal('starts_at')
                ? Carbon::parse($service->getOriginal('starts_at'), $service->timezone ?: config('app.timezone'))
                : $service->starts_at->copy();
        }
    }

    private function validateRecurringRule(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'service_type_id' => 'required|uuid',
            'congregation_id' => 'nullable|uuid',
            'location_id' => 'nullable|uuid',
            'frequency' => 'required|in:weekly,monthly_nth,monthly_date',
            'weekday' => 'nullable|integer|min:0|max:6',
            'week_of_month' => 'nullable|integer|min:-1|max:5',
            'excluded_weeks_of_month' => 'nullable|array',
            'excluded_weeks_of_month.*' => 'integer|in:-1,1,2,3,4,5',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'starts_time' => 'required|date_format:H:i',
            'ends_time' => 'nullable|date_format:H:i',
            'timezone' => 'required|timezone',
            'active' => 'nullable|boolean',
        ]);

        ServiceType::query()->where('active', true)->orWhereKey($data['service_type_id'])->findOrFail($data['service_type_id']);
        if (! empty($data['congregation_id'])) Congregation::query()->findOrFail($data['congregation_id']);
        if (! empty($data['location_id'])) Location::query()->findOrFail($data['location_id']);

        if ($data['frequency'] === 'weekly' && $data['weekday'] === null) {
            throw ValidationException::withMessages(['weekday' => 'Informe o dia da semana.']);
        }
        if ($data['frequency'] === 'monthly_nth' && ($data['weekday'] === null || empty($data['week_of_month']))) {
            throw ValidationException::withMessages(['week_of_month' => 'Informe a ocorrência do dia da semana no mês.']);
        }
        if ($data['frequency'] === 'monthly_date' && empty($data['day_of_month'])) {
            throw ValidationException::withMessages(['day_of_month' => 'Informe o dia fixo do mês.']);
        }

        $data['excluded_weeks_of_month'] = $data['frequency'] === 'weekly'
            ? collect($data['excluded_weeks_of_month'] ?? [])->map(fn ($value) => (int) $value)->unique()->values()->all()
            : null;

        return $data;
    }

    private function syncManualMinisterFromEdit(ServiceRequest $request, Service $service, AvailabilityService $availability, AuditService $audit): void
    {
        $ministerId = $request->input('manual_minister_person_id');
        $assignment = ScheduleAssignment::query()->where('service_id', $service->id)->first();

        if (blank($ministerId)) {
            if ($assignment && $assignment->assignment_mode === 'manual' && $assignment->status === 'draft') {
                $audit->event('schedule.manual_assignment.cleared_from_service_edit', $assignment, null, ['service_id' => $service->id]);
                $assignment->delete();
            }
            return;
        }

        $minister = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
            ->findOrFail($ministerId);

        $evaluation = $availability->evaluateManual($minister, $service);
        if (! $evaluation['eligible']) {
            throw ValidationException::withMessages([
                'manual_minister_person_id' => 'O ministro selecionado não pode assumir este culto: '.implode('; ', $evaluation['reasons']),
            ]);
        }

        if ($assignment && $assignment->status === 'published' && $assignment->minister_person_id !== $minister->id) {
            throw ValidationException::withMessages([
                'manual_minister_person_id' => 'A escala deste culto já foi publicada. Altere o ministro pela tela Escalas para preservar o histórico e as notificações.',
            ]);
        }

        if (! $assignment) {
            $assignment = ScheduleAssignment::query()->create([
                'organization_id' => $service->organization_id,
                'service_id' => $service->id,
                'minister_person_id' => $minister->id,
                'status' => 'draft',
                'assignment_mode' => 'manual',
                'score' => null,
                'manual_reason' => 'Definido diretamente na edição do culto.',
                'explanation' => ['Ministro definido manualmente na edição do culto', 'Disponibilidade e conflitos revalidados'],
                'assigned_by' => $request->user()->id,
            ]);
        } else {
            $assignment->update([
                'minister_person_id' => $minister->id,
                'assignment_mode' => 'manual',
                'score' => null,
                'manual_reason' => 'Definido diretamente na edição do culto.',
                'explanation' => ['Ministro definido manualmente na edição do culto', 'Disponibilidade e conflitos revalidados'],
                'assigned_by' => $request->user()->id,
            ]);
        }

        $audit->event('schedule.manual_assignment.from_service_edit', $assignment, null, [
            'service_id' => $service->id,
            'minister_person_id' => $minister->id,
        ]);
    }

    private function blockingHistoryDependencies(Service $service): array
    {
        $dependencies = [];

        $assignments = $service->assignment()->withTrashed()->get();
        if ($assignments->contains(fn ($assignment) => $assignment->status === 'published' || filled($assignment->published_at))) {
            $dependencies[] = 'escala publicada';
        }

        $repertoires = $service->repertoire()->withTrashed()->withCount('items')->get();
        if ($repertoires->contains(fn ($repertoire) => $repertoire->items_count > 0
            && ($repertoire->status === 'published' || filled($repertoire->published_at) || filled($repertoire->locked_at)))) {
            $dependencies[] = 'repertório publicado com itens';
        }

        $execution = $service->execution()->with(['songs', 'participants', 'ministers'])->first();
        // Abrir/finalizar acidentalmente o Modo culto sem registrar nenhum fato real
        // não transforma um cadastro de teste em histórico imutável. Só bloqueamos
        // quando houve música executada, participante ou ministro efetivamente registrado.
        if ($execution && ($execution->songs->isNotEmpty() || $execution->participants->isNotEmpty() || $execution->ministers->isNotEmpty())) {
            $dependencies[] = 'execução/participação real';
        }

        return $dependencies;
    }
}
