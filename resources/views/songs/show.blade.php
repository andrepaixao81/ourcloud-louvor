@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3"><div><a href="{{ route('songs.index') }}" class="text-sm font-semibold text-indigo-600">← Biblioteca</a><h1 class="mt-2 text-2xl font-black">{{ $song->title }}</h1><p class="text-slate-500">{{ $song->artist_reference ?: 'Sem referência de artista' }}</p></div><div class="flex gap-2">@perm('songs.update')<a class="sgml-btn-secondary" href="{{ route('songs.edit',$song) }}">Editar</a>@endperm @perm('songs.archive')<form method="POST" action="{{ route('songs.destroy',$song) }}" data-confirm-message="Excluir este louvor da biblioteca? O histórico de cultos será preservado.">@csrf @method('DELETE')<button class="sgml-btn-danger">Excluir</button></form>@endperm</div></div>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div class="sgml-card p-5"><div class="text-sm text-slate-500">Ministrações</div><div class="mt-1 text-3xl font-black">{{ $stats['total'] }}</div></div><div class="sgml-card p-5"><div class="text-sm text-slate-500">Últimos 30 dias</div><div class="mt-1 text-3xl font-black">{{ $stats['last_30_days'] }}</div></div><div class="sgml-card p-5"><div class="text-sm text-slate-500">Últimos 90 dias</div><div class="mt-1 text-3xl font-black">{{ $stats['last_90_days'] }}</div></div><div class="sgml-card p-5"><div class="text-sm text-slate-500">Última ministração</div><div class="mt-2 font-bold">{{ $stats['last_execution']?->format('d/m/Y') ?? 'Nunca' }}</div></div></div>
<div class="mt-6 sgml-card p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Tons lembrados por ministro</h2>
            <p class="mt-1 text-sm text-slate-500">O sistema guarda o último tom definido no repertório/ensaio ou efetivamente ministrado por cada ministro para este louvor.</p>
        </div>
    </div>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($song->ministerKeys->sortByDesc('last_used_at') as $ministerKey)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="font-semibold text-slate-900">{{ $ministerKey->person?->display_name ?: 'Ministro' }}</div>
                    <span class="sgml-badge bg-indigo-50 text-indigo-700">Tom {{ $ministerKey->preferred_key }}</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    @if($ministerKey->lastService)
                        Última referência: {{ $ministerKey->lastService->starts_at?->format('d/m/Y') ?: 'culto registrado' }}
                    @elseif($ministerKey->last_used_at)
                        Atualizado em {{ $ministerKey->last_used_at->format('d/m/Y') }}
                    @else
                        Tom definido para próximas ministrações
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500 md:col-span-2 xl:col-span-3">Ainda não há tom personalizado para este louvor. Quando um ministro definir o tom no repertório ou durante o ensaio, ele aparecerá aqui automaticamente.</p>
        @endforelse
    </div>
</div>

<div class="mt-6 sgml-card p-5"><h2 class="text-lg font-bold">Versões</h2><div class="mt-3 divide-y divide-slate-100">@forelse($song->versions as $version)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><div><div class="font-semibold">{{ $version->name }} @if($version->is_default)<span class="sgml-badge bg-indigo-50 text-indigo-700">Padrão</span>@endif</div><div class="text-xs text-slate-500">{{ $version->artist }} · Tom {{ $version->original_key ?: '—' }} · BPM {{ $version->bpm ?: '—' }} · {{ $version->active?'ativa':'arquivada' }}</div>@if($version->youtube_url)<a class="text-xs font-semibold text-indigo-600" href="{{ $version->youtube_url }}" target="_blank" rel="noopener">Abrir referência</a>@endif</div>@perm('songs.update')<div class="flex gap-2">@if(!$version->is_default && $version->active)<form method="POST" action="{{ route('songs.versions.default',[$song,$version]) }}">@csrf<button class="text-sm font-semibold text-indigo-600">Definir padrão</button></form>@endif @if($version->active)<form method="POST" action="{{ route('songs.versions.archive',[$song,$version]) }}">@csrf<button class="text-sm font-semibold text-rose-600">Arquivar</button></form>@endif</div>@endperm</div>@empty<p class="py-6 text-sm text-slate-500">Nenhuma versão cadastrada.</p>@endforelse</div></div>
@perm('songs.update')<form method="POST" action="{{ route('songs.versions.store',$song) }}" class="mt-6 sgml-card grid gap-3 p-5 md:grid-cols-2">@csrf<h2 class="md:col-span-2 font-bold">Adicionar versão</h2><input class="sgml-input" name="name" placeholder="Nome da versão" required><input class="sgml-input" name="artist" placeholder="Artista"><input class="sgml-input" type="url" name="youtube_url" placeholder="YouTube opcional"><div class="grid grid-cols-2 gap-2"><input class="sgml-input" name="original_key" placeholder="Tom"><input class="sgml-input" type="number" min="30" max="300" name="bpm" placeholder="BPM"></div><textarea class="sgml-input md:col-span-2" name="notes" placeholder="Observações"></textarea><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Tornar versão padrão</label><div class="md:text-right"><button class="sgml-btn-primary">Adicionar versão</button></div></form>@endperm
@endsection
