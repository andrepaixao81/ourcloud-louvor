<?php

namespace App\Services;

use App\Models\CalendarEntry;
use App\Models\CalendarFeedState;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceAdditionalMinister;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarSubscriptionService
{
    public function __construct(
        private readonly CalendarEventContentService $content,
        private readonly InternalCalendarService $internalCalendar,
    ) {
    }

    public function personal(User $user): Collection
    {
        $from = now()->subDay()->startOfDay();
        $to = now()->addMonths(6)->endOfDay();
        $person = $user->person;

        $currentIds = collect();
        if ($person) {
            $publishedServiceIds = ScheduleAssignment::query()->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->where('status', 'published')
                ->pluck('service_id');

            $primaryIds = ScheduleAssignment::query()->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->where('minister_person_id', $person->id)
                ->where('status', 'published')
                ->pluck('service_id');

            $additionalIds = ServiceAdditionalMinister::query()->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->where('person_id', $person->id)
                ->where('status', 'published')
                ->whereIn('service_id', $publishedServiceIds)
                ->pluck('service_id');

            $currentIds = $primaryIds->merge($additionalIds)->unique()->values();
        }

        return $this->build($user, 'personal', $currentIds, $from, $to);
    }

    public function team(User $user): Collection
    {
        $from = now()->subDay()->startOfDay();
        $to = now()->addMonths(12)->endOfDay();

        // A agenda completa nasce exclusivamente da escala principal PUBLICADA.
        // Ministros adicionais entram no conteúdo do evento, mas não conseguem
        // manter um culto fantasma no feed se a escala principal for revogada.
        $currentIds = ScheduleAssignment::query()->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'published')
            ->pluck('service_id')
            ->unique()->values();

        // Eventos especiais entram na Agenda do sistema mesmo antes da definição
        // do ministro, pois a equipe precisa vê-los para confirmar participação.
        // O perfil Membro nunca recebe esses eventos.
        if (! $user->isMemberWithoutMinistryAccess()) {
            $specialEventIds = Service::query()->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->whereNotNull('special_event_id')
                ->whereNotIn('status', ['cancelled'])
                ->whereNull('deleted_at')
                ->whereBetween('starts_at', [$from, $to])
                ->pluck('id');
            $currentIds = $currentIds->merge($specialEventIds)->unique()->values();
        } else {
            $specialEventIds = Service::query()->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->whereNotNull('special_event_id')
                ->pluck('id');
            $currentIds = $currentIds->diff($specialEventIds)->values();
        }

        $publishedServices = $this->build($user, 'team', $currentIds, $from, $to);

        // RC53: "Agenda do sistema" acompanha também eventos internos públicos
        // criados diretamente no Calendário, além dos cultos da escala publicada.
        // Itens restritos/privados não são expostos na assinatura compartilhada.
        $publicInternalEvents = CalendarEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('source_type', 'custom')
            ->where('visibility', 'public_internal')
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->where('starts_at', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('ends_at')
                    ->where('starts_at', '>=', $from)
                    ->orWhere('ends_at', '>=', $from);
            })
            ->get()
            ->each(function (CalendarEntry $entry): void {
                $entry->setAttribute('ics_sequence', 0);
                $entry->setAttribute('ics_last_modified', $entry->updated_at ?: now());
            });

        return $publishedServices
            ->concat($publicInternalEvents)
            ->sortBy(fn (CalendarEntry $entry) => $entry->starts_at)
            ->values();
    }

    private function build(User $user, string $scope, Collection $candidateCurrentIds, Carbon $from, Carbon $to): Collection
    {
        $services = Service::query()->withoutGlobalScopes()
            ->with([
                'assignment.minister',
                'additionalMinisters.minister',
                'repertoire.openingSong.versions',
                'repertoire.openingSongVersion',
                'repertoire.items.song.versions',
                'repertoire.items.songVersion',
            ])
            ->where('organization_id', $user->organization_id)
            ->whereIn('id', $candidateCurrentIds)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->whereBetween('starts_at', [$from, $to])
            ->get()
            ->keyBy('id');

        // RC44: o feed continua sendo um retrato do estado PUBLICADO atual. O
        // conteúdo é recalculado em cada GET, inclusive louvores, tons e notas.
        $currentIds = $services->keys()->values();

        $calendarEntries = CalendarEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('source_type', 'service')
            ->whereIn('source_id', $currentIds)
            ->get()
            ->keyBy('source_id');

        $currentEvents = collect();
        foreach ($services as $service) {
            $entry = $calendarEntries->get($service->id);
            if (! $entry) {
                // RC53: a assinatura não pode depender de alguém ter aberto antes
                // o calendário interno. Se a projeção estiver ausente por dado
                // legado ou publicação recém-criada, ela é reconstruída na hora.
                $entry = $this->internalCalendar->syncService($service, false);
            }
            $currentEvents->push($this->decorateCurrentEntry($entry, $service));
        }

        return $this->synchronizeStates($user, $scope, $currentEvents, $from, $to);
    }

    private function decorateCurrentEntry(CalendarEntry $entry, Service $service): CalendarEntry
    {
        $event = clone $entry;
        $event->setAttribute('title', $service->name);
        $event->setAttribute('starts_at', $service->starts_at);
        $event->setAttribute('ends_at', $service->ends_at);
        $event->setAttribute('timezone', $service->timezone);
        $event->setAttribute('status', $service->status === 'completed' ? 'completed' : 'scheduled');
        $event->setAttribute('description', $this->content->descriptionFor($service));
        $event->setAttribute('ics_content_hash', $this->contentHash($event));
        return $event;
    }

    private function contentHash(CalendarEntry $entry): string
    {
        return hash('sha256', json_encode([
            'title' => $entry->title,
            'description' => $entry->description,
            'starts_at' => optional($entry->starts_at)->toIso8601String(),
            'ends_at' => optional($entry->ends_at)->toIso8601String(),
            'status' => $entry->status,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function synchronizeStates(User $user, string $scope, Collection $currentEvents, Carbon $from, Carbon $to): Collection
    {
        $states = CalendarFeedState::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->get()
            ->keyBy('service_id');

        $currentServiceIds = $currentEvents->pluck('source_id')->filter()->values();
        $result = collect();

        foreach ($currentEvents as $event) {
            $serviceId = (string) $event->source_id;
            $hash = (string) $event->getAttribute('ics_content_hash');
            $state = $states->get($serviceId);

            if (! $state) {
                $state = CalendarFeedState::query()->create([
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'scope' => $scope,
                    'service_id' => $serviceId,
                    'calendar_entry_id' => $event->id,
                    'event_starts_at' => $event->starts_at,
                    'status' => 'active',
                    'content_hash' => $hash,
                    'sequence' => 1,
                    'last_published_at' => now(),
                ]);
                $states->put($serviceId, $state);
            } else {
                $changed = $state->status !== 'active'
                    || $state->content_hash !== $hash
                    || $state->calendar_entry_id !== $event->id;

                if ($changed) {
                    $state->forceFill([
                        'calendar_entry_id' => $event->id,
                        'event_starts_at' => $event->starts_at,
                        'status' => 'active',
                        'content_hash' => $hash,
                        'sequence' => (int) $state->sequence + 1,
                        'last_published_at' => now(),
                        'cancelled_at' => null,
                    ])->save();
                }
            }

            $event->setAttribute('ics_sequence', (int) $state->sequence);
            $event->setAttribute('ics_last_modified', $state->updated_at ?: now());
            $result->push($event);
        }

        // O evento NÃO é emitido quando deixa de estar publicado; apenas o
        // estado interno é encerrado para preservar sequência/auditoria do feed.
        CalendarFeedState::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->where('status', 'active')
            ->whereBetween('event_starts_at', [$from, $to])
            ->when($currentServiceIds->isNotEmpty(), fn ($q) => $q->whereNotIn('service_id', $currentServiceIds))
            ->get()
            ->each(function (CalendarFeedState $state): void {
                $state->forceFill([
                    'status' => 'cancelled',
                    'sequence' => (int) $state->sequence + 1,
                    'cancelled_at' => now(),
                    'last_published_at' => now(),
                ])->save();
            });

        return $result->sortBy(fn (CalendarEntry $entry) => $entry->starts_at)->values();
    }
}
