<?php

namespace App\Http\Controllers;

use App\Models\Congregation;
use App\Models\Location;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\SpecialEvent;
use App\Models\SpecialEventResponse;
use App\Services\AuditService;
use App\Services\InternalCalendarService;
use App\Services\TeamNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpecialEventController extends Controller
{
    public function index(Request $request)
    {
        $this->guardVisibility($request);
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $events = SpecialEvent::query()
            ->with(['service.assignment.minister', 'responses.person'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('starts_at')
            ->get();

        return view('special-events.index', compact('events', 'month'));
    }

    public function create(Request $request)
    {
        $this->guardManage($request);
        return view('special-events.form', [
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, SpecialEvent $specialEvent)
    {
        $this->guardManage($request);
        abort_if($specialEvent->status === 'cancelled', 422, 'Evento cancelado não pode ser editado. Exclua-o ou crie um novo evento.');
        $specialEvent->load(['service.congregation', 'service.location']);

        return view('special-events.form', [
            'specialEvent' => $specialEvent,
            'congregations' => Congregation::query()->where('active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SpecialEvent $specialEvent, InternalCalendarService $calendar, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->guardManage($request);
        abort_if($specialEvent->status === 'cancelled', 422, 'Evento cancelado não pode ser editado.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'congregation_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($data['congregation_id'])) Congregation::query()->findOrFail($data['congregation_id']);
        if (! empty($data['location_id'])) Location::query()->findOrFail($data['location_id']);

        $organization = $request->user()->organization;
        $timezone = $organization?->timezone ?: config('app.timezone');
        $starts = Carbon::parse($data['starts_at'], $timezone);
        $ends = filled($data['ends_at']) ? Carbon::parse($data['ends_at'], $timezone) : $starts->copy()->addHours(2);
        $service = $specialEvent->service()->firstOrFail();
        $before = [
            'name' => $specialEvent->name,
            'description' => $specialEvent->description,
            'starts_at' => $specialEvent->starts_at?->toIso8601String(),
            'ends_at' => $specialEvent->ends_at?->toIso8601String(),
            'congregation_id' => $service->congregation_id,
            'location_id' => $service->location_id,
        ];

        DB::transaction(function () use ($request, $specialEvent, $service, $data, $timezone, $starts, $ends): void {
            $specialEvent->update([
                'name' => trim($data['name']),
                'description' => filled($data['description']) ? trim($data['description']) : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'timezone' => $timezone,
                'updated_by' => $request->user()->id,
            ]);

            $service->update([
                'name' => trim($data['name']),
                'description' => filled($data['description']) ? trim($data['description']) : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'timezone' => $timezone,
                'congregation_id' => $data['congregation_id'] ?: null,
                'location_id' => $data['location_id'] ?: null,
                'updated_by' => $request->user()->id,
            ]);
        });

        $calendar->syncService($service->fresh());
        $audit->event('special_event.updated', $specialEvent->fresh(), null, [
            'before' => $before,
            'after' => [
                'name' => $specialEvent->fresh()->name,
                'starts_at' => $starts->toIso8601String(),
                'ends_at' => $ends->toIso8601String(),
                'congregation_id' => $data['congregation_id'] ?: null,
                'location_id' => $data['location_id'] ?: null,
            ],
        ]);

        $summary = implode("\n", array_values(array_filter([
            'Evento: '.trim($data['name']),
            'Dia: '.ucfirst($starts->locale('pt_BR')->translatedFormat('l')),
            'Data: '.$starts->format('d/m/Y'),
            'Horário: '.$starts->format('H:i').' - '.$ends->format('H:i'),
            filled($data['description'] ?? null) ? 'Resumo: '.Str::limit(trim((string) $data['description']), 260, '…') : null,
            'O evento foi atualizado. Revise sua confirmação de participação no SGML.',
        ])));
        $teamNotifications->announce(
            $specialEvent->organization_id,
            'Evento especial atualizado: '.trim($data['name']),
            $summary,
            route('special-events.show', $specialEvent),
            'special_event.updated:'.$specialEvent->id.':'.$specialEvent->updated_at?->timestamp,
            'high'
        );

        return redirect()->route('special-events.show', $specialEvent)->with('status', 'Evento especial atualizado. Agenda e equipe foram atualizadas.');
    }

    public function store(Request $request, InternalCalendarService $calendar, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'congregation_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($data['congregation_id'])) Congregation::query()->findOrFail($data['congregation_id']);
        if (! empty($data['location_id'])) Location::query()->findOrFail($data['location_id']);

        $organization = $request->user()->organization;
        $timezone = $organization?->timezone ?: config('app.timezone');
        $starts = Carbon::parse($data['starts_at'], $timezone);
        $ends = filled($data['ends_at']) ? Carbon::parse($data['ends_at'], $timezone) : $starts->copy()->addHours(2);

        [$event, $service] = DB::transaction(function () use ($request, $data, $timezone, $starts, $ends): array {
            $type = ServiceType::query()->withTrashed()
                ->where('organization_id', $request->user()->organization_id)
                ->where('slug', 'evento-especial')
                ->first();
            if ($type) {
                if ($type->trashed()) $type->restore();
                $type->update([
                    'name' => 'Evento especial',
                    'requires_authorization' => false,
                    'manual_minister_assignment' => true,
                    'active' => true,
                ]);
            } else {
                $type = ServiceType::query()->create([
                    'organization_id' => $request->user()->organization_id,
                    'slug' => 'evento-especial',
                    'name' => 'Evento especial',
                    'description' => 'Evento eventual do ministério com confirmação de participação e ministro definido pela liderança.',
                    'requires_authorization' => false,
                    'manual_minister_assignment' => true,
                    'active' => true,
                ]);
            }

            $event = SpecialEvent::query()->create([
                'organization_id' => $request->user()->organization_id,
                'name' => trim($data['name']),
                'description' => filled($data['description']) ? trim($data['description']) : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'timezone' => $timezone,
                'status' => 'scheduled',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $service = Service::query()->create([
                'organization_id' => $request->user()->organization_id,
                'service_type_id' => $type->id,
                'congregation_id' => $data['congregation_id'] ?: null,
                'location_id' => $data['location_id'] ?: null,
                'special_event_id' => $event->id,
                'name' => trim($data['name']),
                'description' => filled($data['description']) ? trim($data['description']) : null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'timezone' => $timezone,
                'status' => 'scheduled',
                'origin' => 'special_event',
                'notes' => null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'minister_slots' => 1,
            ]);

            return [$event, $service];
        });

        $calendar->syncService($service);
        $audit->event('special_event.created', $event, null, ['service_id' => $service->id]);
        $summary = implode("\n", array_values(array_filter([
            'Evento: '.$event->name,
            'Dia: '.ucfirst($starts->locale('pt_BR')->translatedFormat('l')),
            'Data: '.$starts->format('d/m/Y'),
            'Horário: '.$starts->format('H:i').' - '.$ends->format('H:i'),
            filled($data['description'] ?? null) ? 'Resumo: '.Str::limit(trim((string) $data['description']), 260, '…') : null,
            'Confirme no SGML se poderá participar.',
        ])));
        $teamNotifications->announce(
            $request->user()->organization_id,
            'Novo evento especial: '.$event->name,
            $summary,
            route('special-events.show', $event),
            'special_event.created:'.$event->id,
            'high'
        );

        return redirect()->route('special-events.show', $event)->with('status', 'Evento especial criado. A equipe recebeu alerta para confirmar participação.');
    }

    public function show(Request $request, SpecialEvent $specialEvent)
    {
        $this->guardVisibility($request);
        $specialEvent->load([
            'service.type', 'service.congregation', 'service.location', 'service.assignment.minister',
            'service.repertoire.openingSong.versions', 'service.repertoire.items.song.versions',
            'responses.person',
        ]);

        $person = $request->user()->person;
        $myResponse = $person ? $specialEvent->responses->firstWhere('person_id', $person->id) : null;
        $ministers = collect();
        if ($request->user()->hasPermission('special_events.manage')) {
            $ministers = Person::query()
                ->where('status', 'active')
                ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
                ->orderBy('display_name')
                ->get();
        }

        return view('special-events.show', compact('specialEvent', 'person', 'myResponse', 'ministers'));
    }

    public function respond(Request $request, SpecialEvent $specialEvent, AuditService $audit, InternalCalendarService $calendar)
    {
        $this->guardVisibility($request);
        abort_unless($request->user()->hasPermission('special_events.respond'), 403);
        $person = $request->user()->person;
        abort_unless($person, 422, 'Sua conta precisa estar vinculada a uma pessoa para confirmar participação.');
        $data = $request->validate([
            'response' => ['required', 'in:yes,no'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $response = SpecialEventResponse::query()->updateOrCreate(
            ['special_event_id' => $specialEvent->id, 'person_id' => $person->id],
            [
                'organization_id' => $specialEvent->organization_id,
                'response' => $data['response'],
                'note' => filled($data['note']) ? trim($data['note']) : null,
                'responded_by' => $request->user()->id,
                'responded_at' => now(),
            ]
        );
        $audit->event('special_event.response.updated', $response, null, ['response' => $data['response']]);
        if ($specialEvent->service) {
            $calendar->syncService($specialEvent->service->fresh());
        }

        return back()->with('status', $data['response'] === 'yes' ? 'Participação confirmada.' : 'Indisponibilidade registrada para este evento.');
    }

    public function assignMinister(Request $request, SpecialEvent $specialEvent, InternalCalendarService $calendar, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'minister_person_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $service = $specialEvent->service()->firstOrFail();
        $minister = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
            ->findOrFail($data['minister_person_id']);

        $assignment = ScheduleAssignment::query()->withTrashed()->where('service_id', $service->id)->first();
        if ($assignment) {
            if ($assignment->trashed()) $assignment->restore();
            $assignment->update([
                'minister_person_id' => $minister->id,
                'status' => 'published',
                'assignment_mode' => 'manual',
                'manual_reason' => trim($data['reason']),
                'explanation' => ['Ministro definido pela liderança após consulta das confirmações do evento especial'],
                'assigned_by' => $request->user()->id,
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        } else {
            $assignment = ScheduleAssignment::query()->create([
                'organization_id' => $service->organization_id,
                'service_id' => $service->id,
                'minister_person_id' => $minister->id,
                'status' => 'published',
                'assignment_mode' => 'manual',
                'manual_reason' => trim($data['reason']),
                'score' => null,
                'explanation' => ['Ministro definido pela liderança após consulta das confirmações do evento especial'],
                'assigned_by' => $request->user()->id,
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        }

        $calendar->syncService($service->fresh());
        $audit->event('special_event.minister.assigned', $assignment, $data['reason'], ['special_event_id' => $specialEvent->id]);
        $teamNotifications->announce(
            $specialEvent->organization_id,
            'Ministro definido para '.$specialEvent->name,
            $minister->display_name.' ministrará em '.$specialEvent->starts_at->format('d/m/Y H:i').'. Consulte o evento para participantes e repertório.',
            route('special-events.show', $specialEvent),
            'special_event.minister:'.$specialEvent->id,
            'high'
        );

        return back()->with('status', 'Ministro definido. O evento já integra a escala publicada e permanece visível no calendário.');
    }

    public function cancel(Request $request, SpecialEvent $specialEvent, InternalCalendarService $calendar, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->guardManage($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $service = $specialEvent->service()->first();

        DB::transaction(function () use ($specialEvent, $service, $request): void {
            $specialEvent->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
            if ($service) {
                $service->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
                $service->assignment()?->update(['status' => 'cancelled']);
            }
        });

        if ($service) $calendar->deleteServiceProjection($service, $request->user()->id);
        $audit->event('special_event.cancelled', $specialEvent, $data['reason']);
        $teamNotifications->announce(
            $specialEvent->organization_id,
            'Evento especial cancelado: '.$specialEvent->name,
            $specialEvent->starts_at->format('d/m/Y H:i').' · '.Str::limit($data['reason'], 260, '…'),
            route('special-events.index', ['month' => $specialEvent->starts_at->format('Y-m')]),
            'special_event.cancelled:'.$specialEvent->id,
            'high'
        );

        return redirect()->route('special-events.index', ['month' => $specialEvent->starts_at->format('Y-m')])->with('status', 'Evento especial cancelado e equipe avisada.');
    }

    public function destroy(Request $request, SpecialEvent $specialEvent, InternalCalendarService $calendar, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->guardManage($request);
        $data = $request->validateWithBag('deleteSpecialEvent', [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'confirmation' => ['required', 'in:EXCLUIR'],
        ], [
            'confirmation.in' => 'Digite EXCLUIR exatamente para confirmar a exclusão do evento.',
        ]);

        $service = $specialEvent->service()->first();
        if ($service) {
            $execution = \App\Models\ServiceExecution::query()->where('service_id', $service->id)->first();
            $hasEffectiveHistory = $service->status === 'completed';
            if ($execution) {
                $hasEffectiveHistory = $hasEffectiveHistory
                    || \App\Models\SongExecution::query()->withTrashed()->where('service_execution_id', $execution->id)->exists()
                    || \App\Models\ServiceParticipant::query()->withTrashed()->where('service_execution_id', $execution->id)->exists()
                    || \App\Models\ServiceExecutionMinister::query()->withTrashed()->where('service_execution_id', $execution->id)->exists();
            }
            if ($hasEffectiveHistory) {
                throw ValidationException::withMessages([
                    'delete' => 'Este evento já possui histórico efetivo e não pode ser excluído. Use Cancelar evento para preservar a rastreabilidade.',
                ]);
            }
        }

        $month = $specialEvent->starts_at->format('Y-m');
        $eventName = $specialEvent->name;
        $startsAt = $specialEvent->starts_at->copy();
        $organizationId = $specialEvent->organization_id;

        DB::transaction(function () use ($request, $specialEvent, $service, $calendar, $audit, $data): void {
            $audit->event('special_event.deleted', $specialEvent, $data['reason'], [
                'name' => $specialEvent->name,
                'starts_at' => $specialEvent->starts_at?->toIso8601String(),
                'service_id' => $service?->id,
            ]);

            if ($service) {
                $calendar->deleteServiceProjection($service, $request->user()->id);

                ScheduleAssignment::query()->withTrashed()->where('service_id', $service->id)->get()
                    ->each(fn ($assignment) => $assignment->forceDelete());
                \App\Models\ServiceAdditionalMinister::query()->withTrashed()->where('service_id', $service->id)->get()
                    ->each(fn ($minister) => $minister->forceDelete());
                \App\Models\Repertoire::query()->withTrashed()->where('service_id', $service->id)->get()
                    ->each(fn ($repertoire) => $repertoire->forceDelete());
                \App\Models\SongRequest::query()->where('requested_for_service_id', $service->id)
                    ->update(['requested_for_service_id' => null]);
                \App\Models\CalendarFeedState::query()->withoutGlobalScopes()
                    ->where('organization_id', $service->organization_id)
                    ->where('service_id', $service->id)
                    ->delete();

                $execution = \App\Models\ServiceExecution::query()->where('service_id', $service->id)->first();
                if ($execution) $execution->delete();
                $service->deleteQuietly();
            }

            SpecialEventResponse::query()->where('special_event_id', $specialEvent->id)->delete();
            $specialEvent->deleteQuietly();
        });

        $teamNotifications->announce(
            $organizationId,
            'Evento especial excluído: '.$eventName,
            implode("\n", [
                'Evento: '.$eventName,
                'Data: '.$startsAt->format('d/m/Y'),
                'Horário: '.$startsAt->format('H:i'),
                'Motivo: '.Str::limit(trim($data['reason']), 260, '…'),
                'O evento foi removido da agenda e da escala.',
            ]),
            route('special-events.index', ['month' => $month]),
            'special_event.deleted:'.$specialEvent->id,
            'high'
        );

        return redirect()->route('special-events.index', ['month' => $month])
            ->with('status', 'Evento especial excluído. Ele foi removido da agenda, da escala e das confirmações; a auditoria foi preservada.');
    }

    private function guardVisibility(Request $request): void
    {
        abort_if($request->user()->isMemberWithoutMinistryAccess(), 403);
        abort_unless($request->user()->hasPermission('special_events.view'), 403);
    }

    private function guardManage(Request $request): void
    {
        abort_if($request->user()->isMemberWithoutMinistryAccess(), 403);
        abort_unless($request->user()->hasPermission('special_events.manage'), 403);
    }
}
