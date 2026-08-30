<?php

namespace App\Services;

use Illuminate\Support\Str;

class SongTitleNormalizer
{
    public function normalize(string $title): string
    {
        $title = Str::ascii(Str::lower(trim($title)));
        $title = preg_replace('/[^a-z0-9\s]/u', ' ', $title) ?? $title;
        return trim(preg_replace('/\s+/', ' ', $title) ?? $title);
    }
}
