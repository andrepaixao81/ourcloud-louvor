@extends('layouts.app')
@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-black">Louvores</h1>
    <p class="text-sm text-slate-500">Biblioteca oficial e histórico real dos louvores ministrados.</p>
</div>
<div class="mb-6 flex flex-wrap gap-2">
    @perm('songs.view')<a href="{{ route('songs.index') }}" class="sgml-btn-secondary">Biblioteca de louvores</a>@endperm
    <a href="{{ route('songs.history') }}" class="sgml-btn-primary">Louvores ministrados</a>
</div>
<div class="mb-5">
    <h2 class="text-xl font-black">Louvores ministrados</h2>
    <p class="text-sm text-slate-500">Após o horário final do culto, o repertório vigente entra automaticamente neste histórico. Correções posteriores no repertório também atualizam os registros.</p>
</div>

<form method="GET" class="mb-6 sgml-card grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-5">
    <div>
        <label class="sgml-label">Ministro</label>
        <select class="sgml-input" name="minister_id">
            <option value="">Todos os ministros</option>
            @foreach($ministers as $minister)
                <option value="{{ $minister->id }}" @selected($ministerId === $minister->id)>{{ $minister->display_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="sgml-label">Louvor</label>
        <select class="sgml-input" name="song_id">
            <option value="">Todos os louvores</option>
            @foreach($songs as $song)
                <option value="{{ $song->id }}" @selected($songId === $song->id)>{{ $song->title }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="sgml-label">Buscar pelo nome</label>
        <input class="sgml-input" name="q" value="{{ $songQuery }}" placeholder="Ex.: Canção de Gratidão">
    </div>
    <div>
        <label class="sgml-label">De</label>
        <input class="sgml-input" type="date" name="from" value="{{ $from->format('Y-m-d') }}">
    </div>
    <div>
        <label class="sgml-label">Até</label>
        <input class="sgml-input" type="date" name="to" value="{{ $to->format('Y-m-d') }}">
    </div>
    <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-2">
        <button class="sgml-btn-primary">Consultar</button>
        <a class="sgml-btn-secondary" href="{{ route('songs.history') }}">Limpar filtros</a>
    </div>
</form>

@if($ministerId)
<section class="mb-6 sgml-card p-5">
    <h2 class="text-lg font-black">Louvores ministrados por {{ $ministers->firstWhere('id',$ministerId)?->display_name ?? 'ministro selecionado' }}</h2>
    <p class="mb-4 text-xs text-slate-500">O mesmo louvor pode aparecer com vários tons. A contagem considera o repertório consolidado após o culto e eventuais correções posteriores.</p>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500"><th class="px-2 py-3">Louvor</th><th class="px-2 py-3">Categoria</th><th class="px-2 py-3">Tons usados</th><th class="px-2 py-3">Vezes</th><th class="px-2 py-3">Última</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($ministerSongs as $item)
                <tr><td class="px-2 py-3 font-semibold">{{ $item['title'] }}</td><td class="px-2 py-3 text-slate-500">{{ $item['category'] ?: '—' }}</td><td class="px-2 py-3"><div class="flex flex-wrap gap-1">@foreach($item['keys'] as $key)<span class="sgml-badge bg-indigo-50 text-indigo-700">{{ $key['key'] }} · {{ $key['count'] }}x</span>@endforeach</div></td><td class="px-2 py-3 font-bold">{{ $item['count'] }}</td><td class="px-2 py-3 text-slate-500">{{ $item['last_at']?->format('d/m/Y') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-2 py-8 text-center text-slate-500">Nenhum louvor registrado para este ministro no período.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif

@if($songId || $songQuery !== '')
<section class="mb-6 sgml-card p-5">
    <h2 class="text-lg font-black">Ministros e tons deste louvor</h2>
    <p class="mb-4 text-xs text-slate-500">Registros antigos de cultos com vários ministros podem aparecer como “não individualizado” quando o sistema ainda não gravava quem conduziu cada louvor.</p>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($songMinisters as $item)
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-3"><strong>{{ $item['minister'] }}</strong><span class="sgml-badge bg-slate-100 text-slate-700">{{ $item['count'] }}x</span></div>
                <div class="mt-3 flex flex-wrap gap-1">@foreach($item['keys'] as $key)<span class="sgml-badge bg-indigo-50 text-indigo-700">Tom {{ $key['key'] }} · {{ $key['count'] }}x</span>@endforeach</div>
                <div class="mt-2 text-xs text-slate-500">Último registro: {{ $item['last_at']?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 py-8 text-center text-slate-500">Nenhuma execução encontrada para este louvor no período.</div>
        @endforelse
    </div>
</section>
@endif

<section class="sgml-card p-5">
    <h2 class="text-lg font-black">Execuções recentes</h2>
    <div class="mt-3 divide-y divide-slate-100">
        @forelse($executions as $execution)
            @php($service = $execution->serviceExecution?->service)
            <div class="grid gap-2 py-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_100px_160px] sm:items-center">
                <div><strong>{{ $execution->song?->title ?? $execution->song_title_snapshot ?? 'Louvor' }}</strong><div class="text-xs text-slate-500">{{ $service?->name ?? 'Culto' }}</div></div>
                <div class="text-sm">{{ $execution->resolvedPerformer?->display_name ?? 'Ministro não individualizado' }}</div>
                <div class="text-sm"><span class="sgml-badge bg-indigo-50 text-indigo-700">Tom {{ $execution->performed_key ?: '—' }}</span></div>
                <div class="text-xs text-slate-500">{{ $service?->starts_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        @empty
            <p class="py-8 text-center text-slate-500">Nenhum louvor ministrado neste período.</p>
        @endforelse
    </div>
</section>
@endsection
