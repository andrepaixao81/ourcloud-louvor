<?php

namespace App\Services;

use App\Models\MinisterSongKey;
use App\Models\SongExecution;

class MinisterSongKeyService
{
    public function preferredKey(?string $personId, ?string $songId): ?string
    {
        if (! $personId || ! $songId) return null;

        $stored = MinisterSongKey::query()
            ->where('person_id', $personId)
            ->where('song_id', $songId)
            ->first();

        if ($stored && filled($stored->preferred_key)) {
            return trim((string) $stored->preferred_key);
        }

        // Fallback para bancos que ainda não tiveram o histórico materializado na
        // tabela de preferência (por exemplo, durante uma atualização interrompida).
        $historyKey = SongExecution::query()
            ->where('performed_by_person_id', $personId)
            ->where('song_id', $songId)
            ->whereNotNull('performed_key')
            ->latest('created_at')
            ->value('performed_key');

        return filled($historyKey) ? trim((string) $historyKey) : null;
    }

    public function preference(?string $personId, ?string $songId): ?MinisterSongKey
    {
        if (! $personId || ! $songId) return null;

        return MinisterSongKey::query()
            ->where('person_id', $personId)
            ->where('song_id', $songId)
            ->first();
    }

    public function remember(
        string $organizationId,
        ?string $personId,
        ?string $songId,
        ?string $key,
        string $source,
        ?string $serviceId = null,
        ?string $repertoireItemId = null,
        ?string $userId = null,
        mixed $usedAt = null,
    ): ?MinisterSongKey {
        $normalizedKey = filled($key) ? trim((string) $key) : '';
        if (! $personId || ! $songId || $normalizedKey === '') return null;

        return MinisterSongKey::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'person_id' => $personId,
                'song_id' => $songId,
            ],
            [
                'preferred_key' => $normalizedKey,
                'source' => $source,
                'last_service_id' => $serviceId,
                'last_repertoire_item_id' => $repertoireItemId,
                'updated_by' => $userId,
                'last_used_at' => $usedAt ?: now(),
            ]
        );
    }
}
