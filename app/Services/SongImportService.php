<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongCategory;
use App\Models\SongVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SongImportService
{
    public function __construct(private readonly SongTitleNormalizer $normalizer)
    {
    }

    /** @return array{total:int,created:int,updated:int,unchanged:int,review:int} */
    public function importBuiltIn(User $actor): array
    {
        /** @var array{categories: array<string,string>, songs: array<int,array<string,mixed>>} $catalog */
        $catalog = require base_path('database/data/example_song_catalog.php');

        foreach ($catalog['categories'] as $name => $description) {
            $this->category($actor, $name, $description);
        }

        return $this->importRows($actor, $catalog['songs'], 'catalogo-exemplo');
    }

    /** @return array{total:int,created:int,updated:int,unchanged:int,review:int} */
    public function importCsv(User $actor, UploadedFile $file): array
    {
        $rows = $this->readCsv($file->getRealPath());

        return $this->importRows($actor, $rows, 'csv');
    }

    /** @param array<int,array<string,mixed>> $rows
     *  @return array{total:int,created:int,updated:int,unchanged:int,review:int}
     */
    public function importRows(User $actor, array $rows, string $source): array
    {
        if (! $actor->organization_id) {
            throw new RuntimeException('O usuário não possui organização vinculada.');
        }

        $stats = ['total' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'review' => 0];

        foreach ($rows as $raw) {
            $entry = $this->normalizeEntry($raw);
            if ($entry['title'] === '') {
                continue;
            }

            if ($entry['confidence'] === 'revisar') {
                $stats['review']++;
            }

            $categoryId = null;
            if ($entry['category'] !== '') {
                $categoryId = $this->category($actor, $entry['category'])->id;
            }

            $candidateTitles = collect([$entry['input_title'], $entry['title'], ...$entry['aliases']])
                ->filter()
                ->map(fn ($title) => $this->normalizer->normalize((string) $title))
                ->unique()
                ->values();

            $song = Song::query()->withoutGlobalScopes()
                ->where('organization_id', $actor->organization_id)
                ->where(function ($query) use ($candidateTitles): void {
                    $query->whereIn('normalized_title', $candidateTitles->all())
                        ->orWhereIn('normalized_alternative_title', $candidateTitles->all());
                })
                ->first();

            DB::transaction(function () use (&$stats, $actor, $entry, $categoryId, &$song, $source): void {
                if (! $song) {
                    $note = $entry['note'];
                    if ($source === 'catalogo-exemplo') {
                        $note = trim('Importado do catálogo fictício de exemplo. '.$note);
                    } else {
                        $note = trim('Importado por CSV. '.$note);
                    }

                    $song = Song::query()->withoutGlobalScopes()->create([
                        'organization_id' => $actor->organization_id,
                        'category_id' => $categoryId,
                        'title' => $entry['title'],
                        'normalized_title' => $this->normalizer->normalize($entry['title']),
                        'alternative_title' => $entry['input_title'] !== '' && $entry['input_title'] !== $entry['title'] ? $entry['input_title'] : null,
                        'normalized_alternative_title' => $entry['input_title'] !== '' && $entry['input_title'] !== $entry['title'] ? $this->normalizer->normalize($entry['input_title']) : null,
                        'artist_reference' => $entry['artist'] !== '' ? $entry['artist'] : null,
                        'notes' => $note !== '' ? $note : null,
                        'status' => 'active',
                        'created_by' => $actor->id,
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]);
                    $stats['created']++;
                } else {
                    if (method_exists($song, 'trashed') && $song->trashed()) {
                        $song->restoreQuietly();
                        $song->forceFill(['status' => 'active', 'updated_by' => $actor->id])->saveQuietly();
                    }
                    $changes = [];
                    if (! $song->category_id && $categoryId) $changes['category_id'] = $categoryId;
                    if (! $song->artist_reference && $entry['artist'] !== '') $changes['artist_reference'] = $entry['artist'];
                    if (! $song->alternative_title && $entry['input_title'] !== '' && $this->normalizer->normalize($entry['input_title']) !== $song->normalized_title) {
                        $changes['alternative_title'] = $entry['input_title'];
                        $changes['normalized_alternative_title'] = $this->normalizer->normalize($entry['input_title']);
                    }
                    if ($changes !== []) {
                        $changes['updated_by'] = $actor->id;
                        $song->update($changes);
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                }

                if ($entry['artist'] !== '' || $entry['original_key'] !== '') {
                    $version = SongVersion::query()->withoutGlobalScopes()
                        ->where('organization_id', $actor->organization_id)
                        ->where('song_id', $song->id)
                        ->where('is_default', true)
                        ->first();

                    if (! $version) {
                        SongVersion::query()->withoutGlobalScopes()->create([
                            'organization_id' => $actor->organization_id,
                            'song_id' => $song->id,
                            'name' => $entry['artist'] !== '' ? $entry['artist'] : 'Versão principal',
                            'artist' => $entry['artist'] !== '' ? $entry['artist'] : null,
                            'original_key' => $entry['original_key'] !== '' ? $entry['original_key'] : null,
                            'is_default' => true,
                            'active' => true,
                            'notes' => $entry['confidence'] === 'alta' ? 'Metadados iniciais do catálogo/importação.' : 'Referência inicial; confirmar artista/versão/tom.',
                        ]);
                    } else {
                        $versionChanges = [];
                        if (! $version->artist && $entry['artist'] !== '') $versionChanges['artist'] = $entry['artist'];
                        if (! $version->original_key && $entry['original_key'] !== '') $versionChanges['original_key'] = $entry['original_key'];
                        if ($versionChanges !== []) $version->update($versionChanges);
                    }
                }
            });
        }

        return $stats;
    }

    public function templateCsv(): string
    {
        return implode("\n", [
            'titulo;artista;tom_original;categoria;titulo_alternativo;observacao',
            'Canção de Adoração - Exemplo;Artista Exemplo;G;Adoração;;',
            'Canção de Gratidão - Exemplo;Artista Exemplo;C;Gratidão;;Use seus próprios metadados',
        ])."\n";
    }

    private function category(User $actor, string $name, ?string $description = null): SongCategory
    {
        $name = trim($name);
        $existing = SongCategory::query()->withoutGlobalScopes()
            ->where('organization_id', $actor->organization_id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if (! $existing->active) $existing->update(['active' => true]);
            return $existing;
        }

        return SongCategory::query()->withoutGlobalScopes()->create([
            'organization_id' => $actor->organization_id,
            'name' => $name,
            'description' => $description,
            'active' => true,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) throw new RuntimeException('Não foi possível abrir o arquivo CSV.');

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            throw new RuntimeException('O arquivo CSV está vazio.');
        }

        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = $this->detectDelimiter($first);
        $headers = str_getcsv(rtrim($first, "\r\n"), $delimiter);
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);

        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values === [null] || collect($values)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) continue;
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }
            $rows[] = $row;
            if (count($rows) > 5000) {
                fclose($handle);
                throw new RuntimeException('O arquivo excede o limite de 5.000 linhas por importação.');
            }
        }
        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = strtr($header, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
    }

    /** @param array<string,mixed> $row
     *  @return array{group:string,input_title:string,title:string,artist:string,original_key:string,category:string,confidence:string,note:string,aliases:array<int,string>}
     */
    private function normalizeEntry(array $row): array
    {
        $title = $this->first($row, ['titulo_canonico','titulo','title','nome']);
        $input = $this->first($row, ['titulo_informado','input_title','titulo_alternativo','alternative_title']);
        if ($title === '') $title = $input;
        if ($input === '') $input = $title;

        return [
            'group' => $this->first($row, ['grupo','group']),
            'input_title' => $input,
            'title' => $title,
            'artist' => $this->first($row, ['artista_referencia','artista','artist']),
            'original_key' => $this->first($row, ['tom_original_referencia','tom_original','original_key','tom']),
            'category' => $this->first($row, ['categoria','category']),
            'confidence' => $this->first($row, ['confianca','confidence']) ?: 'importado',
            'note' => $this->first($row, ['observacao','observacoes','note','notes']),
            'aliases' => isset($row['aliases']) && is_array($row['aliases']) ? array_values(array_filter(array_map('strval', $row['aliases']))) : [],
        ];
    }

    /** @param array<string,mixed> $row */
    private function first(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') return trim((string) $row[$key]);
        }
        return '';
    }
}
