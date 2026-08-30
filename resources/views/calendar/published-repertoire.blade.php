@extends('layouts.app')
@section('content')
@php
    $isPublished = $repertoire && $repertoire->status === 'published';
    $ministers = collect([$assignment->minister?->display_name])->filter()->merge($service->additionalMinisters->pluck('minister.display_name')->filter())->values();
    $copyLines = collect([$service->name, $service->starts_at->format('d/m/Y H:i'), $ministers->isNotEmpty() ? 'Ministro(s): '.$ministers->join(', ') : null, '']);
    if($repertoire?->openingSong) $copyLines->push('Abertura: '.$repertoire->openingSong->title.($repertoire->openingMinister ? ' - Ministro '.$repertoire->openingMinister->display_name : '').($repertoire->opening_key ? ' - Tom '.$repertoire->opening_key : ''));
    foreach($items as $copyItem) $copyLines->push($copyItem->position.'. '.($copyItem->song?->title ?? 'Louvor').($copyItem->minister ? ' - Ministro '.$copyItem->minister->display_name : '').(($copyItem->planned_key) ? ' - Tom '.$copyItem->planned_key : ''));
    $copyText = $copyLines->filter(fn($line)=>$line!==null)->implode("\n");
@endphp
<div class="sgml-page-header">
    <div><a class="mb-2 inline-flex items-center gap-1 text-sm font-bold text-indigo-700" href="{{ route('my.agenda') }}"><x-icon name="chevron-left" class="h-4 w-4"/>Minha Agenda</a><div class="sgml-page-kicker">Repertório do culto</div><h1 class="sgml-page-title">{{ $service->name }}</h1><p class="sgml-page-description">{{ $service->starts_at->format('d/m/Y H:i') }}@if($service->location?->name) · {{ $service->location->name }}@endif</p></div>
    @if($items->isNotEmpty())<button type="button" class="sgml-btn-secondary" data-copy-text="{{ e($copyText) }}">Copiar repertório</button>@endif
</div>
<div class="mb-5 flex flex-wrap items-center gap-2"><span class="{{ $isPublished ? 'sgml-badge-success':'sgml-badge-warning' }}">{{ $isPublished ? '✓ Repertório publicado':'Em preparação' }}</span><span class="sgml-badge-muted"><x-icon name="mic" class="h-3.5 w-3.5"/>{{ $ministers->join(', ') ?: 'Ministro não definido' }}</span></div>

<section class="sgml-card p-5 sm:p-6">
    <div class="mb-5"><h2 class="sgml-section-title">Ordem planejada</h2><p class="sgml-section-description">Tons e referências para estudo do ministério.</p></div>
    <div class="space-y-3">
        @if($repertoire?->openingSong)
            @php
                $openingDefaultVersion = $repertoire->openingSong->versions?->firstWhere('is_default', true) ?? $repertoire->openingSong->versions?->first();
                $openingKey = $repertoire->opening_key ?: $repertoire->openingSongVersion?->original_key ?: $openingDefaultVersion?->original_key;
                $openingReference = $repertoire->opening_youtube_url ?: $repertoire->openingSongVersion?->youtube_url ?: $openingDefaultVersion?->youtube_url;
            @endphp
            <article class="flex items-start gap-4 rounded-2xl border border-violet-200 bg-violet-50/30 p-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-sm font-black text-violet-700">A</span>
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="text-base font-black text-slate-900">{{ $repertoire->openingSong->title }}</h3><div class="mt-1 text-sm text-slate-500">Ministro: {{ $repertoire->openingMinister?->display_name ?: 'a definir' }}</div></div>@if($openingKey)<span class="sgml-badge-info">Tom {{ $openingKey }}</span>@endif</div>@if($repertoire->opening_notes)<div class="mt-3 rounded-xl bg-white/70 px-3 py-2 text-sm text-slate-600"><strong>Nota:</strong> {{ $repertoire->opening_notes }}</div>@endif @if($openingReference)<a class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:underline" href="{{ $openingReference }}" target="_blank" rel="noopener">Ouvir referência ↗</a>@endif</div>
            </article>
        @endif
        @forelse($items as $item)
            @php
                $defaultVersion = $item->song?->versions?->firstWhere('is_default', true) ?? $item->song?->versions?->first();
                $key = $item->planned_key ?: $item->songVersion?->original_key ?: $defaultVersion?->original_key;
                $artist = $item->song?->artist_reference ?: $item->songVersion?->artist ?: $defaultVersion?->artist;
                $reference = $item->specific_youtube_url ?: $item->songVersion?->youtube_url ?: $defaultVersion?->youtube_url;
            @endphp
            <article class="flex items-start gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/20">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">{{ $item->position }}</span>
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="text-base font-black text-slate-900">{{ $item->song?->title ?? 'Louvor' }}</h3><div class="mt-1 text-sm text-slate-500">{{ $item->song?->category?->name ?? 'Sem categoria' }}@if($artist) · {{ $artist }}@endif · Ministro: {{ $item->minister?->display_name ?: 'a definir' }}</div></div>@if($key)<span class="sgml-badge-info">Tom {{ $key }}</span>@endif</div>@if($item->notes)<div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"><strong>Nota:</strong> {{ $item->notes }}</div>@endif @if($reference)<a class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:underline" href="{{ $reference }}" target="_blank" rel="noopener">Ouvir referência ↗</a>@endif</div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center"><div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><x-icon name="music"/></div><h3 class="font-black">Repertório ainda vazio</h3><p class="mt-1 text-sm text-slate-500">Os louvores aparecerão aqui assim que forem adicionados.</p></div>
        @endforelse
    </div>
</section>
@endsection
