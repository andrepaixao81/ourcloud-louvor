<?php

namespace App\Services;

use App\Models\RepertoireItem;
use App\Models\Service;
use App\Models\ServiceExecution;
use App\Models\Song;
use App\Models\SongExecution;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecutionService
{
    public function __construct(private readonly MinisterSongKeyService $ministerSongKeys)
    {
    }

    public function start(Service $service): ServiceExecution
    {
        return DB::transaction(function () use ($service) {
            $execution = ServiceExecution::query()->firstOrCreate(
                ['service_id' => $service->id],
                ['organization_id' => $service->organization_id, 'status' => 'not_started', 'opened_by' => auth()->id()]
            );

            if (in_array($execution->status, ['finished', 'interrupted'], true)) {
                throw new RuntimeException('Execução já encerrada.');
            }

            $execution->update([
                'status' => 'in_progress',
                'started_at' => $execution->started_at ?: now(),
                'opened_by' => $execution->opened_by ?: auth()->id(),
            ]);
            $service->update(['status' => 'in_progress', 'updated_by' => auth()->id()]);

            return $execution;
        });
    }

    public function performFromRepertoire(ServiceExecution $execution, RepertoireItem $item, ?string $performedKey = null, ?string $performedByPersonId = null): SongExecution
    {
        if ($execution->status !== 'in_progress') {
            throw new RuntimeException('A ministração precisa estar em andamento.');
        }

        $execution->loadMissing('service.repertoire');
        if (! $execution->service?->repertoire || $item->repertoire_id !== $execution->service->repertoire->id) {
            abort(404);
        }

        $existing = SongExecution::query()
            ->where('service_execution_id', $execution->id)
            ->where('repertoire_item_id', $item->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $performedByPersonId = $performedByPersonId ?: $item->minister_person_id;
        $effectiveKey = filled($performedKey) ? trim((string) $performedKey) : $item->planned_key;
        if (! filled($effectiveKey) && $performedByPersonId) {
            $effectiveKey = $this->ministerSongKeys->preferredKey($performedByPersonId, $item->song_id);
        }

        $songExecution = SongExecution::query()->create([
            'organization_id' => $execution->organization_id,
            'service_execution_id' => $execution->id,
            'song_id' => $item->song_id,
            'song_version_id' => $item->song_version_id,
            'repertoire_item_id' => $item->id,
            'position' => $this->nextPosition($execution),
            'planned_key' => $item->planned_key,
            'performed_key' => filled($effectiveKey) ? trim((string) $effectiveKey) : null,
            'performed_by_person_id' => $performedByPersonId,
            'execution_source' => 'repertoire',
            'recorded_by' => auth()->id(),
        ]);
        $this->rememberExecution($execution, $songExecution, 'execution');
        return $songExecution;
    }

    public function performSong(ServiceExecution $execution, Song $song, ?string $performedKey = null, ?string $performedByPersonId = null): SongExecution
    {
        if ($execution->status !== 'in_progress') {
            throw new RuntimeException('A ministração precisa estar em andamento.');
        }
        if ($song->organization_id !== $execution->organization_id) {
            abort(404);
        }

        $effectiveKey = filled($performedKey) ? trim((string) $performedKey) : null;
        if (! $effectiveKey && $performedByPersonId) {
            $effectiveKey = $this->ministerSongKeys->preferredKey($performedByPersonId, $song->id);
        }
        $songExecution = SongExecution::query()->create([
            'organization_id' => $execution->organization_id,
            'service_execution_id' => $execution->id,
            'song_id' => $song->id,
            'position' => $this->nextPosition($execution),
            'performed_key' => $effectiveKey,
            'performed_by_person_id' => $performedByPersonId,
            'execution_source' => 'added_during_service',
            'recorded_by' => auth()->id(),
        ]);
        $this->rememberExecution($execution, $songExecution, 'execution');
        return $songExecution;
    }

    public function correctSongExecution(ServiceExecution $execution, SongExecution $songExecution, ?string $performedKey): void
    {
        if ($songExecution->service_execution_id !== $execution->id) {
            abort(404);
        }

        $songExecution->update([
            'performed_key' => filled($performedKey) ? trim((string) $performedKey) : null,
        ]);
    }

    public function rememberPerformedKey(ServiceExecution $execution, SongExecution $songExecution, string $source = 'execution_correction'): void
    {
        $this->rememberExecution($execution, $songExecution, $source);
    }

    public function removeSongExecution(ServiceExecution $execution, SongExecution $songExecution): void
    {
        if ($songExecution->service_execution_id !== $execution->id) {
            abort(404);
        }

        DB::transaction(function () use ($execution, $songExecution): void {
            $songExecution->delete();

            $remaining = SongExecution::query()
                ->where('service_execution_id', $execution->id)
                ->orderBy('position')
                ->orderBy('created_at')
                ->get();

            foreach ($remaining as $index => $item) {
                $newPosition = $index + 1;
                if ((int) $item->position !== $newPosition) {
                    $item->update(['position' => $newPosition]);
                }
            }
        });
    }

    public function finish(ServiceExecution $execution): void
    {
        if (! in_array($execution->status, ['in_progress', 'reopened'], true)) {
            throw new RuntimeException('A ministração não está em andamento.');
        }

        DB::transaction(function () use ($execution): void {
            $execution->update(['status' => 'finished', 'finished_at' => now(), 'closed_by' => auth()->id()]);
            $execution->service()->update(['status' => 'completed', 'updated_by' => auth()->id()]);
        });
    }

    public function cancelOpening(ServiceExecution $execution): void
    {
        if (! in_array($execution->status, ['not_started', 'in_progress'], true)) {
            throw new RuntimeException('O Modo Culto não pode ser cancelado neste estado.');
        }

        $hasEffectiveRecords = $execution->songs()->exists()
            || $execution->participants()->exists()
            || $execution->ministers()->exists();

        if ($hasEffectiveRecords) {
            throw new RuntimeException('Já existem registros efetivos no Modo Culto. Remova/corrija esses registros administrativamente antes de cancelar a abertura.');
        }

        DB::transaction(function () use ($execution): void {
            $execution->update([
                'status' => 'not_started',
                'started_at' => null,
                'finished_at' => null,
                'opened_by' => null,
                'closed_by' => null,
            ]);

            if (! in_array($execution->service?->status, ['cancelled', 'completed'], true)) {
                $execution->service()->update(['status' => 'scheduled', 'updated_by' => auth()->id()]);
            }
        });
    }

    private function rememberExecution(ServiceExecution $execution, SongExecution $songExecution, string $source): void
    {
        $execution->loadMissing('service');
        $this->ministerSongKeys->remember(
            $execution->organization_id,
            $songExecution->performed_by_person_id,
            $songExecution->song_id,
            $songExecution->performed_key,
            $source,
            $execution->service?->id,
            $songExecution->repertoire_item_id,
            auth()->id(),
            now(),
        );
    }

    private function nextPosition(ServiceExecution $execution): int
    {
        return ((int) $execution->songs()->max('position')) + 1;
    }
}
