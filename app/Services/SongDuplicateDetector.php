<?php

namespace App\Services;

use App\Models\Song;
use Illuminate\Support\Collection;

class SongDuplicateDetector
{
    public function __construct(private SongTitleNormalizer $normalizer) {}

    public function find(string $title, ?string $artist = null): Collection
    {
        $normalized = $this->normalizer->normalize($title);
        return Song::query()
            ->where(function ($q) use ($normalized) {
                $q->where('normalized_title', $normalized)
                  ->orWhere('normalized_title', 'like', '%'.$normalized.'%')
                  ->orWhere('normalized_alternative_title', $normalized);
            })
            ->when($artist, fn ($q) => $q->orderByRaw('CASE WHEN lower(artist_reference) = lower(?) THEN 0 ELSE 1 END', [$artist]))
            ->limit(10)
            ->get();
    }
}
