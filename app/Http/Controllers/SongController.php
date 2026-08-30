<?php

namespace App\Http\Controllers;

use App\Http\Requests\SongRequestForm;
use App\Models\Song;
use App\Models\SongCategory;
use App\Models\SongRequest;
use App\Models\SongVersion;
use App\Models\Repertoire;
use App\Services\RepertoireService;
use App\Services\SongDuplicateDetector;
use App\Services\SongHistoryService;
use App\Services\SongImportService;
use App\Services\AuditService;
use App\Services\SongTitleNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SongController extends Controller
{
    public function index(Request $request)
    {
        $songs = Song::query()
            ->with(['category', 'versions'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.mb_strtolower(trim((string) $request->q)).'%';
                $query->where(function ($where) use ($term): void {
                    $where->whereRaw('lower(title) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(alternative_title, \'\')) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(artist_reference, \'\')) like ?', [$term])
                        ->orWhereHas('versions', fn ($versions) => $versions->whereRaw('lower(coalesce(artist, \'\')) like ?', [$term]));
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->category_id))
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        return view('songs.index', [
            'songs' => $songs,
            'categories' => SongCategory::query()->where('active', true)->orderBy('name')->get(),
            'requests' => SongRequest::query()->with(['requester','requestedService','suggestedCategory'])->latest()->limit(30)->get(),
        ]);
    }

    public function importForm()
    {
        return view('songs.import');
    }

    public function importBuiltIn(Request $request, SongImportService $importer, AuditService $audit)
    {
        $stats = $importer->importBuiltIn($request->user());
        $audit->event('songs.imported', null, null, ['source' => 'catalogo-exemplo'] + $stats);

        return redirect()->route('songs.index')->with('status', sprintf(
            'Catálogo processado: %d itens, %d criados, %d enriquecidos, %d já adequados e %d para revisão.',
            $stats['total'], $stats['created'], $stats['updated'], $stats['unchanged'], $stats['review']
        ));
    }

    public function importCsv(Request $request, SongImportService $importer, AuditService $audit)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        try {
            $stats = $importer->importCsv($request->user(), $data['file']);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['file' => 'Não foi possível importar o CSV: '.$e->getMessage()]);
        }

        $audit->event('songs.imported', null, null, ['source' => 'csv'] + $stats);

        return redirect()->route('songs.index')->with('status', sprintf(
            'CSV processado: %d linhas, %d criadas, %d enriquecidas e %d já existentes.',
            $stats['total'], $stats['created'], $stats['updated'], $stats['unchanged']
        ));
    }

    public function importTemplate(SongImportService $importer)
    {
        return response($importer->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-louvores.csv"',
        ]);
    }

    public function create()
    {
        return view('songs.form', [
            'song' => new Song(),
            'referenceVersion' => null,
            'categories' => SongCategory::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(SongRequestForm $request, SongTitleNormalizer $normalizer, SongDuplicateDetector $duplicates)
    {
        $dupes = $duplicates->find($request->title, $request->artist_reference);
        if ($dupes->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicates', $dupes);
        }

        $song = DB::transaction(function () use ($request, $normalizer) {
            $song = Song::query()->create([
                'organization_id' => $request->user()->organization_id,
                'title' => $request->title,
                'normalized_title' => $normalizer->normalize($request->title),
                'alternative_title' => $request->alternative_title,
                'normalized_alternative_title' => $request->alternative_title ? $normalizer->normalize($request->alternative_title) : null,
                'artist_reference' => $request->artist_reference,
                'category_id' => $request->category_id,
                'notes' => $request->notes,
                'status' => 'active',
                'created_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $hasReferenceData = $request->filled('version_name')
                || $request->filled('youtube_url')
                || $request->filled('original_key')
                || $request->filled('bpm');

            if ($hasReferenceData) {
                SongVersion::query()->create([
                    'organization_id' => $request->user()->organization_id,
                    'song_id' => $song->id,
                    'name' => $request->filled('version_name') ? trim((string) $request->version_name) : 'Versão padrão',
                    'artist' => $request->artist_reference,
                    'youtube_url' => $request->filled('youtube_url') ? trim((string) $request->youtube_url) : null,
                    'original_key' => $request->filled('original_key') ? trim((string) $request->original_key) : null,
                    'bpm' => $request->filled('bpm') ? (int) $request->bpm : null,
                    'is_default' => true,
                    'active' => true,
                ]);
            }

            return $song;
        });

        return redirect()->route('songs.show', $song)->with('status', 'Louvor cadastrado.');
    }

    public function show(Song $song, SongHistoryService $history)
    {
        $song->load(['category', 'versions', 'ministerKeys.person', 'ministerKeys.lastService']);
        return view('songs.show', ['song' => $song, 'stats' => $history->stats($song)]);
    }

    public function edit(Song $song)
    {
        $song->load('versions');
        $referenceVersion = $song->versions->firstWhere('is_default', true)
            ?? $song->versions->first(fn (SongVersion $version): bool => (bool) $version->active)
            ?? $song->versions->first();

        return view('songs.form', [
            'song' => $song,
            'referenceVersion' => $referenceVersion,
            'categories' => SongCategory::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(SongRequestForm $request, Song $song, SongTitleNormalizer $normalizer, SongDuplicateDetector $duplicates)
    {
        $dupes = $duplicates->find($request->title, $request->artist_reference)->where('id', '!=', $song->id);
        if ($dupes->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicates', $dupes);
        }

        DB::transaction(function () use ($request, $song, $normalizer): void {
            $song->update([
                'title' => $request->title,
                'normalized_title' => $normalizer->normalize($request->title),
                'alternative_title' => $request->alternative_title,
                'normalized_alternative_title' => $request->alternative_title ? $normalizer->normalize($request->alternative_title) : null,
                'artist_reference' => $request->artist_reference,
                'category_id' => $request->category_id,
                'notes' => $request->notes,
                'updated_by' => $request->user()->id,
            ]);

            $referenceVersion = $song->versions()->where('is_default', true)->first()
                ?? $song->versions()->where('active', true)->oldest()->first()
                ?? $song->versions()->oldest()->first();

            $hasReferenceData = $request->filled('version_name')
                || $request->filled('youtube_url')
                || $request->filled('original_key')
                || $request->filled('bpm');

            if ($referenceVersion) {
                SongVersion::query()
                    ->where('song_id', $song->id)
                    ->where('id', '!=', $referenceVersion->id)
                    ->update(['is_default' => false]);

                $referenceVersion->update([
                    'name' => $request->filled('version_name') ? trim((string) $request->version_name) : ($referenceVersion->name ?: 'Versão padrão'),
                    'artist' => $request->artist_reference,
                    'youtube_url' => $request->filled('youtube_url') ? trim((string) $request->youtube_url) : null,
                    'original_key' => $request->filled('original_key') ? trim((string) $request->original_key) : null,
                    'bpm' => $request->filled('bpm') ? (int) $request->bpm : null,
                    'is_default' => true,
                    'active' => true,
                ]);
            } elseif ($hasReferenceData) {
                SongVersion::query()->create([
                    'organization_id' => $song->organization_id,
                    'song_id' => $song->id,
                    'name' => $request->filled('version_name') ? trim((string) $request->version_name) : 'Versão padrão',
                    'artist' => $request->artist_reference,
                    'youtube_url' => $request->filled('youtube_url') ? trim((string) $request->youtube_url) : null,
                    'original_key' => $request->filled('original_key') ? trim((string) $request->original_key) : null,
                    'bpm' => $request->filled('bpm') ? (int) $request->bpm : null,
                    'is_default' => true,
                    'active' => true,
                ]);
            }
        });

        return redirect()->route('songs.show', $song)->with('status', 'Louvor e referência principal atualizados.');
    }

    public function destroy(Request $request, Song $song, AuditService $audit)
    {
        // RC48: "Excluir" retira o louvor imediatamente da biblioteca usando
        // SoftDeletes. Referências históricas/repertórios permanecem legíveis por
        // relações withTrashed(), sem manter cartões "Inativo" na biblioteca.
        $song->forceFill(['status' => 'deleted', 'updated_by' => $request->user()->id])->saveQuietly();
        $audit->event('song.deleted_from_library', $song, 'Exclusão da biblioteca', [
            'title' => $song->title,
        ]);
        $song->delete();

        return redirect()->route('songs.index')->with('status', 'Louvor excluído da biblioteca. O histórico de cultos permanece preservado.');
    }

    public function archive(Request $request, Song $song)
    {
        $song->update(['status' => 'inactive', 'updated_by' => $request->user()->id]);
        return back()->with('status', 'Louvor arquivado.');
    }

    public function restore(Request $request, Song $song)
    {
        $song->update(['status' => 'active', 'updated_by' => $request->user()->id]);
        return back()->with('status', 'Louvor reativado.');
    }

    public function storeVersion(Request $request, Song $song)
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'artist' => 'nullable|string|max:180',
            'youtube_url' => 'nullable|url|max:500',
            'original_key' => 'nullable|string|max:20',
            'bpm' => 'nullable|integer|min:30|max:300',
            'notes' => 'nullable|string|max:3000',
            'is_default' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($data, $request, $song): void {
            if ($request->boolean('is_default')) {
                SongVersion::query()->where('song_id', $song->id)->update(['is_default' => false]);
            }
            SongVersion::query()->create($data + [
                'organization_id' => $song->organization_id,
                'song_id' => $song->id,
                'is_default' => $request->boolean('is_default'),
                'active' => true,
            ]);
        });

        return back()->with('status', 'Versão cadastrada.');
    }

    public function setDefaultVersion(Song $song, SongVersion $version)
    {
        abort_unless($version->song_id === $song->id, 404);
        DB::transaction(function () use ($song, $version): void {
            SongVersion::query()->where('song_id', $song->id)->update(['is_default' => false]);
            $version->update(['is_default' => true]);
        });
        return back()->with('status', 'Versão padrão atualizada.');
    }

    public function archiveVersion(Song $song, SongVersion $version)
    {
        abort_unless($version->song_id === $song->id, 404);
        $version->update(['active' => false, 'is_default' => false]);
        return back()->with('status', 'Versão arquivada.');
    }

    public function requestSong(Request $request, SongTitleNormalizer $normalizer)
    {
        $data = $request->validate([
            'suggested_title' => 'required|string|max:180',
            'artist_reference' => 'nullable|string|max:180',
            'youtube_url' => 'nullable|url|max:500',
            'suggested_key' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:3000',
            'requested_for_service_id' => 'nullable|uuid',
            'suggested_category_id' => 'nullable|uuid',
        ]);

        if (! empty($data['requested_for_service_id'])) {
            \App\Models\Service::query()->findOrFail($data['requested_for_service_id']);
        }
        if (! empty($data['suggested_category_id'])) {
            SongCategory::query()->where('active', true)->findOrFail($data['suggested_category_id']);
        }

        SongRequest::query()->create($data + [
            'organization_id' => $request->user()->organization_id,
            'requested_by' => $request->user()->id,
            'normalized_title' => $normalizer->normalize($data['suggested_title']),
            'status' => 'pending',
        ]);
        return back()->with('status', 'Solicitação enviada para revisão. O louvor só entrará na biblioteca após aprovação administrativa.');
    }

    public function approveRequest(Request $request, SongRequest $songRequest, SongTitleNormalizer $normalizer, RepertoireService $repertoires)
    {
        abort_unless($songRequest->status === 'pending', 422);
        $data = $request->validate([
            'title' => ['required','string','max:180'],
            'artist_reference' => ['nullable','string','max:180'],
            'youtube_url' => ['nullable','url','max:500'],
            'original_key' => ['nullable','string','max:20'],
            'category_id' => ['nullable','uuid'],
            'review_notes' => ['nullable','string','max:2000'],
            'add_to_repertoire' => ['nullable','boolean'],
        ]);
        if (! empty($data['category_id'])) SongCategory::query()->where('active', true)->findOrFail($data['category_id']);

        $song = DB::transaction(function () use ($songRequest, $request, $normalizer, $data, $repertoires) {
            $normalized = $normalizer->normalize($data['title']);
            $song = Song::query()->firstOrCreate(
                ['normalized_title' => $normalized, 'artist_reference' => $data['artist_reference'] ?? null],
                [
                    'organization_id' => $songRequest->organization_id,
                    'title' => $data['title'],
                    'category_id' => $data['category_id'] ?? null,
                    'status' => 'active',
                    'created_by' => $request->user()->id,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]
            );
            if (! $song->category_id && ! empty($data['category_id'])) $song->update(['category_id' => $data['category_id']]);

            if (($data['youtube_url'] ?? null) || ($data['original_key'] ?? null) || ($data['artist_reference'] ?? null)) {
                $version = $song->versions()->where('is_default', true)->first();
                if (! $version) {
                    SongVersion::query()->create([
                        'organization_id' => $songRequest->organization_id,
                        'song_id' => $song->id,
                        'name' => $data['artist_reference'] ?: 'Versão aprovada',
                        'artist' => $data['artist_reference'] ?? null,
                        'youtube_url' => $data['youtube_url'] ?? null,
                        'original_key' => $data['original_key'] ?? null,
                        'is_default' => true,
                        'active' => true,
                    ]);
                } else {
                    $version->update([
                        'artist' => $data['artist_reference'] ?: $version->artist,
                        'youtube_url' => $data['youtube_url'] ?: $version->youtube_url,
                        'original_key' => $data['original_key'] ?: $version->original_key,
                    ]);
                }
            }

            $songRequest->update([
                'status' => 'approved',
                'suggested_title' => $data['title'],
                'normalized_title' => $normalized,
                'artist_reference' => $data['artist_reference'] ?? null,
                'youtube_url' => $data['youtube_url'] ?? null,
                'suggested_key' => $data['original_key'] ?? null,
                'suggested_category_id' => $data['category_id'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                'approved_song_id' => $song->id,
            ]);

            if ($request->boolean('add_to_repertoire') && $songRequest->requested_for_service_id) {
                $service = \App\Models\Service::query()->find($songRequest->requested_for_service_id);
                if ($service) {
                    $repertoire = $repertoires->forService($service);
                    if (! $repertoire->items()->where('song_id', $song->id)->exists()) {
                        $repertoires->addItem($repertoire, $song->id, null, $data['original_key'] ?? null, $data['youtube_url'] ?? null);
                    }
                }
            }
            return $song;
        });

        return redirect()->route('songs.index')->with('status', 'Solicitação revisada e aprovada. O louvor agora faz parte da biblioteca.');
    }

    public function rejectRequest(Request $request, SongRequest $songRequest)
    {
        $data = $request->validate(['review_notes' => 'required|string|max:2000']);
        $songRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);
        return back()->with('status', 'Solicitação rejeitada.');
    }
}
