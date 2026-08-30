<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\CalendarIntegration;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceAdditionalMinister;
use App\Models\SwapRequest;
use App\Services\AvailabilityService;
use App\Services\IcsService;
use App\Services\CalendarSubscriptionService;
use App\Services\OrganizationGoogleCalendarSettingsService;
use App\Services\MinisterAssistanceContext;
use App\Services\SchedulePdfService;
use App\Services\InternalCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    public function index(Request $request, InternalCalendarService $calendar)
    {
        $view = $request->input('view','month');
        $date = Carbon::parse($request->input('date',now()->toDateString()));
        [$from,$to] = match($view) {
            'day' => [$date->copy()->startOfDay(),$date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(),$date->copy()->endOfWeek()],
            'list' => [$date->copy()->startOfDay(),$date->copy()->addDays(60)->endOfDay()],
            default => [$date->copy()->startOfMonth()->startOfDay(),$date->copy()->endOfMonth()->endOfDay()],
        };
        // RC48: a agenda interna se auto-reconcilia em cada abertura. Assim
        // repertório/ministro alterado aparece no calendário do sistema sem depender
        // de Google, ICS, fila externa ou nova assinatura.
        $calendar->refreshRange($from, $to);

        $entryQuery = CalendarEntry::query()
            ->where('starts_at','<=',$to)
            ->where(fn($q)=>$q->whereNull('ends_at')->where('starts_at','>=',$from)->orWhere('ends_at','>=',$from));

        // RC59: evento especial é conteúdo operacional do ministério e nunca é
        // exposto ao perfil Membro, mesmo que uma ACL antiga tenha liberado calendário.
        if ($request->user()->isMemberWithoutMinistryAccess()) {
            $specialServiceIds = \App\Models\Service::query()->whereNotNull('special_event_id')->pluck('id');
            if ($specialServiceIds->isNotEmpty()) {
                $entryQuery->where(function ($query) use ($specialServiceIds): void {
                    $query->where('source_type', '!=', 'service')->orWhereNotIn('source_id', $specialServiceIds);
                });
            }
        }

        $entries = $entryQuery->orderBy('starts_at')->get();
        $entriesByDate = $entries->groupBy(fn ($entry) => $entry->starts_at->format('Y-m-d'));
        return view('calendar.index',compact('entries','entriesByDate','view','date','from','to'));
    }

    public function myAgenda(Request $request, AvailabilityService $availability, OrganizationGoogleCalendarSettingsService $googleSettings, MinisterAssistanceContext $assistance)
    {
        if ($request->user()->isParticipantOnly() || $request->user()->isMemberWithoutMinistryAccess()) {
            return redirect()->route('home')->with('status', 'Minha Agenda é exclusiva do ministro. Músicos acompanham os cultos pela Escala/Agenda e o perfil Membro não possui agenda ministerial pessoal.');
        }
        $person = $assistance->person($request->user());
        $assistedPerson = $assistance->assistedPerson($request->user());
        $assignments = collect();
        $activeSwapsByAssignment = collect();
        $incomingSwaps = collect();
        $outgoingSwaps = collect();
        $additionalCommitments = collect();

        if ($person) {
            // RC46: Minha Agenda é estritamente pessoal. Escala coletiva fica em
            // Escalas; confirmações foram retiradas do fluxo e a ação operacional
            // relevante para o ministro é solicitar/aceitar troca.
            $assignments = ScheduleAssignment::query()
                ->with([
                    'service.type',
                    'service.congregation',
                    'service.location',
                    'service.repertoire.openingSong.versions',
                    'service.repertoire.openingSongVersion',
                    'service.repertoire.openingMinister',
                    'service.repertoire.items.song.versions',
                    'service.repertoire.items.songVersion',
                    'service.repertoire.items.minister',
                ])
                ->where('minister_person_id', $person->id)
                ->where('status', 'published')
                ->whereHas('service', fn ($q) => $q
                    ->where('status', '!=', 'cancelled')
                    ->where('starts_at', '>=', now()->startOfDay())
                    ->where('starts_at', '<=', now()->addDays(90)->endOfDay()))
                ->get()
                ->sortBy(fn ($a) => $a->service->starts_at)
                ->values();

            $additionalCommitments = ServiceAdditionalMinister::query()
                ->with([
                    'service.type',
                    'service.location',
                    'service.repertoire.openingSong.versions',
                    'service.repertoire.openingSongVersion',
                    'service.repertoire.openingMinister',
                    'service.repertoire.items.song.versions',
                    'service.repertoire.items.songVersion',
                    'service.repertoire.items.minister',
                    'minister',
                ])
                ->where('person_id', $person->id)
                ->where('status', 'published')
                ->whereHas('service', fn ($q) => $q
                    ->where('status', '!=', 'cancelled')
                    ->where('starts_at', '>=', now()->startOfDay())
                    ->where('starts_at', '<=', now()->addDays(90)->endOfDay()))
                ->get()
                ->sortBy(fn ($item) => $item->service->starts_at)
                ->values();

            $activeSwapsByAssignment = SwapRequest::query()
                ->with(['targetPerson'])
                ->where('requester_person_id', $person->id)
                ->where('status', 'requested')
                ->get()
                ->keyBy('schedule_assignment_id');

            $incomingSwaps = SwapRequest::query()
                ->with(['assignment.service', 'requester'])
                ->where('target_person_id', $person->id)
                ->where('status', 'requested')
                ->latest()
                ->get();

            $outgoingSwaps = SwapRequest::query()
                ->with(['assignment.service', 'targetPerson'])
                ->where('requester_person_id', $person->id)
                ->latest()
                ->limit(20)
                ->get();
        }

        $personalFeedUrl = null;
        $personalWebcalUrl = null;
        $googleDirectAvailable = false;
        $googlePersonalIntegration = null;
        if (! $assistedPerson) {
            $feedToken = $request->user()->ensureCalendarFeedToken();
            $personalFeedUrl = route('calendar.feed', ['token' => $feedToken]);
            $personalWebcalUrl = preg_replace('#^https?://#i', 'webcal://', $personalFeedUrl) ?: $personalFeedUrl;
            $googleDirectAvailable = $googleSettings->configured($request->user()->organization_id);
            $googlePersonalIntegration = CalendarIntegration::query()
                ->where('provider', 'google')
                ->where('integration_type', 'personal')
                ->where('user_id', $request->user()->id)
                ->where('status', 'connected')
                ->whereNull('revoked_at')
                ->latest()
                ->first();
        }

        return view('calendar.my-agenda', compact(
            'assignments',
            'activeSwapsByAssignment',
            'incomingSwaps',
            'outgoingSwaps',
            'additionalCommitments',
            'personalFeedUrl',
            'personalWebcalUrl',
            'googleDirectAvailable',
            'googlePersonalIntegration',
            'assistedPerson'
        ));
    }

    public function swapOptions(Request $request, ScheduleAssignment $assignment, AvailabilityService $availability, MinisterAssistanceContext $assistance)
    {
        abort_unless($request->user()->hasPermission('schedule.swap'), 403);

        $person = $assistance->person($request->user());
        abort_unless($person && $assignment->minister_person_id === $person->id, 403);
        abort_unless($assignment->status === 'published', 404);

        $assignment->load(['service.type', 'service.location']);
        abort_if(! $assignment->service || $assignment->service->status === 'cancelled', 404);

        $activeSwap = SwapRequest::query()
            ->with('targetPerson')
            ->where('schedule_assignment_id', $assignment->id)
            ->where('requester_person_id', $person->id)
            ->where('status', 'requested')
            ->first();

        $targets = collect();
        if (! $activeSwap) {
            $targets = Person::query()
                ->where('status', 'active')
                ->where('id', '!=', $person->id)
                ->whereHas('ministryRoles', fn ($query) => $query->where('is_minister_role', true)->where('active', true))
                ->with('user')
                ->orderBy('display_name')
                ->get()
                ->map(function (Person $candidate) use ($assignment, $availability): array {
                    $evaluation = $availability->evaluateForSwap($candidate, $assignment->service);
                    return [
                        'person' => $candidate,
                        'selectable' => (bool) ($evaluation['selectable'] ?? $evaluation['eligible']),
                        'warnings' => $evaluation['warnings'] ?? [],
                        'blocking_reasons' => $evaluation['blocking_reasons'] ?? [],
                    ];
                });
        }

        return view('calendar.swap-options', compact('assignment', 'targets', 'activeSwap'));
    }

    public function programmedSongs(Request $request)
    {
        $from = now()->startOfDay();
        $to = now()->addDays(60)->endOfDay();

        $services = Service::query()
            ->with([
                'type',
                'location',
                'assignment.minister',
                'additionalMinisters' => fn ($query) => $query->where('status', 'published')->with('minister'),
                'repertoire.openingSong.versions',
                'repertoire.openingSongVersion',
                'repertoire.openingMinister',
                'repertoire.items.song.versions',
                'repertoire.items.songVersion',
                'repertoire.items.minister',
            ])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$from, $to])
            ->whereHas('assignment', fn ($query) => $query->where('status', 'published'))
            ->orderBy('starts_at')
            ->get();

        return view('calendar.programmed-songs', compact('services', 'from', 'to'));
    }

    /**
     * RC53: ponto único de assinatura da agenda. A agenda completa do sistema
     * fica disponível a qualquer usuário autenticado; somente pessoas com papel
     * ministerial ativo recebem também a assinatura pessoal. Nenhuma das duas
     * depende de conta Google.
     */
    public function sync(Request $request, MinisterAssistanceContext $assistance)
    {
        $user = $request->user();
        $user->loadMissing('person.ministryRoles');
        $assistedPerson = $assistance->assistedPerson($user);
        $isMinister = (bool) ($user->person?->isMinister());

        $feedToken = $user->ensureCalendarFeedToken();
        $teamFeedUrl = route('calendar.team-feed', ['token' => $feedToken]);
        $teamWebcalUrl = preg_replace('#^https?://#i', 'webcal://', $teamFeedUrl) ?: $teamFeedUrl;

        $personalFeedUrl = null;
        $personalWebcalUrl = null;
        if ($isMinister && ! $assistedPerson) {
            $personalFeedUrl = route('calendar.feed', ['token' => $feedToken]);
            $personalWebcalUrl = preg_replace('#^https?://#i', 'webcal://', $personalFeedUrl) ?: $personalFeedUrl;
        }

        return view('calendar.sync', compact(
            'teamFeedUrl',
            'teamWebcalUrl',
            'personalFeedUrl',
            'personalWebcalUrl',
            'isMinister',
            'assistedPerson'
        ));
    }

    public function serviceDetail(\App\Models\Service $service)
    {
        abort_if($service->status === 'cancelled', 404);
        abort_if($service->special_event_id && auth()->user()?->isMemberWithoutMinistryAccess(), 403);
        if ($service->special_event_id && auth()->user()?->hasPermission('special_events.view')) {
            return redirect()->route('special-events.show', $service->special_event_id);
        }

        $service->load([
            'type',
            'congregation',
            'location',
            'assignment.minister',
            'additionalMinisters.minister',
            'repertoire.openingSong.versions',
            'repertoire.openingSongVersion',
            'repertoire.openingMinister',
            'repertoire.items.song.versions',
            'repertoire.items.songVersion',
            'repertoire.items.minister',
        ]);

        return view('calendar.service-detail', compact('service'));
    }


    public function publishedRepertoire(Request $request, \App\Models\Service $service)
    {
        $assignment = ScheduleAssignment::query()
            ->with(['minister'])
            ->where('service_id', $service->id)
            ->where('status', 'published')
            ->firstOrFail();

        abort_if($service->status === 'cancelled', 404);

        $service->load([
            'type',
            'congregation',
            'location',
            'repertoire.openingSong.versions',
            'repertoire.openingSongVersion',
            'repertoire.openingMinister',
            'repertoire.items.song.category',
            'repertoire.items.song.versions',
            'repertoire.items.songVersion',
            'repertoire.items.minister',
            'additionalMinisters.minister',
        ]);

        $repertoire = $service->repertoire;
        $items = $repertoire?->items ?? collect();

        return view('calendar.published-repertoire', compact('service', 'assignment', 'repertoire', 'items'));
    }

    public function publishedSchedulePdf(Request $request, SchedulePdfService $pdf)
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = Carbon::createFromFormat('Y-m', $data['month'] ?? now()->format('Y-m'))->startOfMonth();
        $assignments = ScheduleAssignment::query()
            ->with([
                'minister',
                'service.type',
                'service.congregation',
                'service.location',
                'service.repertoire.items.song.versions',
                'service.repertoire.openingSong.versions',
                'service.repertoire.openingSongVersion',
                'service.repertoire.openingMinister',
                'service.repertoire.items.songVersion',
                'service.repertoire.items.minister',
                'service.additionalMinisters.minister',
            ])
            ->where('status','published')
            ->whereHas('service', fn($q) => $q
                ->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]))
            ->get()->sortBy(fn($a)=>$a->service->starts_at)->values();

        abort_if($assignments->isEmpty(), 404, 'Ainda não existe escala publicada para este mês.');
        $content = $pdf->render($assignments, $month, $request->user()->organization?->name ?? config('app.name'), 'PUBLICADA');
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="escala-publicada-'.$month->format('Y-m').'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function exportEvent(CalendarEntry $entry, IcsService $ics)
    {
        return response($ics->render(collect([$entry]),$entry->title),200,[
            'Content-Type'=>'text/calendar; charset=utf-8',
            'Content-Disposition'=>'attachment; filename="evento-'.$entry->id.'.ics"'
        ]);
    }

    public function feed(string $token, IcsService $ics, CalendarSubscriptionService $subscriptions)
    {
        $user = \App\Models\User::query()
            ->where('calendar_feed_token', $token)
            ->where('active', true)
            ->firstOrFail();

        $entries = $subscriptions->personal($user);

        return response($ics->render($entries, 'Minha Agenda - OurCloud Louvor'), 200, $this->calendarFeedHeaders('ourcloud-louvor-minha-agenda.ics'));
    }

    public function teamFeed(string $token, IcsService $ics, CalendarSubscriptionService $subscriptions)
    {
        $user = \App\Models\User::query()
            ->where('calendar_feed_token', $token)
            ->where('active', true)
            ->firstOrFail();

        $entries = $subscriptions->team($user);

        return response($ics->render($entries, 'Escala do Ministério - OurCloud Louvor'), 200, $this->calendarFeedHeaders('ourcloud-louvor-agenda-completa.ics'));
    }

    private function calendarFeedHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    public function regenerateFeed(Request $request)
    {
        $request->user()->forceFill(['calendar_feed_token'=>Str::random(80)])->saveQuietly();
        return back()->with('status','Link do calendário regenerado.');
    }
}
