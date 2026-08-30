<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceExecution;
use App\Models\SongExecution;
use Illuminate\Support\Facades\DB;

class ServiceHistorySyncService
{
    public function __construct(private readonly MinisterSongKeyService $ministerSongKeys)
    {
    }

    /**
     * Materializa o repertório vigente como histórico real depois do horário final
     * do culto. O processo é idempotente e continua sincronizável para correções
     * posteriores feitas pelo ministro/gestão no repertório.
     */
    public function sync(Service $service, bool $force = false): bool
    {
        $service->loadMissing([
            'assignment.minister',
            'additionalMinisters.minister',
            'repertoire.openingSong.versions',
            'repertoire.openingSongVersion',
            'repertoire.openingMinister',
            'repertoire.items.song.versions',
            'repertoire.items.songVersion',
            'repertoire.items.minister',
            'execution.songs',
        ]);

        if ($service->status === 'cancelled' || $service->trashed()) return false;
        if (! $service->assignment || $service->assignment->status !== 'published') return false;

        $endedAt = $service->ends_at ?: $service->starts_at->copy()->addHours(2);
        if (! $force && $endedAt->isFuture()) return false;

        $repertoire = $service->repertoire;
        if (! $repertoire) return false;

        $execution = $service->execution;
        if (! $force && $execution?->auto_finalized_at
            && (int) $execution->repertoire_revision_snapshot === (int) $repertoire->revision_number) {
            return false;
        }
        if ($execution && ! $execution->auto_finalized_at && $execution->songs()->exists()) {
            return false;
        }

        DB::transaction(function () use ($service, $endedAt, $repertoire, &$execution): void {
            $execution = ServiceExecution::query()->firstOrCreate(
                ['service_id' => $service->id],
                [
                    'organization_id' => $service->organization_id,
                    'status' => 'finished',
                    'started_at' => $service->starts_at,
                    'finished_at' => $endedAt,
                ]
            );

            $execution->forceFill([
                'status' => 'finished',
                'started_at' => $execution->started_at ?: $service->starts_at,
                'finished_at' => $endedAt,
                'auto_finalized_at' => now(),
                'repertoire_revision_snapshot' => (int) $repertoire->revision_number,
                'notes' => filled($repertoire->final_notes) ? trim((string) $repertoire->final_notes) : $execution->notes,
            ])->saveQuietly();

            SongExecution::query()
                ->where('service_execution_id', $execution->id)
                ->whereIn('execution_source', ['repertoire_auto', 'opening_auto'])
                ->delete();

            $position = 1;
            $defaultPerformerId = $service->assignment?->minister_person_id;

            if ($repertoire->openingSong) {
                $openingPerformerId = $repertoire->opening_minister_person_id ?: $defaultPerformerId;
                $opening = SongExecution::query()->create([
                    'organization_id' => $service->organization_id,
                    'service_execution_id' => $execution->id,
                    'song_id' => $repertoire->opening_song_id,
                    'song_version_id' => $repertoire->opening_song_version_id,
                    'song_title_snapshot' => $repertoire->openingSong->title,
                    'position' => $position++,
                    'planned_key' => $repertoire->opening_key,
                    'performed_key' => $repertoire->opening_key,
                    'started_at' => $service->starts_at,
                    'finished_at' => $endedAt,
                    'execution_source' => 'opening_auto',
                    'notes' => $repertoire->opening_notes,
                    'performed_by_person_id' => $openingPerformerId,
                    'recorded_by' => $repertoire->updated_by ?: $repertoire->created_by,
                ]);
                $this->rememberExecution($service, $opening, $endedAt);
            }

            foreach ($repertoire->items as $item) {
                $defaultVersion = $item->song?->versions?->firstWhere('is_default', true) ?? $item->song?->versions?->first();
                $key = $item->planned_key ?: $item->songVersion?->original_key ?: $defaultVersion?->original_key;
                $performedBy = $item->minister_person_id ?: $defaultPerformerId;
                $songExecution = SongExecution::query()->create([
                    'organization_id' => $service->organization_id,
                    'service_execution_id' => $execution->id,
                    'song_id' => $item->song_id,
                    'song_version_id' => $item->song_version_id,
                    'repertoire_item_id' => $item->id,
                    'song_title_snapshot' => $item->song?->title,
                    'position' => $position++,
                    'planned_key' => $key,
                    'performed_key' => $key,
                    'started_at' => $service->starts_at,
                    'finished_at' => $endedAt,
                    'execution_source' => 'repertoire_auto',
                    'notes' => $item->notes,
                    'performed_by_person_id' => $performedBy,
                    'recorded_by' => $repertoire->updated_by ?: $repertoire->created_by,
                ]);
                $this->rememberExecution($service, $songExecution, $endedAt);
            }

            if ($service->status !== 'completed') {
                $service->forceFill(['status' => 'completed'])->saveQuietly();
            }
        });

        return true;
    }

    public function syncDue(int $limit = 250): int
    {
        $services = Service::query()->withoutGlobalScopes()
            ->with(['assignment', 'repertoire'])
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->whereHas('assignment', fn ($q) => $q->where('status', 'published'))
            ->whereHas('repertoire')
            ->where(function ($q): void {
                $q->where('ends_at', '<=', now())
                    ->orWhere(function ($openEnded): void {
                        $openEnded->whereNull('ends_at')->where('starts_at', '<=', now()->subHours(2));
                    });
            })
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($services as $service) {
            if ($this->sync($service)) $count++;
        }
        return $count;
    }

    private function rememberExecution(Service $service, SongExecution $execution, mixed $usedAt): void
    {
        $this->ministerSongKeys->remember(
            $service->organization_id,
            $execution->performed_by_person_id,
            $execution->song_id,
            $execution->performed_key,
            'history_auto',
            $service->id,
            $execution->repertoire_item_id,
            $execution->recorded_by,
            $usedAt,
        );
    }
}
