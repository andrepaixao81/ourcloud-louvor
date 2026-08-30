<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulePdfService
{
    public function render(Collection $assignments, Carbon $month, string $organizationName, string $statusLabel): string
    {
        $lines = [];
        $lines[] = ['text' => 'SGML - Escala Ministerial', 'bold' => true, 'size' => 16];
        $lines[] = ['text' => $organizationName, 'bold' => true, 'size' => 11];
        $lines[] = ['text' => 'Período: '.$this->monthLabel($month).' | Situação: '.$statusLabel, 'bold' => false, 'size' => 10];
        $lines[] = ['text' => 'Gerado em '.now()->format('d/m/Y H:i'), 'bold' => false, 'size' => 9];
        $lines[] = ['text' => '', 'bold' => false, 'size' => 9];

        foreach ($assignments as $assignment) {
            $service = $assignment->service;
            $ministerNames = collect([$assignment->minister?->display_name])->filter()->merge($service->additionalMinisters?->pluck('minister.display_name')->filter() ?? collect())->values();
            $minister = $ministerNames->isNotEmpty() ? $ministerNames->implode(', ') : 'Não definido';
            $place = collect([$service->congregation?->name, $service->location?->name])->filter()->implode(' - ');

            $lines[] = ['text' => $service->starts_at->format('d/m/Y H:i').' - '.$service->name, 'bold' => true, 'size' => 11];
            $lines[] = ['text' => ($ministerNames->count() > 1 ? 'Ministros: ' : 'Ministro: ').$minister.($place !== '' ? ' | '.$place : ''), 'bold' => false, 'size' => 9];

            $repertoire = $service->repertoire;
            if ($repertoire && ($repertoire->openingSong || $repertoire->items->isNotEmpty())) {
                $repertoireLabel = $repertoire->status === 'published'
                    ? 'Repertório:'
                    : 'Repertório planejado (em preparação - sujeito a alterações):';
                $lines[] = ['text' => $repertoireLabel, 'bold' => true, 'size' => 9];

                if ($repertoire->openingSong) {
                    $openingKey = $repertoire->opening_key ?: $repertoire->openingSongVersion?->original_key ?: $repertoire->openingSong?->versions?->firstWhere('is_default', true)?->original_key;
                    $openingLine = '  Abertura: '.$repertoire->openingSong->title;
                    if ($repertoire->openingMinister) $openingLine .= ' | Ministro '.$repertoire->openingMinister->display_name;
                    if ($openingKey) $openingLine .= ' | Tom '.$openingKey;
                    if ($repertoire->opening_notes) $openingLine .= ' | Nota: '.$repertoire->opening_notes;
                    $lines[] = ['text' => $openingLine, 'bold' => false, 'size' => 9];
                }

                foreach ($repertoire->items as $index => $item) {
                    $key = $item->planned_key ?: $item->songVersion?->original_key;
                    if (! $key) {
                        $defaultVersion = $item->song?->versions?->firstWhere('is_default', true);
                        $key = $defaultVersion?->original_key;
                    }
                    $artist = $item->song?->artist_reference;
                    $songLine = '  Louvor '.($index + 1).': '.($item->song?->title ?: 'Louvor');
                    if ($item->minister) $songLine .= ' | Ministro '.$item->minister->display_name;
                    if ($key) $songLine .= ' | Tom '.$key;
                    if ($artist) $songLine .= ' | '.$artist;
                    if ($item->notes) $songLine .= ' | Nota: '.$item->notes;
                    $lines[] = ['text' => $songLine, 'bold' => false, 'size' => 9];
                }
                if ($repertoire->final_notes) {
                    $lines[] = ['text' => '  Observações: '.$repertoire->final_notes, 'bold' => false, 'size' => 9];
                }
            } else {
                $lines[] = ['text' => 'Repertório: nenhum louvor planejado até o momento.', 'bold' => false, 'size' => 9];
            }
            $lines[] = ['text' => '', 'bold' => false, 'size' => 8];
        }

        if ($assignments->isEmpty()) {
            $lines[] = ['text' => 'Nenhuma atribuição encontrada para este período.', 'bold' => false, 'size' => 10];
        }

        return $this->buildPdf($lines);
    }

    private function monthLabel(Carbon $month): string
    {
        $months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
        return $months[$month->month].' '.$month->year;
    }

    /** @param array<int,array{text:string,bold:bool,size:int}> $lines */
    private function buildPdf(array $lines): string
    {
        $pages = [];
        $current = [];
        $used = 0;
        foreach ($lines as $line) {
            $height = max(12, $line['size'] + 5);
            if ($used + $height > 735 && $current !== []) {
                $pages[] = $current;
                $current = [];
                $used = 0;
            }
            $current[] = $line;
            $used += $height;
        }
        if ($current !== [] || $pages === []) $pages[] = $current;

        $objects = [];
        $addObject = function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontRegular = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontBold = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $pagesObjectIndex = $addObject('PAGES_PLACEHOLDER');
        $pageObjectIds = [];

        foreach ($pages as $pageLines) {
            $stream = "BT\n";
            $y = 800;
            foreach ($pageLines as $line) {
                $font = $line['bold'] ? 'F2' : 'F1';
                $size = $line['size'];
                $text = $this->pdfString($line['text']);
                $stream .= "/{$font} {$size} Tf\n1 0 0 1 42 {$y} Tm\n({$text}) Tj\n";
                $y -= max(12, $size + 5);
            }
            $stream .= "ET\n";
            $contentId = $addObject('<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream");
            $pageId = $addObject('<< /Type /Page /Parent '.$pagesObjectIndex.' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R >> >> /Contents '.$contentId.' 0 R >>');
            $pageObjectIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => $id.' 0 R', $pageObjectIds));
        $objects[$pagesObjectIndex - 1] = '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageObjectIds).' >>';
        $catalogId = $addObject('<< /Type /Catalog /Pages '.$pagesObjectIndex.' 0 R >>');

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for ($i=1; $i<=count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }
        $pdf .= 'trailer << /Size '.(count($objects)+1).' /Root '.$catalogId." 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    private function pdfString(string $text): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)',' ',' '], $encoded);
    }
}
