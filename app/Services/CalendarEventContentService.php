<?php

namespace App\Services;

use App\Models\Service;

class CalendarEventContentService
{
    public function descriptionFor(Service $service): string
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
            'specialEvent.responses.person',
        ]);

        $primary = $service->assignment?->status === 'published' ? $service->assignment?->minister?->display_name : null;
        $additional = $service->additionalMinisters->where('status', 'published')->pluck('minister.display_name')->filter();
        $ministers = collect([$primary])->filter()->merge($additional)->unique()->values();
        $lines = [];

        if ($service->special_event_id) {
            if (filled($service->description)) {
                $lines[] = 'Evento especial: '.$this->oneLine((string) $service->description);
            }
            $confirmed = $service->specialEvent?->responses?->where('response', 'yes')->pluck('person.display_name')->filter()->values() ?? collect();
            if ($confirmed->isNotEmpty()) {
                $lines[] = 'Participantes confirmados: '.$confirmed->implode(', ');
            }
        }

        if ($ministers->isNotEmpty()) {
            $lines[] = ($ministers->count() > 1 ? 'Ministros: ' : 'Ministro: ').$ministers->implode(', ');
        }

        $repertoire = $service->repertoire;
        if ($repertoire?->openingSong) {
            $defaultVersion = $repertoire->openingSong->versions?->firstWhere('is_default', true) ?? $repertoire->openingSong->versions?->first();
            $key = $repertoire->opening_key ?: $repertoire->openingSongVersion?->original_key ?: $defaultVersion?->original_key;
            $line = 'Louvor de abertura: '.$repertoire->openingSong->title;
            if ($repertoire->openingMinister) $line .= ' - Ministro: '.$repertoire->openingMinister->display_name;
            if (filled($key)) $line .= ' - Tom: '.trim((string) $key);
            if (filled($repertoire->opening_notes)) $line .= ' - Nota: '.$this->oneLine($repertoire->opening_notes);
            $lines[] = $line;
        }

        $items = $repertoire?->items ?? collect();
        if ($items->isNotEmpty()) {
            $lines[] = 'Louvores:';
            foreach ($items as $index => $item) {
                $defaultVersion = $item->song?->versions?->firstWhere('is_default', true) ?? $item->song?->versions?->first();
                $key = $item->planned_key ?: $item->songVersion?->original_key ?: $defaultVersion?->original_key;
                $line = 'Louvor '.($index + 1).': '.($item->song?->title ?: 'Louvor');
                if ($item->minister) $line .= ' - Ministro: '.$item->minister->display_name;
                if (filled($key)) $line .= ' - Tom: '.trim((string) $key);
                if (filled($item->notes)) $line .= ' - Nota: '.$this->oneLine($item->notes);
                $lines[] = $line;
            }
        }

        if (filled($repertoire?->final_notes)) {
            $lines[] = 'Observações: '.$this->oneLine($repertoire->final_notes);
        }

        return implode("\n", $lines);
    }

    private function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
