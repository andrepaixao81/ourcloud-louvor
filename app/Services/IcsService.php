<?php

namespace App\Services;

use Illuminate\Support\Collection;

class IcsService
{
    public function render(Collection $entries, string $name = 'OurCloud Louvor'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OurCloud//OurCloud Louvor//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-PUBLISHED-TTL:PT1M',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1M',
            'X-WR-CALNAME:'.$this->escape($name),
        ];

        foreach ($entries as $entry) {
            $lastModified = $entry->getAttribute('ics_last_modified') ?: $entry->updated_at ?: now();
            $sequence = max(0, (int) ($entry->getAttribute('ics_sequence') ?? 0));

            $lines[] = 'BEGIN:VEVENT';
            // Compatibilidade com assinaturas já existentes: o UID histórico é
            // mantido para que atualizações e tombstones CANCELLED substituam o
            // mesmo evento no calendário do celular, em vez de criar duplicatas.
            $lines[] = 'UID:'.$entry->id.'@sgml';
            $lines[] = 'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z');
            $lines[] = 'LAST-MODIFIED:'.$lastModified->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SEQUENCE:'.$sequence;
            $lines[] = 'DTSTART:'.$entry->starts_at->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.($entry->ends_at ?: $entry->starts_at->copy()->addHours(2))->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escape($entry->title);
            if ($entry->description) $lines[] = 'DESCRIPTION:'.$this->escape($entry->description);
            $lines[] = 'STATUS:'.($entry->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", "\r\n", "\r", "\n", ",", ";"], ["\\\\", "\\n", "\\n", "\\n", "\\,", "\\;"], $value);
    }
}
