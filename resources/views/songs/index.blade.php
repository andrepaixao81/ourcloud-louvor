@extends('layouts.app')

@section('content')
<div class="sgml-page-header">
    <div><div class="sgml-page-kicker">Repertório</div><h1 class="sgml-page-title">Biblioteca de Louvores</h1><p class="sgml-page-description">Encontre rapidamente músicas, tons, versões e referências usadas pelo ministério.</p></div>
    <div class="flex flex-wrap gap-2">
        @perm('songs.create')
            <a href="{{ route('songs.import') }}" class="sgml-btn-secondary">Importar</a>
            <a href="{{ route('songs.create') }}" class="sgml-btn-primary"><x-icon name="plus" class="h-4 w-4"/>Novo louvor</a>
        @endperm
    </div>
</div>
<div class="mb-5 flex flex-wrap gap-2">
    <a href="{{ route('songs.index') }}" class="sgml-btn-primary"><x-icon name="music" class="h-4 w-4"/>Biblioteca</a>
    @perm('song_history.view')<a href="{{ route('songs.history') }}" class="sgml-btn-secondary">Louvores ministrados</a>@endperm
</div>

<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,2fr)_minmax(220px,1fr)_auto]">
    <div>
        <label class="sgml-label">Buscar</label>
        <input class="sgml-input" name="q" value="{{ request('q') }}" placeholder="Título, título alternativo ou artista">
    </div>
    <div>
        <label class="sgml-label">Categoria</label>
        <select class="sgml-input" name="category_id">
            <option value="">Todas as categorias</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button class="sgml-btn-primary">Filtrar</button>
        @if(request()->filled('q') || request()->filled('category_id'))
            <a href="{{ route('songs.index') }}" class="sgml-btn-secondary">Limpar</a>
        @endif
    </div>
</form>

@if(request()->filled('category_id'))
    @php($selectedCategory = $categories->firstWhere('id', request('category_id')))
    @if($selectedCategory)
        <div class="mb-4 text-sm text-slate-500">Categoria selecionada: <strong class="text-slate-700">{{ $selectedCategory->name }}</strong></div>
    @endif
@endif

<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    @forelse($songs as $song)
        @php($defaultVersion = $song->versions->firstWhere('is_default', true) ?? $song->versions->first())
        <a href="{{ route('songs.show', $song) }}" class="sgml-card-interactive group p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-start gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 transition group-hover:bg-indigo-100"><x-icon name="music" class="h-4 w-4"/></span><div class="min-w-0"><div class="font-black text-slate-900">{{ $song->title }}</div>
                    @if($song->alternative_title)
                        <div class="mt-0.5 text-xs text-slate-400">Também: {{ $song->alternative_title }}</div>
                    @endif
                    <div class="mt-1 text-sm text-slate-500">{{ $song->artist_reference ?: $defaultVersion?->artist ?: 'Artista a confirmar' }}</div></div></div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span class="sgml-badge-info">{{ $song->category?->name ?? 'Sem categoria' }}</span>
                @if($defaultVersion?->original_key)
                    <span class="sgml-badge-muted">Tom {{ $defaultVersion->original_key }}</span>
                @endif
                <span class="sgml-badge bg-slate-100 text-slate-600">{{ $song->versions->where('active', true)->count() }} versão(ões)</span>
            </div>
        </a>
    @empty
        <div class="sgml-card p-10 text-center text-slate-500 md:col-span-2 xl:col-span-3">Nenhum louvor encontrado com estes filtros.</div>
    @endforelse
</div>

@if($songs->hasPages())
<div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
    <div class="text-slate-500">Página <strong class="text-slate-700">{{ $songs->currentPage() }}</strong> de <strong class="text-slate-700">{{ $songs->lastPage() }}</strong> · {{ $songs->total() }} louvor(es)</div>
    <div class="flex flex-wrap items-center gap-1">
        @if($songs->onFirstPage())<span class="rounded-lg border border-slate-200 px-3 py-2 text-slate-300">← Anterior</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50" href="{{ $songs->previousPageUrl() }}">← Anterior</a>@endif
        @php($startPage = max(1, $songs->currentPage() - 2))
        @php($endPage = min($songs->lastPage(), $songs->currentPage() + 2))
        @for($page = $startPage; $page <= $endPage; $page++)
            <a class="rounded-lg px-3 py-2 font-semibold {{ $page === $songs->currentPage() ? 'bg-indigo-600 text-white' : 'border border-slate-200 text-slate-700 hover:bg-slate-50' }}" href="{{ $songs->url($page) }}">{{ $page }}</a>
        @endfor
        @if($songs->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50" href="{{ $songs->nextPageUrl() }}">Próxima →</a>@else<span class="rounded-lg border border-slate-200 px-3 py-2 text-slate-300">Próxima →</span>@endif
    </div>
</div>
@endif

@perm('songs.request')
    <div class="mt-8 sgml-card p-5">
        <h2 class="font-bold">Solicitar novo louvor</h2>
        <form method="POST" action="{{ route('song-requests.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <input class="sgml-input" name="suggested_title" placeholder="Título" required>
            <input class="sgml-input" name="artist_reference" placeholder="Artista/referência">
            <input class="sgml-input" type="url" name="youtube_url" placeholder="Link YouTube opcional">
            <input class="sgml-input" name="suggested_key" placeholder="Tom sugerido">
            <textarea class="sgml-input md:col-span-2" name="notes" placeholder="Observações"></textarea>
            <div><button class="sgml-btn-primary">Enviar solicitação</button></div>
        </form>
    </div>
@endperm

@perm('songs.approve')
    <div class="mt-8 sgml-card p-5">
        <h2 class="font-bold">Solicitações recentes</h2>
        <div class="mt-3 divide-y divide-slate-100">
            @forelse($requests as $req)
                <div class="py-4">
                    <div class="flex flex-wrap justify-between gap-2">
                        <div><strong>{{ $req->suggested_title }}</strong><span class="ml-2 text-sm text-slate-500">{{ $req->artist_reference }}</span></div>
                        <span class="sgml-badge bg-slate-100 text-slate-700">{{ strtoupper($req->status) }}</span>
                    </div>
                    @if($req->requestedService)
                        <div class="mt-1 text-xs text-slate-500">Solicitado para o culto: <strong>{{ $req->requestedService->name }}</strong> · {{ $req->requestedService->starts_at?->format('d/m/Y H:i') }}</div>
                    @endif
                    @if($req->requester)
                        <div class="mt-1 text-xs text-slate-400">Solicitado por {{ $req->requester->name }}</div>
                    @endif
                    @if($req->status === 'pending')
                        <details class="mt-3 rounded-xl border border-slate-200 p-4" open>
                            <summary class="cursor-pointer font-semibold text-indigo-700">Revisar dados antes de aprovar</summary>
                            <form method="POST" action="{{ route('song-requests.approve', $req) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <div><label class="sgml-label">Título corrigido</label><input class="sgml-input" name="title" value="{{ $req->suggested_title }}" required></div>
                                <div><label class="sgml-label">Artista/referência</label><input class="sgml-input" name="artist_reference" value="{{ $req->artist_reference }}"></div>
                                <div><label class="sgml-label">Categoria</label><select class="sgml-input" name="category_id"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($req->suggested_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                                <div><label class="sgml-label">Tom original/sugerido</label><input class="sgml-input" name="original_key" value="{{ $req->suggested_key }}"></div>
                                <div class="sm:col-span-2"><label class="sgml-label">Link de referência</label><input class="sgml-input" type="url" name="youtube_url" value="{{ $req->youtube_url }}"></div>
                                <div class="sm:col-span-2"><label class="sgml-label">Observação da revisão</label><textarea class="sgml-input" name="review_notes" rows="2">{{ $req->review_notes }}</textarea></div>
                                @if($req->requestedService)
                                    <label class="sm:col-span-2 flex items-start gap-2 rounded-lg bg-indigo-50 p-3 text-sm"><input type="checkbox" name="add_to_repertoire" value="1" class="mt-1"> <span>Depois de aprovar, adicionar este louvor ao repertório do culto <strong>{{ $req->requestedService->name }}</strong>. A aprovação continua sendo necessária antes da inclusão.</span></label>
                                @endif
                                <div class="sm:col-span-2"><button class="sgml-btn-primary w-full sm:w-auto">Aprovar e incluir na biblioteca</button></div>
                            </form>
                            <form method="POST" action="{{ route('song-requests.reject', $req) }}" class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row">
                                @csrf
                                <input class="sgml-input" name="review_notes" placeholder="Motivo da rejeição" required>
                                <button class="sgml-btn-danger shrink-0">Rejeitar</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500">Nenhuma solicitação.</p>
            @endforelse
        </div>
    </div>
@endperm
@endsection
