<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Services\AuditService;
use App\Services\TeamNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    public function create()
    {
        return view('calendar.form', ['entry' => new CalendarEntry(), 'editing' => false]);
    }

    public function store(Request $request, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $data = $this->validated($request);

        $entry = CalendarEntry::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'source_type' => 'custom',
            'status' => 'scheduled',
            'visibility' => 'public_internal',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $audit->event('calendar_event.created', $entry, null, [
            'calendar_only' => true,
            'creates_service' => false,
            'creates_schedule' => false,
        ]);

        $teamNotifications->announceCalendar(
            $request->user()->organization_id,
            'Nova programação na agenda: '.$entry->title,
            $this->notificationSummary($entry, 'Programação incluída na Agenda do Ministério.'),
            route('calendar.index', ['view' => 'day', 'date' => $entry->starts_at->format('Y-m-d')]),
            'calendar_event.created:'.$entry->id,
        );

        return redirect()->route('calendar.index', ['view' => 'day', 'date' => $entry->starts_at->format('Y-m-d')])
            ->with('status', 'Programação adicionada à Agenda. Nenhum culto ou escala foi criado.');
    }

    public function edit(CalendarEntry $entry)
    {
        $this->assertCustom($entry);
        return view('calendar.form', ['entry' => $entry, 'editing' => true]);
    }

    public function update(Request $request, CalendarEntry $entry, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->assertCustom($entry);
        $data = $this->validated($request);
        $old = $entry->only(['title', 'description', 'starts_at', 'ends_at', 'timezone', 'status']);

        $entry->fill($data + ['updated_by' => $request->user()->id]);
        if ($entry->status === 'cancelled') {
            $entry->status = 'scheduled';
        }
        $entry->save();

        $audit->modelEvent('updated', $entry, $old, $entry->only(['title', 'description', 'starts_at', 'ends_at', 'timezone', 'status']), null, [
            'calendar_only' => true,
            'creates_service' => false,
            'creates_schedule' => false,
        ]);

        $teamNotifications->announceCalendar(
            $request->user()->organization_id,
            'Programação atualizada: '.$entry->title,
            $this->notificationSummary($entry, 'Os dados da programação foram atualizados.'),
            route('calendar.index', ['view' => 'day', 'date' => $entry->starts_at->format('Y-m-d')]),
            'calendar_event.updated:'.$entry->id.':'.$entry->updated_at?->timestamp,
        );

        return redirect()->route('calendar.index', ['view' => 'day', 'date' => $entry->starts_at->format('Y-m-d')])
            ->with('status', 'Programação atualizada na Agenda.');
    }

    public function cancel(Request $request, CalendarEntry $entry, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->assertCustom($entry);
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $entry->forceFill([
            'status' => 'cancelled',
            'updated_by' => $request->user()->id,
        ])->save();

        $audit->event('calendar_event.cancelled', $entry, $data['reason'], [
            'calendar_only' => true,
        ]);

        $teamNotifications->announceCalendar(
            $request->user()->organization_id,
            'Programação cancelada: '.$entry->title,
            $this->notificationSummary($entry, 'Esta programação foi cancelada.'),
            route('calendar.index', ['view' => 'month', 'date' => $entry->starts_at->copy()->startOfMonth()->format('Y-m-d')]),
            'calendar_event.cancelled:'.$entry->id.':'.$entry->updated_at?->timestamp,
            'high',
        );

        return redirect()->route('calendar.index', ['view' => 'month', 'date' => $entry->starts_at->copy()->startOfMonth()->format('Y-m-d')])
            ->with('status', 'Programação cancelada e retirada da agenda ativa.');
    }

    public function destroy(Request $request, CalendarEntry $entry, TeamNotificationService $teamNotifications, AuditService $audit)
    {
        $this->assertCustom($entry);
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'confirmation' => ['required', Rule::in(['EXCLUIR'])],
        ]);

        $organizationId = $entry->organization_id;
        $title = $entry->title;
        $startsAt = $entry->starts_at->copy();
        $entry->forceFill(['updated_by' => $request->user()->id])->saveQuietly();

        $audit->event('calendar_event.deleted', $entry, $data['reason'], [
            'calendar_only' => true,
            'creates_service' => false,
            'creates_schedule' => false,
        ]);
        $entry->delete();

        $teamNotifications->announceCalendar(
            $organizationId,
            'Programação excluída: '.$title,
            'A programação de '.$startsAt->format('d/m/Y H:i').' foi removida da Agenda do Ministério.',
            route('calendar.index', ['view' => 'month', 'date' => $startsAt->copy()->startOfMonth()->format('Y-m-d')]),
            'calendar_event.deleted:'.$entry->id.':'.now()->timestamp,
            'high',
        );

        return redirect()->route('calendar.index', ['view' => 'month', 'date' => $startsAt->copy()->startOfMonth()->format('Y-m-d')])
            ->with('status', 'Programação excluída da Agenda. Nenhuma escala ou culto foi afetado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:3000',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'timezone' => 'required|string|max:100',
        ]);
    }

    private function assertCustom(CalendarEntry $entry): void
    {
        abort_unless($entry->source_type === 'custom', 404);
    }

    private function notificationSummary(CalendarEntry $entry, string $intro): string
    {
        $lines = [
            $intro,
            'Dia: '.Str::ucfirst($entry->starts_at->locale('pt_BR')->translatedFormat('l')),
            'Data: '.$entry->starts_at->format('d/m/Y'),
            'Horário: '.$entry->starts_at->format('H:i').($entry->ends_at ? ' - '.$entry->ends_at->format('H:i') : ''),
        ];
        if (trim((string) $entry->description) !== '') {
            $lines[] = 'Resumo: '.Str::limit(trim((string) $entry->description), 300, '…');
        }
        return implode("\n", $lines);
    }
}
