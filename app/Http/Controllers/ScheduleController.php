<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleConfirmation;
use App\Models\ServiceAdditionalMinister;
use App\Models\Service;
use App\Models\SwapRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\InternalCalendarService;
use App\Services\NotificationService;
use App\Services\MinisterAssistanceContext;
use App\Services\SchedulePdfService;
use App\Services\ScheduleGeneratorService;
use App\Services\ScheduleValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request, ScheduleValidationService $validator, AvailabilityService $availability)
    {
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $canManageSchedule = collect([
            'schedule.generate', 'schedule.publish', 'schedule.reset', 'schedule.manual_assign',
            'schedule.update', 'schedule.additional_ministers', 'schedule.force_swap',
        ])->contains(fn (string $permission) => $request->user()->hasPermission($permission));

        $readonlyView = in_array($request->input('view'), ['minister', 'chronological'], true)
            ? $request->input('view')
            : 'minister';

        $services = Service::query()
            ->with(['type', 'assignment.minister', 'assignment.confirmation', 'additionalMinisters.minister'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($request->user()->isMemberWithoutMinistryAccess(), fn ($q) => $q->whereNull('special_event_id'))
            ->when(
                $canManageSchedule,
                fn ($q) => $q->whereNotIn('status', ['cancelled', 'completed']),
                fn ($q) => $q->where('status', '!=', 'cancelled')
            )
            ->orderBy('starts_at')
            ->get();

        // RC34: pluck() sobre a Eloquent Collection devolve uma Support\Collection,
        // que não possui load(). Consulte as atribuições como Eloquent Collection e
        // carregue explicitamente tudo o que a tela/gerador precisa.
        $assignments = ScheduleAssignment::query()
            ->with([
                'service.type',
                'service.location',
                'service.repertoire.openingSong.versions',
                'service.repertoire.openingSongVersion',
                'service.repertoire.openingMinister',
                'service.repertoire.items.song.versions',
                'service.repertoire.items.songVersion',
                'service.repertoire.items.minister',
                'service.additionalMinisters.minister',
                'minister',
                'confirmation',
            ])
            ->whereIn('service_id', $services->pluck('id')->all())
            ->when(! $canManageSchedule, fn ($q) => $q->where('status', 'published'))
            ->get();

        if (! $canManageSchedule) {
            $publishedServiceIds = $assignments->pluck('service_id')->all();
            $services = $services->whereIn('id', $publishedServiceIds)->values();
        }

        $validation = $validator->validateMonth($month);

        $ministers = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
            ->with('servicePermissions')
            ->orderBy('display_name')
            ->get();

        $eligibleMinistersByAssignment = $assignments->mapWithKeys(function (ScheduleAssignment $assignment) use ($ministers, $availability) {
            // RC76: a liderança pode escolher deliberadamente um ministro que já
            // esteja em outro culto no mesmo dia. A sobreposição real de horário
            // continua bloqueada e a exceção do mesmo dia aparece como advertência.
            $candidates = $ministers->map(function (Person $minister) use ($assignment, $availability) {
                $evaluation = $availability->evaluateForManualOverride($minister, $assignment->service);
                return [
                    'person' => $minister,
                    'selectable' => (bool) ($evaluation['selectable'] ?? false),
                    'warnings' => $evaluation['warnings'] ?? [],
                    'blocking_reasons' => $evaluation['blocking_reasons'] ?? [],
                ];
            })->filter(fn (array $candidate) => $candidate['selectable'])->values();
            return [$assignment->id => $candidates];
        });

        $manualServices = $services
            ->filter(fn (Service $service) => $service->type?->manual_minister_assignment && ! $service->assignment)
            ->values();

        $manualCandidatesByService = $manualServices->mapWithKeys(function (Service $service) use ($ministers, $availability) {
            $candidates = $ministers->map(function (Person $minister) use ($service, $availability) {
                $evaluation = $availability->evaluateForManualOverride($minister, $service);
                return [
                    'person' => $minister,
                    'selectable' => (bool) ($evaluation['selectable'] ?? false),
                    'warnings' => $evaluation['warnings'] ?? [],
                ];
            })->filter(fn (array $candidate) => $candidate['selectable'])->values();
            return [$service->id => $candidates];
        });

        $additionalCandidatesByService = $services->filter(fn (Service $service) => (int) $service->minister_slots > 1)->mapWithKeys(function (Service $service) use ($ministers, $availability) {
            $primaryId = $service->assignment?->minister_person_id;
            $selected = $service->additionalMinisters->pluck('person_id')->all();
            $eligible = $ministers->map(function (Person $minister) use ($service, $availability, $primaryId, $selected) {
                if ($minister->id === $primaryId) return null;
                $evaluation = $availability->evaluateForManualOverride($minister, $service);
                if (! ($evaluation['selectable'] ?? false) && ! in_array($minister->id, $selected, true)) return null;
                return [
                    'person' => $minister,
                    'warnings' => $evaluation['warnings'] ?? [],
                ];
            })->filter()->values();
            return [$service->id => $eligible];
        });

        $hasPublished = $assignments->contains(fn (ScheduleAssignment $assignment) => $assignment->status === 'published');

        // RC40: além do contador agregado, a tela mostra exatamente em quais cultos
        // cada ministro está escalado. Ministros adicionais de cultos especiais também
        // aparecem na visão para que a liderança consiga conferir o mês rapidamente.
        $overview = [];
        foreach ($assignments->sortBy(fn (ScheduleAssignment $assignment) => $assignment->service?->starts_at) as $assignment) {
            $service = $assignment->service;
            $minister = $assignment->minister;
            if ($service && $minister) {
                $overview[$minister->id] ??= ['id' => $minister->id, 'name' => $minister->display_name, 'services' => []];
                $overview[$minister->id]['services'][] = [
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'starts_at' => $service->starts_at,
                    'type' => $service->type?->name,
                    'additional' => false,
                    'status' => $assignment->status,
                ];
            }

            if ($service) {
                foreach ($service->additionalMinisters as $additional) {
                    $extra = $additional->minister;
                    if (! $extra) continue;
                    $overview[$extra->id] ??= ['id' => $extra->id, 'name' => $extra->display_name, 'services' => []];
                    $overview[$extra->id]['services'][] = [
                        'service_id' => $service->id,
                        'name' => $service->name,
                        'starts_at' => $service->starts_at,
                        'type' => $service->type?->name,
                        'additional' => true,
                        'status' => $additional->status,
                    ];
                }
            }
        }
        $ministerScheduleOverview = collect($overview)
            ->map(function (array $item) {
                $item['services'] = collect($item['services'])->sortBy('starts_at')->values();
                $item['count'] = $item['services']->count();
                return $item;
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // RC40: diagnóstico por tipo deixa visível quando um rodízio semanal está
        // concentrado em apenas parte dos ministros habilitados. A lista representa
        // habilitação administrativa; indisponibilidades por data ainda podem explicar
        // diferenças legítimas na distribuição final.
        $rotationByType = $services
            ->filter(fn (Service $service) => ! $service->type?->manual_minister_assignment)
            ->groupBy('service_type_id')
            ->map(function ($typeServices) use ($ministers, $assignments, $availability) {
                $firstService = $typeServices->first();
                $typeId = $firstService?->service_type_id;
                $authorized = $ministers->filter(fn (Person $minister) => in_array($typeId, $minister->allowedServiceTypeIds(), true))->values();
                if ($authorized->count() < 2 || $typeServices->count() < 2) return null;

                $serviceIds = $typeServices->pluck('id')->all();
                $counts = $authorized->map(function (Person $minister) use ($assignments, $serviceIds, $typeServices, $availability) {
                    $primaryCount = $assignments->filter(fn (ScheduleAssignment $assignment) => in_array($assignment->service_id, $serviceIds, true) && $assignment->minister_person_id === $minister->id)->count();
                    $additionalCount = $typeServices->sum(fn (Service $service) => $service->additionalMinisters->where('person_id', $minister->id)->count());
                    // RC48: oportunidade real considera disponibilidade, exceções e a
                    // regra de um culto por dia na escala ATUAL. Assim um 0/2/2 não é
                    // marcado como erro quando o terceiro ministro estava legitimamente
                    // indisponível em todas as ocorrências daquele tipo.
                    $opportunities = $typeServices->filter(fn (Service $service) => $availability->evaluate($minister, $service)['eligible'])->count();
                    return [
                        'id' => $minister->id,
                        'name' => $minister->display_name,
                        'count' => $primaryCount + $additionalCount,
                        'opportunities' => $opportunities,
                    ];
                })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

                $min = (int) ($counts->min('count') ?? 0);
                $max = (int) ($counts->max('count') ?? 0);
                $spread = $max - $min;
                $underusedHasOpportunity = $spread > 1 && $counts
                    ->where('count', $min)
                    ->contains(fn (array $item) => (int) ($item['opportunities'] ?? 0) > 0);
                $balanceStatus = $spread <= 1 ? 'balanced' : ($underusedHasOpportunity ? 'review' : 'constrained');
                return [
                    'type_id' => $typeId,
                    'type_name' => $firstService?->type?->name ?? $firstService?->name ?? 'Tipo de culto',
                    'service_count' => $typeServices->count(),
                    'authorized_count' => $authorized->count(),
                    'counts' => $counts,
                    'spread' => $spread,
                    'balance_status' => $balanceStatus,
                ];
            })
            ->filter()
            ->sortBy('type_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('schedules.index', compact(
            'month', 'assignments', 'validation', 'eligibleMinistersByAssignment',
            'manualServices', 'manualCandidatesByService', 'additionalCandidatesByService', 'hasPublished',
            'ministerScheduleOverview', 'rotationByType', 'canManageSchedule', 'readonlyView'
        ));
    }

    public function generate(Request $request, ScheduleGeneratorService $generator)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $result = $generator->generate(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth());

        $message = sprintf(
            'Sugestão processada: %d novo(s), %d corrigido(s), %d mantido(s) e %d culto(s) pendente(s).',
            $result['assigned'], $result['reassigned'], $result['kept'], count($result['unassigned'])
        );
        if (($result['recurrence_sync']['cancelled'] ?? 0) > 0) {
            $message .= ' '.($result['recurrence_sync']['cancelled']).' ocorrência(s) recorrente(s) incompatível(is) foi(ram) retirada(s) antes do rodízio.';
        }
        if (count($result['published_invalid']) > 0) {
            $message .= ' Atenção: '.count($result['published_invalid']).' atribuição(ões) publicada(s) precisa(m) de revisão.';
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, ScheduleAssignment $assignment, AvailabilityService $availability, NotificationService $notifications, InternalCalendarService $calendar)
    {
        $data = $request->validate(['minister_person_id' => ['required', 'uuid']]);
        $minister = $this->ministerQuery()->findOrFail($data['minister_person_id']);
        $result = $availability->evaluateForManualOverride($minister, $assignment->service);

        if (! ($result['selectable'] ?? false)) {
            throw ValidationException::withMessages(['minister_person_id' => 'O ministro selecionado possui indisponibilidade ou conflito: '.implode('; ', $result['blocking_reasons'] ?? $result['reasons'])]);
        }

        DB::transaction(function () use ($assignment, $minister, $request, $notifications, $result): void {
            $previousMinisterId = $assignment->minister_person_id;
            $assignment->update([
                'minister_person_id' => $minister->id,
                'score' => null,
                'assignment_mode' => 'manual',
                'explanation' => array_values(array_filter([
                    'Alteração direta pela liderança/administração',
                    'Habilitação por tipo dispensada; disponibilidade e conflitos revalidados',
                    ! empty($result['warnings']) ? 'Exceção deliberada: ministro já possui outro culto no mesmo dia' : null,
                ])),
                'assigned_by' => $request->user()->id,
            ]);
            $this->refreshPublishedConfirmation($assignment, $previousMinisterId, $minister, $notifications);
        });

        $calendar->syncService($assignment->service()->firstOrFail());
        return back()->with('status', 'Ministro alterado com sucesso.'.(! empty($result['warnings']) ? ' Atenção: ele já possui outro culto no mesmo dia; a exceção foi mantida deliberadamente.' : ''));
    }

    public function manualAssign(Request $request, Service $service, AvailabilityService $availability, NotificationService $notifications, AuditService $audit, InternalCalendarService $calendar)
    {
        $data = $request->validate([
            'minister_person_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $minister = $this->ministerQuery()->findOrFail($data['minister_person_id']);
        $result = $availability->evaluateForManualOverride($minister, $service);
        if (! ($result['selectable'] ?? false)) {
            throw ValidationException::withMessages(['manual_minister' => 'Não é possível atribuir este ministro: '.implode('; ', $result['blocking_reasons'] ?? $result['reasons'])]);
        }

        DB::transaction(function () use ($request, $service, $minister, $data, $notifications, $audit, $result): void {
            $assignment = ScheduleAssignment::query()->where('service_id', $service->id)->first();
            $previousMinisterId = $assignment?->minister_person_id;

            if (! $assignment) {
                $assignment = ScheduleAssignment::query()->create([
                    'organization_id' => $service->organization_id,
                    'service_id' => $service->id,
                    'minister_person_id' => $minister->id,
                    'status' => 'draft',
                    'assignment_mode' => 'manual',
                    'score' => null,
                    'manual_reason' => $data['reason'],
                    'explanation' => array_values(array_filter([
                        'Ministro definido manualmente pela liderança/administração',
                        'Habilitação por tipo de culto não foi exigida; disponibilidade/conflitos foram validados',
                        ! empty($result['warnings']) ? 'Exceção deliberada: ministro já possui outro culto no mesmo dia' : null,
                    ])),
                    'assigned_by' => $request->user()->id,
                ]);
            } else {
                $assignment->update([
                    'minister_person_id' => $minister->id,
                    'assignment_mode' => 'manual',
                    'manual_reason' => $data['reason'],
                    'score' => null,
                    'explanation' => array_values(array_filter([
                        'Ministro definido manualmente pela liderança/administração',
                        'Disponibilidade e conflitos foram validados',
                        ! empty($result['warnings']) ? 'Exceção deliberada: ministro já possui outro culto no mesmo dia' : null,
                    ])),
                    'assigned_by' => $request->user()->id,
                ]);
                $this->refreshPublishedConfirmation($assignment, $previousMinisterId, $minister, $notifications);
            }

            $audit->event('schedule.manual_assignment', $assignment, $data['reason'], [
                'service_id' => $service->id,
                'minister_person_id' => $minister->id,
            ]);
        });

        $calendar->syncService($service->fresh());
        return back()->with('status', 'Ministro definido manualmente para este culto.'.(! empty($result['warnings']) ? ' Atenção: ele já possui outro culto no mesmo dia; a exceção foi aceita.' : ''));
    }

    public function syncAdditionalMinisters(Request $request, Service $service, AvailabilityService $availability, AuditService $audit, InternalCalendarService $calendar)
    {
        abort_unless((int) $service->minister_slots > 1, 422, 'Este culto possui apenas um ministro previsto.');
        $data = $request->validate([
            'minister_person_ids' => ['nullable','array','max:11'],
            'minister_person_ids.*' => ['uuid'],
        ]);
        $ids = array_values(array_unique(array_filter($data['minister_person_ids'] ?? [])));
        $max = max(0, (int) $service->minister_slots - 1);
        if (count($ids) > $max) throw ValidationException::withMessages(['additional_ministers' => "Este culto permite no máximo {$max} ministro(s) adicional(is)."]);

        $service->loadMissing('assignment');
        if ($service->assignment && in_array($service->assignment->minister_person_id, $ids, true)) {
            throw ValidationException::withMessages(['additional_ministers' => 'O ministro principal não pode ser repetido como ministro adicional.']);
        }

        $people = $this->ministerQuery()->whereIn('id', $ids)->get()->keyBy('id');
        $sameDayWarnings = [];
        foreach ($ids as $id) {
            $minister = $people->get($id);
            if (! $minister) throw ValidationException::withMessages(['additional_ministers' => 'Um dos ministros selecionados não é válido.']);
            $evaluation = $availability->evaluateForManualOverride($minister, $service);
            if (! ($evaluation['selectable'] ?? false)) throw ValidationException::withMessages(['additional_ministers' => $minister->display_name.' não pode assumir este culto: '.implode('; ', $evaluation['blocking_reasons'] ?? $evaluation['reasons'])]);
            if (! empty($evaluation['warnings'])) $sameDayWarnings[] = $minister->display_name;
        }

        DB::transaction(function () use ($service, $ids, $people, $request, $audit): void {
            $current = ServiceAdditionalMinister::query()->where('service_id', $service->id)->get();
            foreach ($current as $item) {
                if (! in_array($item->person_id, $ids, true)) {
                    $audit->event('schedule.additional_minister.removed', $item, null, ['service_id' => $service->id]);
                    $item->delete();
                }
            }
            foreach ($ids as $index => $personId) {
                $item = ServiceAdditionalMinister::query()->withTrashed()->where('service_id', $service->id)->where('person_id', $personId)->first();
                $status = $service->assignment?->status === 'published' ? 'published' : 'draft';
                if ($item) {
                    if ($item->trashed()) $item->restore();
                    $item->update(['position' => $index + 2, 'status' => $status, 'assigned_by' => $request->user()->id, 'published_by' => $status === 'published' ? $request->user()->id : null, 'published_at' => $status === 'published' ? now() : null]);
                } else {
                    $item = ServiceAdditionalMinister::query()->create([
                        'organization_id' => $service->organization_id, 'service_id' => $service->id, 'person_id' => $personId, 'position' => $index + 2,
                        'status' => $status, 'assigned_by' => $request->user()->id, 'published_by' => $status === 'published' ? $request->user()->id : null, 'published_at' => $status === 'published' ? now() : null,
                    ]);
                }
                $audit->event('schedule.additional_minister.assigned', $item, null, ['service_id' => $service->id, 'person_id' => $personId]);
            }
        });

        $calendar->syncService($service->fresh());
        return back()->with('status', 'Ministros adicionais atualizados para este culto.'.($sameDayWarnings ? ' Atenção: '.implode(', ', $sameDayWarnings).' já possui(em) outro culto no mesmo dia; a exceção foi aceita.' : ''));
    }

    public function publish(Request $request, NotificationService $notifications, ScheduleValidationService $validator, InternalCalendarService $calendar, \App\Services\TeamNotificationService $teamNotifications, \App\Services\RecurringServiceGeneratorService $recurring)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'notify_whatsapp' => 'nullable|boolean',
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        // A publicação é a última barreira contra ocorrências recorrentes antigas.
        // Se a regra mudou depois da sugestão, reconciliamos antes de validar/publicar.
        $recurrenceSync = $recurring->synchronizeMonth($month);
        $validation = $validator->validateMonth($month);
        if (! $validation['valid']) return back()->withErrors(['schedule' => 'A escala possui pendências e não pode ser publicada.']);

        DB::transaction(function () use ($month, $request, $notifications): void {
            $assignments = ScheduleAssignment::query()->with(['minister.user', 'service'])
                ->whereHas('service', fn ($q) => $q
                    ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->whereNotIn('status', ['cancelled', 'completed', 'in_progress']))
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);
                // RC46: a escala publicada já é válida; não existe etapa de
                // confirmação. O ministro usa Minha Agenda somente para visualizar
                // sua escala e, se necessário, solicitar troca.
                ScheduleConfirmation::query()->where('schedule_assignment_id', $assignment->id)->delete();
                if ($assignment->minister?->user) {
                    $notifications->create($assignment->minister->user, 'Nova escala publicada', 'Você foi escalado para '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'. Se não puder ministrar, use a opção de troca em Minha Agenda.', 'information', 'high', route('my.agenda'), $assignment->service->starts_at->copy()->subDay(), ['assignment_id' => $assignment->id]);
                }
            }

            $additional = ServiceAdditionalMinister::query()->with(['minister.user','service'])
                ->whereHas('service', fn ($q) => $q
                    ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->whereNotIn('status', ['cancelled', 'completed', 'in_progress']))
                ->get();
            foreach ($additional as $extra) {
                $extra->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);
                if ($extra->minister?->user) {
                    $notifications->create($extra->minister->user, 'Participação em culto especial', 'Você foi incluído como ministro adicional em '.$extra->service->name.' em '.$extra->service->starts_at->format('d/m/Y H:i').'. Você poderá colaborar no repertório.', 'information', 'normal', route('my.agenda'), $extra->service->starts_at->copy()->subDay(), ['service_id' => $extra->service_id]);
                }
            }
        });

        // RC44: publicar a escala atualiza imediatamente a projeção externa.
        Service::query()
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereHas('assignment', fn ($q) => $q->where('status', 'published'))
            ->get()
            ->each(fn (Service $service) => $calendar->syncService($service));

        $sendWhatsapp = $request->boolean('notify_whatsapp');
        $scheduleSummary = implode("\n", [
            'Mês: '.ucfirst($month->locale('pt_BR')->translatedFormat('F/Y')),
            'Status: escala publicada',
            'Cultos publicados: '.Service::query()
                ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->whereNotIn('status', ['cancelled', 'completed', 'in_progress'])
                ->whereHas('assignment', fn ($q) => $q->where('status', 'published'))
                ->count(),
            '',
            'Acesse o SGML para consultar ministros, cultos, trocas e repertórios.',
        ]);

        $announcement = $teamNotifications->announce(
            $request->user()->organization_id,
            'Escala publicada - '.$month->translatedFormat('F/Y'),
            $scheduleSummary,
            route('schedule.index', ['month' => $month->format('Y-m')]),
            'schedule.published:'.$month->format('Y-m'),
            'high',
            $sendWhatsapp
        );

        $message = 'Escala validada e publicada.';
        if (($recurrenceSync['cancelled'] ?? 0) > 0) {
            $message .= ' '.($recurrenceSync['cancelled']).' ocorrência(s) antiga(s) incompatível(is) foi(ram) retirada(s) antes da publicação.';
        }
        if (! $sendWhatsapp) {
            $message .= ' O aviso foi registrado no SGML; envio por WhatsApp desmarcado nesta publicação.';
        } elseif (($announcement['whatsapp_queued'] ?? 0) > 0) {
            $message .= ' '.($announcement['whatsapp_queued']).' aviso(s) foram enfileirado(s) para WhatsApp com envio espaçado, além do registro interno no SGML.';
        } else {
            $message .= ' O WhatsApp foi solicitado, mas nenhum envio pôde ser enfileirado. Verifique se a integração está habilitada e se os integrantes possuem telefone válido.';
        }

        return back()->with('status', $message);
    }

    public function reset(Request $request, AuditService $audit, InternalCalendarService $calendar)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        // RC30: revogar uma escala é uma operação administrativa de versionamento,
        // não uma exclusão do histórico do culto. A existência de Modo Culto/execução
        // real não deve impedir o administrador de arquivar a VERSÃO DA ESCALA.
        // Os registros efetivos continuam intactos em service_executions, song_executions,
        // ministros/participantes efetivos e auditoria. Apenas as atribuições ativas do
        // mês são supersedidas, liberando uma nova versão para os cultos ainda escaláveis.
        $services = Service::query()
            ->with('execution')
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->get();

        $assignments = ScheduleAssignment::query()
            ->with(['service.execution'])
            ->whereIn('service_id', $services->pluck('id'))
            ->get();

        $historicalCount = $services
            ->filter(fn (Service $service) => in_array($service->status, ['in_progress', 'completed'], true) || $service->execution?->status === 'finished')
            ->count();

        DB::transaction(function () use ($assignments, $services, $request, $audit, $data, $month): void {
            $assignmentIds = $assignments->pluck('id')->all();
            if ($assignmentIds !== []) {
                \App\Models\ActionItem::query()
                    ->where('status', 'pending')
                    ->get()
                    ->filter(fn ($item) => in_array($item->payload['assignment_id'] ?? null, $assignmentIds, true))
                    ->each(fn ($item) => $item->update(['status' => 'dismissed', 'read_at' => $item->read_at ?: now()]));

                // Solicitações pendentes pertencem à versão que está sendo revogada.
                // Mantemos os registros para auditoria, mas retiramos da fila operacional.
                SwapRequest::query()
                    ->whereIn('schedule_assignment_id', $assignmentIds)
                    ->where('status', 'requested')
                    ->update(['status' => 'cancelled']);
            }

            // RC44: repertório planejado pertence à versão ativa da escala.
            // Ao revogar a escala de um culto ainda não executado, a próxima versão
            // deve começar vazia, mesmo que caia no mesmo dia e no mesmo ministro.
            foreach ($services->whereIn('id', $assignments->pluck('service_id')->all()) as $service) {
                $historical = in_array($service->status, ['in_progress', 'completed'], true)
                    || $service->execution?->status === 'finished';
                if ($historical) {
                    continue;
                }

                $repertoire = $service->repertoire()->first();
                if ($repertoire) {
                    $snapshot = $repertoire->items()->withTrashed()->with('song')->get()->map(fn ($item) => [
                        'song_id' => $item->song_id,
                        'title' => $item->song?->title,
                        'position' => $item->position,
                        'planned_key' => $item->planned_key,
                        'notes' => $item->notes,
                    ])->values()->all();

                    $audit->event('schedule.repertoire.superseded', $repertoire, $data['reason'], [
                        'month' => $month->format('Y-m'),
                        'service_id' => $service->id,
                        'revision_number' => $repertoire->revision_number,
                        'items' => $snapshot,
                    ]);
                    $repertoire->forceFill([
                        'status' => 'superseded',
                        'updated_by' => $request->user()->id,
                    ])->saveQuietly();
                    // RC44 usa índice único apenas para o repertório ativo; por isso
                    // a versão antiga pode ser arquivada sem ser reutilizada.
                    $repertoire->delete();
                }

                // Pedido de louvor antigo pode continuar válido para a biblioteca,
                // mas não pode inserir conteúdo automaticamente na nova escala.
                \App\Models\SongRequest::query()
                    ->where('requested_for_service_id', $service->id)
                    ->where('status', 'pending')
                    ->update(['requested_for_service_id' => null]);
            }

            foreach ($assignments as $assignment) {
                $audit->event('schedule.assignment.superseded', $assignment, $data['reason'], [
                    'month' => $month->format('Y-m'),
                    'old_status' => $assignment->status,
                    'service_status' => $assignment->service?->status,
                    'execution_status' => $assignment->service?->execution?->status,
                    'execution_preserved' => (bool) $assignment->service?->execution,
                ]);
                $assignment->delete();
            }
            ServiceAdditionalMinister::query()->whereIn('service_id', $assignments->pluck('service_id'))->get()->each(function ($extra) use ($audit, $data): void {
                $audit->event('schedule.additional_minister.superseded', $extra, $data['reason']);
                $extra->delete();
            });
        });

        // A projeção/integração externa recebe o estado sem escala imediatamente.
        $services->each(function (Service $service) use ($calendar): void {
            $fresh = $service->fresh();
            if ($fresh) {
                $calendar->syncService($fresh);
            }
        });

        $message = 'Escala anterior revogada e arquivada. Repertórios planejados dos cultos ainda não executados foram encerrados; a nova escala começa sem herdar louvores antigos.';
        if ($historicalCount > 0) {
            $message .= ' '.$historicalCount.' culto(s) em andamento/concluído(s) permaneceram preservados como histórico e não serão redistribuídos pelo gerador.';
        }

        return back()->with('status', $message);
    }

    public function pdf(Request $request, ScheduleValidationService $validator, SchedulePdfService $pdf)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $canManageSchedule = collect([
            'schedule.generate', 'schedule.publish', 'schedule.reset', 'schedule.manual_assign',
            'schedule.update', 'schedule.additional_ministers', 'schedule.force_swap',
        ])->contains(fn (string $permission) => $request->user()->hasPermission($permission));

        if ($canManageSchedule) {
            $validation = $validator->validateMonth($month);
            abort_unless($validation['valid'], 422, 'A escala possui pendências e ainda não pode ser exportada como PDF.');
        }

        $assignments = ScheduleAssignment::query()->with([
                'minister','service.type','service.congregation','service.location',
                'service.repertoire.openingSong.versions','service.repertoire.openingSongVersion','service.repertoire.openingMinister',
                'service.repertoire.items.song.versions','service.repertoire.items.songVersion','service.repertoire.items.minister',
                'service.additionalMinisters.minister'
            ])
            ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->where('status', '!=', 'cancelled'))
            ->when(! $canManageSchedule, fn ($q) => $q->where('status', 'published'))
            ->get()->sortBy(fn ($assignment) => $assignment->service->starts_at)->values();

        abort_if(! $canManageSchedule && $assignments->isEmpty(), 404, 'Ainda não existe escala publicada para este mês.');
        $status = $assignments->isNotEmpty() && $assignments->every(fn ($a) => $a->status === 'published') ? 'PUBLICADA' : 'VALIDADA';
        $content = $pdf->render($assignments, $month, $request->user()->organization?->name ?? config('app.name'), $status);
        return response($content, 200, ['Content-Type' => 'application/pdf','Content-Disposition' => 'attachment; filename="escala-'.$month->format('Y-m').'.pdf"','Cache-Control' => 'private, no-store, max-age=0']);
    }

    public function confirm(Request $request, ScheduleAssignment $assignment)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        abort_unless($assignment->status === 'published', 404);
        abort_unless($person && $assignment->minister_person_id === $person->id, 403);
        $confirmation = ScheduleConfirmation::query()->firstOrCreate(['schedule_assignment_id' => $assignment->id, 'person_id' => $person->id], ['organization_id' => $assignment->organization_id]);
        $confirmation->update(['status' => 'confirmed', 'responded_at' => now()]);
        return back()->with('status', 'Participação confirmada.');
    }

    public function unavailable(Request $request, ScheduleAssignment $assignment, NotificationService $notifications)
    {
        $person = app(MinisterAssistanceContext::class)->person($request->user());
        abort_unless($assignment->status === 'published', 404);
        abort_unless($person && $assignment->minister_person_id === $person->id, 403);
        $data = $request->validate(['notes' => 'required|string|min:3|max:1000']);

        $confirmation = ScheduleConfirmation::query()->firstOrCreate(
            ['schedule_assignment_id' => $assignment->id, 'person_id' => $person->id],
            ['organization_id' => $assignment->organization_id]
        );
        $confirmation->update(['status' => 'unavailable', 'notes' => $data['notes'], 'responded_at' => now()]);

        $existing = SwapRequest::query()
            ->where('schedule_assignment_id', $assignment->id)
            ->where('requester_person_id', $person->id)
            ->where('status', 'requested')
            ->first();

        $createdSwap = false;
        if (! $existing) {
            $existing = SwapRequest::query()->create([
                'organization_id' => $assignment->organization_id,
                'schedule_assignment_id' => $assignment->id,
                'requester_person_id' => $person->id,
                'target_person_id' => null,
                'status' => 'requested',
                'type' => 'replacement',
                'reason' => $data['notes'],
            ]);
            $createdSwap = true;
        }

        $assignment->loadMissing('service');
        if ($createdSwap) {
            User::query()->where('organization_id', $assignment->organization_id)->where('active', true)->get()
                ->filter(fn (User $user) => $user->hasPermission('schedule.force_swap') || $user->hasPermission('schedule.update'))
                ->each(function (User $user) use ($notifications, $person, $assignment, $existing): void {
                    $notifications->create(
                        $user,
                        'Ministro informou impedimento',
                        $person->display_name.' informou impedimento para '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'. Defina/direcione um substituto.',
                        'action',
                        'high',
                        route('swaps.index'),
                        $assignment->service->starts_at->copy()->subDay(),
                        ['swap_request_id' => $existing->id, 'assignment_id' => $assignment->id]
                    );
                });
        }

        return back()->with('status', $createdSwap
            ? 'Impedimento registrado. A liderança foi avisada para providenciar uma substituição.'
            : 'Impedimento atualizado. Já existe uma solicitação de substituição em andamento.');
    }

    private function ministerQuery()
    {
        return Person::query()->where('status', 'active')->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true));
    }

    private function refreshPublishedConfirmation(ScheduleAssignment $assignment, ?string $previousMinisterId, Person $minister, NotificationService $notifications): void
    {
        if ($assignment->status !== 'published' || $previousMinisterId === $minister->id) return;
        ScheduleConfirmation::query()->where('schedule_assignment_id', $assignment->id)->delete();
        $minister->loadMissing('user');
        if ($minister->user) {
            $notifications->create(
                $minister->user,
                'Alteração de escala publicada',
                'Você foi definido como ministro de '.$assignment->service->name.' em '.$assignment->service->starts_at->format('d/m/Y H:i').'. Se houver impedimento, utilize a troca em Minha Agenda.',
                'information',
                'high',
                route('my.agenda'),
                $assignment->service->starts_at->copy()->subDay(),
                ['assignment_id' => $assignment->id]
            );
        }
    }
}
