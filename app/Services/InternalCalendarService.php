<?php

namespace App\Services;

use App\Jobs\SyncGoogleCalendarEventJob;
use App\Models\CalendarEntry;
use App\Models\CalendarIntegration;
use App\Models\ExternalCalendarSync;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class InternalCalendarService
{
    public function __construct(private readonly CalendarEventContentService $content)
    {
    }

    public function syncService(Service $service, bool $queueExternal = true): CalendarEntry
    {
        $service->loadMissing([
            'assignment.minister',
            'additionalMinisters.minister',
            'repertoire.openingSong.versions',
            'repertoire.openingSongVersion',
            'repertoire.items.song.versions',
            'repertoire.items.songVersion',
        ]);

        $entry = CalendarEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $service->organization_id)
            ->where('source_type', 'service')
            ->where('source_id', $service->id)
            ->first();

        if (! $entry) {
            $entry = new CalendarEntry([
                'organization_id' => $service->organization_id,
                'source_type' => 'service',
                'source_id' => $service->id,
                'created_by' => $service->created_by,
            ]);
        } elseif ($entry->trashed()) {
            $entry->restoreQuietly();
        }

        $entry->forceFill([
            'title' => $service->name,
            // RC44: a projeção externa recebe exatamente o conteúdo publicado
            // atual (ministros + repertório). Assim alterações de louvores/tom/nota
            // entram também na sincronização direta com Google Agenda.
            'description' => $this->content->descriptionFor($service),
            'starts_at' => $service->starts_at,
            'ends_at' => $service->ends_at,
            'timezone' => $service->timezone,
            'location_id' => $service->location_id,
            'status' => $service->status === 'cancelled' ? 'cancelled' : ($service->status === 'completed' ? 'completed' : 'scheduled'),
            'visibility' => 'public_internal',
            'updated_by' => $service->updated_by,
        ])->saveQuietly();

        if ($queueExternal) {
            $this->queueExternalSync($entry);
        }
        return $entry;
    }

    /**
     * RC48: a agenda interna é a fonte acessível a qualquer usuário, com ou sem
     * conta Google. Antes de renderizar uma janela do calendário, reconciliamos
     * as projeções dos cultos diretamente com o estado atual do sistema.
     */
    public function refreshRange(CarbonInterface $from, CarbonInterface $to): void
    {
        $organizationId = auth()->user()?->organization_id;
        $scope = ($organizationId ?: 'system').':'.$from->format('Ymd').':'.$to->format('Ymd');
        $freshnessKey = 'calendar:refresh:fresh:'.$scope;

        // RC82: múltiplos acessos simultâneos reutilizam a mesma projeção recente.
        // O lock evita que dezenas de requisições executem a reconciliação ao mesmo tempo.
        if (Cache::has($freshnessKey)) return;

        $lock = Cache::lock('calendar:refresh:lock:'.$scope, 30);
        if (! $lock->get()) return;

        try {
            if (Cache::has($freshnessKey)) return;

            Service::query()
                ->with([
                    'assignment.minister',
                    'additionalMinisters.minister',
                    'repertoire.openingSong.versions',
                    'repertoire.openingSongVersion',
                    'repertoire.items.song.versions',
                    'repertoire.items.songVersion',
                ])
                ->where('starts_at', '<=', $to)
                ->where(function ($query) use ($from): void {
                    $query->where(function ($q) use ($from): void {
                        $q->whereNull('ends_at')->where('starts_at', '>=', $from);
                    })->orWhere('ends_at', '>=', $from);
                })
                ->get()
                ->each(fn (Service $service) => $this->syncService($service, false));

            Cache::put($freshnessKey, true, now()->addSeconds(20));
        } finally {
            $lock->release();
        }
    }

    public function deleteServiceProjection(Service $service, ?string $updatedBy = null): void
    {
        $entry = CalendarEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $service->organization_id)
            ->where('source_type', 'service')
            ->where('source_id', $service->id)
            ->first();

        if (! $entry) return;

        $entry->forceFill([
            'status' => 'cancelled',
            'updated_by' => $updatedBy,
        ])->saveQuietly();
        $this->queueExternalSync($entry);
        $entry->deleteQuietly();
    }

    public function queueExternalSync(CalendarEntry $entry): void
    {
        $integrations = CalendarIntegration::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('provider', 'google')
            ->where('status', 'connected')
            ->whereNull('revoked_at')
            ->get();

        foreach ($integrations as $integration) {
            $sync = ExternalCalendarSync::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $entry->organization_id, 'calendar_entry_id' => $entry->id, 'integration_id' => $integration->id],
                ['sync_status' => 'pending', 'retry_count' => 0, 'last_error' => null]
            );
            SyncGoogleCalendarEventJob::dispatch($sync->id)->afterCommit();
        }
    }
}
