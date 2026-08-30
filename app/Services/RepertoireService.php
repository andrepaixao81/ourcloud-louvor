<?php

namespace App\Services;

use App\Models\Repertoire;
use App\Models\RepertoireItem;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepertoireService
{
    public function __construct(
        private readonly InternalCalendarService $calendar,
        private readonly ServiceHistorySyncService $history,
        private readonly MinisterSongKeyService $ministerSongKeys,
    ) {
    }

    public function forService(Service $service): Repertoire
    {
        return Repertoire::query()->firstOrCreate(
            ['service_id' => $service->id],
            ['organization_id' => $service->organization_id, 'status' => 'draft', 'revision_number' => 1, 'created_by' => auth()->id()]
        );
    }

    public function addItem(
        Repertoire $repertoire,
        string $songId,
        ?string $versionId = null,
        ?string $key = null,
        ?string $youtube = null,
        ?string $notes = null,
        ?string $ministerPersonId = null,
        string $keySource = 'repertoire',
    ): RepertoireItem {
        $song = Song::query()->findOrFail($songId);
        if ($song->organization_id !== $repertoire->organization_id) abort(404);

        $version = null;
        if ($versionId) {
            $version = SongVersion::query()->findOrFail($versionId);
            if ($version->song_id !== $song->id) {
                throw ValidationException::withMessages(['version' => 'A versão selecionada não pertence ao louvor informado.']);
            }
        }
        $ministerPersonId = $this->validateMinister($repertoire, $ministerPersonId);

        $item = DB::transaction(function () use ($repertoire, $song, $version, $key, $youtube, $notes, $ministerPersonId) {
            $position = ((int) $repertoire->items()->max('position')) + 1;
            $item = RepertoireItem::query()->create([
                'organization_id' => $repertoire->organization_id,
                'repertoire_id' => $repertoire->id,
                'song_id' => $song->id,
                'song_version_id' => $version?->id,
                'minister_person_id' => $ministerPersonId,
                'position' => $position,
                'planned_key' => filled($key) ? trim((string) $key) : null,
                'specific_youtube_url' => filled($youtube) ? trim((string) $youtube) : null,
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);
            $this->markChanged($repertoire);
            return $item;
        });

        $this->rememberKey($repertoire, $item->song_id, $ministerPersonId, $key, $keySource, $item->id);
        $this->touchProjections($repertoire);
        return $item;
    }

    public function updateItem(
        Repertoire $repertoire,
        string $itemId,
        ?string $key,
        ?string $youtube,
        ?string $notes,
        ?string $ministerPersonId = null,
        string $keySource = 'repertoire',
    ): RepertoireItem {
        $ministerPersonId = $this->validateMinister($repertoire, $ministerPersonId);

        $item = DB::transaction(function () use ($repertoire, $itemId, $key, $youtube, $notes, $ministerPersonId): RepertoireItem {
            $item = $repertoire->items()->findOrFail($itemId);
            $item->update([
                'minister_person_id' => $ministerPersonId,
                'planned_key' => filled($key) ? trim((string) $key) : null,
                'specific_youtube_url' => filled($youtube) ? trim((string) $youtube) : null,
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);
            $this->markChanged($repertoire);
            return $item;
        });

        $this->rememberKey($repertoire, $item->song_id, $ministerPersonId, $key, $keySource, $item->id);
        $this->touchProjections($repertoire);
        return $item;
    }

    public function updateOpening(
        Repertoire $repertoire,
        ?string $songId,
        ?string $versionId = null,
        ?string $key = null,
        ?string $youtube = null,
        ?string $notes = null,
        ?string $ministerPersonId = null,
        string $keySource = 'repertoire',
    ): void {
        $song = null;
        $version = null;
        if (filled($songId)) {
            $song = Song::query()->findOrFail($songId);
            if ($song->organization_id !== $repertoire->organization_id) abort(404);
            if ($versionId) {
                $version = SongVersion::query()->findOrFail($versionId);
                if ($version->song_id !== $song->id) {
                    throw ValidationException::withMessages(['openingSongId' => 'A versão selecionada não pertence ao louvor de abertura.']);
                }
            }
            $ministerPersonId = $this->validateMinister($repertoire, $ministerPersonId);
        } else {
            $ministerPersonId = null;
        }

        DB::transaction(function () use ($repertoire, $song, $version, $key, $youtube, $notes, $ministerPersonId): void {
            $repertoire->forceFill([
                'opening_song_id' => $song?->id,
                'opening_song_version_id' => $version?->id,
                'opening_minister_person_id' => $ministerPersonId,
                'opening_key' => $song && filled($key) ? trim((string) $key) : null,
                'opening_youtube_url' => $song && filled($youtube) ? trim((string) $youtube) : null,
                'opening_notes' => $song && filled($notes) ? trim((string) $notes) : null,
            ])->save();
            $this->markChanged($repertoire);
        });

        if ($song) {
            $this->rememberKey($repertoire, $song->id, $ministerPersonId, $key, $keySource, null);
        }
        $this->touchProjections($repertoire);
    }

    public function updateFinalNotes(Repertoire $repertoire, ?string $notes): void
    {
        DB::transaction(function () use ($repertoire, $notes): void {
            $repertoire->forceFill(['final_notes' => filled($notes) ? trim((string) $notes) : null])->save();
            $this->markChanged($repertoire);
        });
        $this->touchProjections($repertoire);
    }

    public function removeItem(Repertoire $repertoire, string $itemId): void
    {
        DB::transaction(function () use ($repertoire, $itemId): void {
            $repertoire->items()->findOrFail($itemId)->delete();
            $this->markChanged($repertoire);
        });
        $this->touchProjections($repertoire);
    }

    public function reorder(Repertoire $repertoire, string $itemId, int $position): void
    {
        DB::transaction(function () use ($repertoire, $itemId, $position): void {
            $items = $repertoire->items()->get()->values();
            $moving = $items->firstWhere('id', $itemId);
            if (! $moving) return;
            $items = $items->reject(fn ($item) => $item->id === $itemId)->values();
            $items->splice(max(0, min($position, $items->count())), 0, [$moving]);
            foreach ($items as $index => $item) $item->updateQuietly(['position' => $index + 1]);
            $this->markChanged($repertoire);
        });
        $this->touchProjections($repertoire);
    }

    public function publish(Repertoire $repertoire, ?string $userId): void
    {
        $repertoire->update(['status' => 'published', 'published_by' => $userId, 'published_at' => now(), 'updated_by' => $userId]);
        $this->touchProjections($repertoire);
    }

    /** @return array<string> */
    public function ministerIdsFor(Repertoire $repertoire): array
    {
        $service = Service::query()->with(['assignment', 'additionalMinisters'])->find($repertoire->service_id);
        if (! $service) return [];

        return collect([$service->assignment?->minister_person_id])
            ->merge($service->additionalMinisters->where('status', 'published')->pluck('person_id'))
            ->filter()->unique()->values()->all();
    }

    private function validateMinister(Repertoire $repertoire, ?string $ministerPersonId): ?string
    {
        if (! $ministerPersonId) return null;

        if (! in_array($ministerPersonId, $this->ministerIdsFor($repertoire), true)) {
            throw ValidationException::withMessages([
                'minister' => 'O ministro selecionado não pertence à escala publicada deste culto.',
            ]);
        }

        return $ministerPersonId;
    }

    private function rememberKey(Repertoire $repertoire, string $songId, ?string $ministerPersonId, ?string $key, string $source, ?string $itemId): void
    {
        $this->ministerSongKeys->remember(
            $repertoire->organization_id,
            $ministerPersonId,
            $songId,
            $key,
            $source,
            $repertoire->service_id,
            $itemId,
            auth()->id(),
        );
    }

    private function markChanged(Repertoire $repertoire): void
    {
        $attributes = [
            'revision_number' => (int) $repertoire->revision_number + 1,
            'updated_by' => auth()->id(),
        ];

        // Uma escala publicada não possui etapa adicional de aprovação do repertório.
        // O que o ministro responsável salva passa a valer na agenda imediatamente.
        $service = Service::query()->find($repertoire->service_id);
        if ($service?->assignment()->where('status', 'published')->exists()) {
            $attributes['status'] = 'published';
            $attributes['published_by'] = auth()->id();
            $attributes['published_at'] = now();
        }

        $repertoire->forceFill($attributes)->save();
    }

    private function touchProjections(Repertoire $repertoire): void
    {
        // Uma falha transitória de projeção (agenda/histórico) não deve desfazer o repertório já salvo.
        $service = Service::query()->find($repertoire->service_id);
        if (! $service) return;

        try {
            $this->calendar->syncService($service);
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            // Depois do culto, correções do repertório também corrigem o histórico.
            $this->history->sync($service->fresh());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
