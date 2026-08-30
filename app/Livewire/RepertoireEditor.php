<?php

namespace App\Livewire;

use App\Models\Repertoire;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongCategory;
use App\Models\SongVersion;
use App\Services\RepertoireService;
use App\Services\SongTitleNormalizer;
use App\Services\MinisterAssistanceContext;
use App\Services\MinisterSongKeyService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RepertoireEditor extends Component
{
    public Service $service;
    public Repertoire $repertoire;

    public string $songId = '';
    public string $plannedKey = '';
    public string $songMinisterId = '';
    public string $specificYoutubeUrl = '';
    public string $itemNote = '';
    public string $songSearch = '';
    public string $categoryId = '';
    public bool $songSearchResultsOpen = false;
    public array $itemKeys = [];
    public array $itemLinks = [];
    public array $itemNotes = [];
    public array $itemMinisterIds = [];
    public string $actionFeedback = '';

    public string $openingSongId = '';
    public string $openingKey = '';
    public string $openingMinisterId = '';
    public string $openingYoutubeUrl = '';
    public string $openingNotes = '';
    public string $openingSongSearch = '';
    public string $openingCategoryId = '';
    public bool $openingSearchResultsOpen = false;
    public string $finalNotes = '';

    public string $newSongTitle = '';
    public string $newSongArtist = '';
    public string $newSongCategoryId = '';
    public string $newSongKey = '';
    public string $newSongMinisterId = '';
    public string $newSongUrl = '';
    public string $newSongNotes = '';

    public string $newOpeningSongTitle = '';
    public string $newOpeningSongArtist = '';
    public string $newOpeningSongCategoryId = '';
    public string $newOpeningSongKey = '';
    public string $newOpeningSongMinisterId = '';
    public string $newOpeningSongUrl = '';
    public string $newOpeningSongNotes = '';

    public function mount(Service $service): void
    {
        $this->service = $service;
        $this->repertoire = app(RepertoireService::class)->forService($service);
        $defaultMinister = $this->defaultMinisterId();
        $this->songMinisterId = $defaultMinister;
        $this->openingMinisterId = $defaultMinister;
        $this->newSongMinisterId = $defaultMinister;
        $this->newOpeningSongMinisterId = $defaultMinister;
        $this->loadFields();
    }

    public function canManage(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->hasPermission('repertoire.manage_all')) return true;
        if (! $user->hasPermission('repertoire.manage_own')) return false;

        $person = app(MinisterAssistanceContext::class)->person($user);
        if (! $person) return false;

        if ($this->service->assignment()->where('status', 'published')->where('minister_person_id', $person->id)->exists()) return true;

        return $this->service->additionalMinisters()->where('status', 'published')->where('person_id', $person->id)->exists();
    }

    public function addSelectedSong(): void
    {
        abort_unless($this->canManage(), 403);
        $this->actionFeedback = '';
        $this->resetErrorBag('addSong');
        $this->validate([
            'songId' => 'required|uuid',
            'plannedKey' => 'nullable|string|max:20',
            'songMinisterId' => 'nullable|uuid',
            'specificYoutubeUrl' => 'nullable|url|max:500',
            'itemNote' => 'nullable|string|max:1000',
        ]);

        $song = Song::query()->where('status', 'active')->findOrFail($this->songId);
        abort_unless($song->organization_id === $this->repertoire->organization_id, 404);

        try {
            app(RepertoireService::class)->addItem(
                $this->repertoire,
                $song->id,
                null,
                $this->plannedKey ?: null,
                $this->specificYoutubeUrl ?: null,
                $this->itemNote ?: null,
                $this->songMinisterId ?: null
            );
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('addSong', 'Não foi possível adicionar este louvor ao repertório. Tente novamente; se persistir, peça ao administrador para consultar o log do SGML.');
            return;
        }

        $this->reset('songId', 'plannedKey', 'specificYoutubeUrl', 'itemNote', 'songSearch', 'songMinisterId');
        $this->songMinisterId = $this->defaultMinisterId();
        $this->songSearchResultsOpen = false;
        $this->refreshRepertoire();
        $this->actionFeedback = 'Adicionado! O louvor entrou no repertório, na escala e na agenda.';
        session()->flash('status', 'Louvor adicionado ao repertório. A escala e a agenda foram atualizadas.');
    }

    // Compatibilidade com chamadas antigas. O fluxo visual RC71 usa a ação
    // explícita addSelectedSong(), acionada por wire:click.
    public function add(): void
    {
        $this->addSelectedSong();
    }

    public function createSongAndAdd(): void
    {
        abort_unless($this->canManage(), 403);
        $this->actionFeedback = '';
        $this->validate([
            'newSongTitle' => 'required|string|max:180',
            'newSongArtist' => 'nullable|string|max:180',
            'newSongCategoryId' => 'nullable|uuid',
            'newSongKey' => 'nullable|string|max:20',
            'newSongMinisterId' => 'nullable|uuid',
            'newSongUrl' => 'nullable|url|max:500',
            'newSongNotes' => 'nullable|string|max:3000',
        ]);

        $normalizer = app(SongTitleNormalizer::class);
        $normalized = $normalizer->normalize($this->newSongTitle);
        if ($this->newSongCategoryId !== '') SongCategory::query()->where('active', true)->findOrFail($this->newSongCategoryId);

        $song = DB::transaction(function () use ($normalized) {
            $song = Song::query()
                ->where('normalized_title', $normalized)
                ->where('status', 'active')
                ->first();

            if (! $song) {
                $song = Song::query()->create([
                    'organization_id' => auth()->user()->organization_id,
                    'title' => trim($this->newSongTitle),
                    'normalized_title' => $normalized,
                    'artist_reference' => filled($this->newSongArtist) ? trim($this->newSongArtist) : null,
                    'category_id' => $this->newSongCategoryId ?: null,
                    'notes' => filled($this->newSongNotes) ? trim($this->newSongNotes) : null,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                if (filled($this->newSongKey) || filled($this->newSongUrl)) {
                    SongVersion::query()->create([
                        'organization_id' => auth()->user()->organization_id,
                        'song_id' => $song->id,
                        'name' => 'Versão principal',
                        'artist' => filled($this->newSongArtist) ? trim($this->newSongArtist) : null,
                        'youtube_url' => filled($this->newSongUrl) ? trim($this->newSongUrl) : null,
                        'original_key' => filled($this->newSongKey) ? trim($this->newSongKey) : null,
                        'notes' => null,
                        'is_default' => true,
                        'active' => true,
                    ]);
                }
            }
            return $song;
        });

        app(RepertoireService::class)->addItem(
            $this->repertoire,
            $song->id,
            null,
            $this->newSongKey ?: null,
            $this->newSongUrl ?: null,
            $this->newSongNotes ?: null,
            $this->newSongMinisterId ?: null
        );

        $this->reset('newSongTitle','newSongArtist','newSongCategoryId','newSongKey','newSongUrl','newSongNotes','newSongMinisterId','songSearch');
        $this->newSongMinisterId = $this->defaultMinisterId();
        $this->songSearchResultsOpen = false;
        $this->refreshRepertoire();
        $this->actionFeedback = 'Adicionado! O novo louvor foi cadastrado e incluído no repertório.';
        session()->flash('status', 'Novo louvor cadastrado na biblioteca e adicionado imediatamente ao repertório, sem etapa de aprovação.');
    }

    public function createOpeningSongAndSet(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'newOpeningSongTitle' => 'required|string|max:180',
            'newOpeningSongArtist' => 'nullable|string|max:180',
            'newOpeningSongCategoryId' => 'nullable|uuid',
            'newOpeningSongKey' => 'nullable|string|max:20',
            'newOpeningSongMinisterId' => 'nullable|uuid',
            'newOpeningSongUrl' => 'nullable|url|max:500',
            'newOpeningSongNotes' => 'nullable|string|max:3000',
        ]);

        $normalizer = app(SongTitleNormalizer::class);
        $normalized = $normalizer->normalize($this->newOpeningSongTitle);
        if ($this->newOpeningSongCategoryId !== '') {
            SongCategory::query()->where('active', true)->findOrFail($this->newOpeningSongCategoryId);
        }

        $song = DB::transaction(function () use ($normalized) {
            $song = Song::query()
                ->where('normalized_title', $normalized)
                ->where('status', 'active')
                ->first();

            if (! $song) {
                $song = Song::query()->create([
                    'organization_id' => auth()->user()->organization_id,
                    'title' => trim($this->newOpeningSongTitle),
                    'normalized_title' => $normalized,
                    'artist_reference' => filled($this->newOpeningSongArtist) ? trim($this->newOpeningSongArtist) : null,
                    'category_id' => $this->newOpeningSongCategoryId ?: null,
                    'notes' => filled($this->newOpeningSongNotes) ? trim($this->newOpeningSongNotes) : null,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                if (filled($this->newOpeningSongKey) || filled($this->newOpeningSongUrl)) {
                    SongVersion::query()->create([
                        'organization_id' => auth()->user()->organization_id,
                        'song_id' => $song->id,
                        'name' => 'Versão principal',
                        'artist' => filled($this->newOpeningSongArtist) ? trim($this->newOpeningSongArtist) : null,
                        'youtube_url' => filled($this->newOpeningSongUrl) ? trim($this->newOpeningSongUrl) : null,
                        'original_key' => filled($this->newOpeningSongKey) ? trim($this->newOpeningSongKey) : null,
                        'notes' => null,
                        'is_default' => true,
                        'active' => true,
                    ]);
                }
            }

            return $song;
        });

        app(RepertoireService::class)->updateOpening(
            $this->repertoire,
            $song->id,
            null,
            $this->newOpeningSongKey ?: null,
            $this->newOpeningSongUrl ?: null,
            $this->newOpeningSongNotes ?: null,
            $this->newOpeningSongMinisterId ?: null
        );

        $this->reset(
            'newOpeningSongTitle',
            'newOpeningSongArtist',
            'newOpeningSongCategoryId',
            'newOpeningSongKey',
            'newOpeningSongUrl',
            'newOpeningSongNotes',
            'newOpeningSongMinisterId',
            'openingSongSearch'
        );
        $this->newOpeningSongMinisterId = $this->defaultMinisterId();
        $this->openingSearchResultsOpen = false;
        $this->refreshRepertoire();
        session()->flash('status', 'Novo louvor cadastrado na biblioteca e definido imediatamente como louvor de abertura.');
    }

    public function updatedSongSearch(): void
    {
        $this->songSearchResultsOpen = trim($this->songSearch) !== '';
    }

    public function updatedCategoryId(): void
    {
        if (trim($this->songSearch) !== '') {
            $this->songSearchResultsOpen = true;
        }
    }

    public function updatedOpeningSongSearch(): void
    {
        $this->openingSearchResultsOpen = trim($this->openingSongSearch) !== '';
    }

    public function updatedOpeningCategoryId(): void
    {
        if (trim($this->openingSongSearch) !== '') {
            $this->openingSearchResultsOpen = true;
        }
    }

    public function selectOpeningSong(string $songId): void
    {
        abort_unless($this->canManage(), 403);
        $this->openingSongId = $songId;
        if ($this->openingMinisterId === '') $this->openingMinisterId = $this->defaultMinisterId();
        $this->applyOpeningSongSuggestion($songId);
        $this->openingSearchResultsOpen = false;
    }

    public function selectSong(string $songId): void
    {
        abort_unless($this->canManage(), 403);
        $this->songId = $songId;
        if ($this->songMinisterId === '') $this->songMinisterId = $this->defaultMinisterId();
        $this->applySongSuggestion($songId);
        $this->songSearchResultsOpen = false;
    }

    public function updatedSongId(): void
    {
        if ($this->songId === '') return;
        $this->applySongSuggestion($this->songId);
    }

    public function updatedSongMinisterId(): void
    {
        if ($this->songId === '') return;
        $this->applySongSuggestion($this->songId, false);
    }

    public function updatedOpeningSongId(): void
    {
        if ($this->openingSongId === '') return;
        $this->applyOpeningSongSuggestion($this->openingSongId);
    }

    public function updatedOpeningMinisterId(): void
    {
        if ($this->openingSongId === '') return;
        $this->applyOpeningSongSuggestion($this->openingSongId, false);
    }

    public function updatedItemMinisterIds(mixed $value, string $itemId): void
    {
        $item = $this->repertoire->items()->find($itemId);
        if (! $item || ! filled($value)) return;
        $item->loadMissing('song.versions');
        $preferred = app(MinisterSongKeyService::class)->preferredKey((string) $value, $item->song_id);
        $defaultVersion = $item->song?->versions?->firstWhere('is_default', true) ?? $item->song?->versions?->first();
        $this->itemKeys[$itemId] = (string) ($preferred ?: $defaultVersion?->original_key ?: '');
    }

    public function saveOpening(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'openingSongId' => 'nullable|uuid',
            'openingKey' => 'nullable|string|max:20',
            'openingMinisterId' => 'nullable|uuid',
            'openingYoutubeUrl' => 'nullable|url|max:500',
            'openingNotes' => 'nullable|string|max:1000',
        ]);
        if ($this->openingSongId !== '') {
            Song::query()->where('status', 'active')->findOrFail($this->openingSongId);
        }
        app(RepertoireService::class)->updateOpening($this->repertoire, $this->openingSongId ?: null, null, $this->openingKey ?: null, $this->openingYoutubeUrl ?: null, $this->openingNotes ?: null, $this->openingMinisterId ?: null);
        $this->refreshRepertoire();
        $this->actionFeedback = 'Salvo! O louvor de abertura foi atualizado.';
        session()->flash('status', 'Louvor de abertura atualizado na agenda.');
    }

    public function saveFinalNotes(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate(['finalNotes' => 'nullable|string|max:3000']);
        app(RepertoireService::class)->updateFinalNotes($this->repertoire, $this->finalNotes ?: null);
        $this->refreshRepertoire();
        session()->flash('status', 'Observações do culto atualizadas.');
    }

    public function updateItem(string $id): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'itemKeys.'.$id => 'nullable|string|max:20',
            'itemLinks.'.$id => 'nullable|url|max:500',
            'itemNotes.'.$id => 'nullable|string|max:1000',
            'itemMinisterIds.'.$id => 'nullable|uuid',
        ]);
        app(RepertoireService::class)->updateItem($this->repertoire, $id, $this->itemKeys[$id] ?? null, $this->itemLinks[$id] ?? null, $this->itemNotes[$id] ?? null, $this->itemMinisterIds[$id] ?? null);
        $this->refreshRepertoire();
        session()->flash('status', $this->serviceEnded() ? 'Louvor corrigido no repertório e no histórico de louvores ministrados.' : 'Louvor atualizado no repertório e na agenda.');
    }

    public function handleSort(string $id, int $position): void
    {
        abort_unless($this->canManage(), 403);
        app(RepertoireService::class)->reorder($this->repertoire, $id, $position);
        $this->refreshRepertoire();
    }

    public function remove(string $id): void
    {
        abort_unless($this->canManage(), 403);
        app(RepertoireService::class)->removeItem($this->repertoire, $id);
        unset($this->itemKeys[$id], $this->itemLinks[$id], $this->itemNotes[$id], $this->itemMinisterIds[$id]);
        $this->refreshRepertoire();
        session()->flash('status', $this->serviceEnded() ? 'Louvor removido do repertório e corrigido no histórico.' : 'Louvor removido do repertório e da agenda.');
    }

    public function confirmRepertoire(): void
    {
        abort_unless($this->canManage(), 403);
        $this->refreshRepertoire();

        if ((int) ($this->repertoire->announced_revision ?? 0) === (int) $this->repertoire->revision_number) {
            session()->flash('status', 'Este repertório já foi confirmado nesta revisão.');
            return;
        }

        $this->repertoire->forceFill([
            'announced_revision' => (int) $this->repertoire->revision_number,
            'announced_at' => now(),
            'announced_by' => auth()->id(),
        ])->save();

        $this->service->loadMissing([
            'assignment.minister',
            'additionalMinisters.minister',
            'repertoire.openingSong.versions',
            'repertoire.openingSongVersion',
            'repertoire.openingMinister',
            'repertoire.items.song.versions',
            'repertoire.items.songVersion',
            'repertoire.items.minister',
        ]);

        $startsAt = $this->service->starts_at;
        $endsAt = $this->service->ends_at;
        $weekday = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$startsAt->dayOfWeek] ?? $startsAt->translatedFormat('l');
        $primaryMinister = $this->service->assignment?->minister?->display_name ?: 'Não definido';
        $extraMinisters = $this->service->additionalMinisters
            ->map(fn ($item) => $item->minister?->display_name)
            ->filter()
            ->values();

        $lines = [
            'Ministro: '.$primaryMinister,
        ];
        if ($extraMinisters->isNotEmpty()) {
            $lines[] = 'Ministros adicionais: '.$extraMinisters->implode(', ');
        }
        $lines[] = 'Dia: '.$weekday;
        $lines[] = 'Data: '.$startsAt->format('d/m/Y');
        $lines[] = 'Horário: '.$startsAt->format('H:i').($endsAt ? ' - '.$endsAt->format('H:i') : '');
        $lines[] = '';

        if ($this->repertoire->openingSong) {
            $openingDefaultVersion = $this->repertoire->openingSong?->versions?->firstWhere('is_default', true)
                ?? $this->repertoire->openingSong?->versions?->first();
            $openingKey = $this->repertoire->opening_key
                ?: $this->repertoire->openingSongVersion?->original_key
                ?: $openingDefaultVersion?->original_key;
            $lines[] = 'Abertura: '.$this->repertoire->openingSong->title;
            $lines[] = 'Ministro: '.($this->repertoire->openingMinister?->display_name ?: $primaryMinister);
            $lines[] = 'Tom: '.($openingKey ?: 'não informado');
            $lines[] = '';
        }

        $items = $this->repertoire->items()->with(['song.versions', 'songVersion', 'minister'])->get();
        foreach ($items as $index => $item) {
            if (! $item->song) continue;
            $defaultVersion = $item->song?->versions?->firstWhere('is_default', true)
                ?? $item->song?->versions?->first();
            $key = $item->planned_key ?: $item->songVersion?->original_key ?: $defaultVersion?->original_key;
            $lines[] = 'Louvor '.($index + 1).': '.$item->song->title;
            $lines[] = 'Ministro: '.($item->minister?->display_name ?: $primaryMinister);
            $lines[] = 'Tom: '.($key ?: 'não informado');
            $lines[] = '';
        }

        if (! $this->repertoire->openingSong && $items->isEmpty()) {
            $lines[] = 'Repertório confirmado sem louvores cadastrados.';
        }

        $summary = rtrim(implode("\n", $lines));

        app(\App\Services\TeamNotificationService::class)->announce(
            $this->service->organization_id,
            'Repertório confirmado - '.$this->service->name,
            $summary,
            route('repertoire.show', $this->service),
            'repertoire.confirmed:'.$this->repertoire->id.':'.$this->repertoire->revision_number,
            'normal'
        );

        $this->refreshRepertoire();
        session()->flash('status', 'Repertório confirmado. A equipe recebeu um aviso para consultar os louvores no SGML.');
    }

    public function render()
    {
        $songs = Song::query()
            ->with(['category', 'versions'])
            ->where('status', 'active')
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->when(trim($this->songSearch) !== '', function ($query): void {
                $term = '%'.mb_strtolower(trim($this->songSearch)).'%';
                $query->where(function ($where) use ($term): void {
                    $where->whereRaw('lower(title) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(alternative_title, \'\')) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(artist_reference, \'\')) like ?', [$term])
                        ->orWhereHas('versions', fn ($versions) => $versions->whereRaw('lower(coalesce(artist, \'\')) like ?', [$term]));
                });
            })
            ->orderBy('title')
            ->limit(200)
            ->get();

        $openingSongs = Song::query()
            ->with(['category', 'versions'])
            ->where('status', 'active')
            ->when($this->openingCategoryId !== '', fn ($query) => $query->where('category_id', $this->openingCategoryId))
            ->when(trim($this->openingSongSearch) !== '', function ($query): void {
                $term = '%'.mb_strtolower(trim($this->openingSongSearch)).'%';
                $query->where(function ($where) use ($term): void {
                    $where->whereRaw('lower(title) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(alternative_title, \'\')) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(artist_reference, \'\')) like ?', [$term])
                        ->orWhereHas('versions', fn ($versions) => $versions->whereRaw('lower(coalesce(artist, \'\')) like ?', [$term]));
                });
            })
            ->orderBy('title')
            ->limit(200)
            ->get();

        return view('livewire.repertoire-editor', [
            'items' => $this->repertoire->items()->with(['song.category', 'song.versions', 'songVersion', 'minister'])->get(),
            'songs' => $songs,
            'openingSongs' => $openingSongs,
            'categories' => SongCategory::query()->where('active', true)->orderBy('name')->get(),
            'canManage' => $this->canManage(),
            'serviceEnded' => $this->serviceEnded(),
            'ministerOptions' => $this->serviceMinisters(),
        ]);
    }

    private function refreshRepertoire(): void
    {
        $this->repertoire->refresh()->load(['openingSong.versions', 'openingMinister', 'items.song.versions', 'items.minister']);
        $this->loadFields();
    }

    private function loadFields(): void
    {
        foreach ($this->repertoire->items()->get() as $item) {
            $this->itemKeys[$item->id] = (string) ($item->planned_key ?? '');
            $this->itemLinks[$item->id] = (string) ($item->specific_youtube_url ?? '');
            $this->itemNotes[$item->id] = (string) ($item->notes ?? '');
            $this->itemMinisterIds[$item->id] = (string) ($item->minister_person_id ?? '');
        }
        $this->openingSongId = (string) ($this->repertoire->opening_song_id ?? '');
        $this->openingKey = (string) ($this->repertoire->opening_key ?? '');
        $this->openingMinisterId = (string) ($this->repertoire->opening_minister_person_id ?: $this->openingMinisterId ?: $this->defaultMinisterId());
        $this->openingYoutubeUrl = (string) ($this->repertoire->opening_youtube_url ?? '');
        $this->openingNotes = (string) ($this->repertoire->opening_notes ?? '');
        $this->finalNotes = (string) ($this->repertoire->final_notes ?? '');
    }

    private function serviceMinisters()
    {
        $this->service->loadMissing(['assignment.minister', 'additionalMinisters.minister']);

        return collect([$this->service->assignment?->minister])
            ->filter()
            ->merge($this->service->additionalMinisters->where('status', 'published')->pluck('minister')->filter())
            ->unique('id')
            ->values();
    }

    private function defaultMinisterId(): string
    {
        $ministers = $this->serviceMinisters();
        $personId = auth()->user()?->person?->id;
        if ($personId && $ministers->contains('id', $personId)) return (string) $personId;
        return $ministers->count() === 1 ? (string) $ministers->first()->id : '';
    }

    private function applySongSuggestion(string $songId, bool $refreshReference = true): void
    {
        $song = Song::query()->with('versions')->where('status', 'active')->findOrFail($songId);
        $version = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
        $preferred = app(MinisterSongKeyService::class)->preferredKey($this->songMinisterId ?: null, $song->id);
        $this->plannedKey = (string) ($preferred ?: $version?->original_key ?: '');
        if ($refreshReference) $this->specificYoutubeUrl = (string) ($version?->youtube_url ?? '');
    }

    private function applyOpeningSongSuggestion(string $songId, bool $refreshReference = true): void
    {
        $song = Song::query()->with('versions')->where('status', 'active')->findOrFail($songId);
        $version = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
        $preferred = app(MinisterSongKeyService::class)->preferredKey($this->openingMinisterId ?: null, $song->id);
        $this->openingKey = (string) ($preferred ?: $version?->original_key ?: '');
        if ($refreshReference) $this->openingYoutubeUrl = (string) ($version?->youtube_url ?? '');
    }

    private function serviceEnded(): bool
    {
        $endsAt = $this->service->ends_at ?: $this->service->starts_at->copy()->addHours(2);
        return $endsAt->isPast();
    }
}
