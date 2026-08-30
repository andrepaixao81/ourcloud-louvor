@extends('layouts.app')
@section('content')
@php
    $weekdayNames = [0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado'];
@endphp
<div class="sgml-page-header">
    <div>
        <div class="sgml-page-kicker">Repertórios</div>
        <h1 class="sgml-page-title">Louvores programados</h1>
        <p class="sgml-page-description">Veja rapidamente o que será ministrado nos próximos cultos publicados, sem carregar a Agenda administrativa.</p>
    </div>
    <span class="sgml-badge-info"><x-icon name="music" class="h-3.5 w-3.5"/>Próximos 60 dias</span>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    @forelse($services as $service)
        @php
            $repertoire = $service->repertoire;
            $ministers = collect([$service->assignment?->minister?->display_name])
                ->merge($service->additionalMinisters->pluck('minister.display_name'))
                ->filter()->unique()->values();
            $copyLines = collect([
                $service->name,
                $service->starts_at->format('d/m/Y H:i'),
                $ministers->isNotEmpty() ? 'Ministro(s): '.$ministers->join(', ') : null,
                '',
            ]);
            if ($repertoire?->openingSong) {
                $copyLines->push('Abertura: '.$repertoire->openingSong->title.($repertoire->openingMinister ? ' - Ministro '.$repertoire->openingMinister->display_name : '').($repertoire->opening_key ? ' - Tom '.$repertoire->opening_key : ''));
            }
            foreach ($repertoire?->items ?? collect() as $copyItem) {
                $copyLines->push($copyItem->position.'. '.($copyItem->song?->title ?? 'Louvor').($copyItem->minister ? ' - Ministro '.$copyItem->minister->display_name : '').($copyItem->planned_key ? ' - Tom '.$copyItem->planned_key : ''));
            }
            $copyText = $copyLines->filter(fn ($line) => $line !== null)->implode("\n");
        @endphp
        <article class="sgml-card-interactive overflow-hidden">
            <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/90 to-violet-50/60 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="sgml-badge-info">{{ $weekdayNames[$service->starts_at->dayOfWeek] ?? '' }}</span>
                            @if($service->type?->name)<span class="sgml-badge-muted">{{ $service->type->name }}</span>@endif
                        </div>
                        <h2 class="truncate text-lg font-black text-slate-950">{{ $service->name }}</h2>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="h-4 w-4"/>{{ $service->starts_at->format('d/m/Y') }}</span>
                            <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="h-4 w-4"/>{{ $service->starts_at->format('H:i') }}</span>
                            @if($service->location?->name)<span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="h-4 w-4"/>{{ $service->location->name }}</span>@endif
                        </div>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white text-indigo-700 shadow-sm ring-1 ring-inset ring-indigo-100"><x-icon name="music"/></div>
                </div>
                <div class="mt-4 flex items-start gap-2 text-sm text-slate-700"><x-icon name="mic" class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500"/><div><span class="font-bold">Ministro(s):</span> {{ $ministers->join(', ') ?: 'A definir' }}</div></div>
            </div>

            <div class="p-5">
                <div class="mb-3 flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">Ordem do repertório</div>@if($repertoire?->openingSong || ($repertoire?->items?->isNotEmpty() ?? false))<button type="button" class="sgml-btn-tertiary !min-h-8 !px-2 !py-1 text-xs" data-copy-text="{{ e($copyText) }}">Copiar repertório</button>@endif</div>
                <div class="space-y-2">
                    @if($repertoire?->openingSong)
                        @php
                            $openingVersion = $repertoire->openingSongVersion ?? $repertoire->openingSong->versions?->firstWhere('is_default', true) ?? $repertoire->openingSong->versions?->first();
                            $openingReference = $openingVersion?->youtube_url;
                        @endphp
                        <div class="flex items-start gap-3 rounded-2xl bg-violet-50/70 p-3.5 ring-1 ring-inset ring-violet-100">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-xs font-black text-violet-700">A</span>
                            <div class="min-w-0 flex-1"><div class="font-bold text-slate-900">{{ $repertoire->openingSong->title }}</div><div class="mt-1 flex flex-wrap items-center gap-2">@if($repertoire->openingMinister)<span class="sgml-badge bg-violet-50 text-violet-700">{{ $repertoire->openingMinister->display_name }}</span>@endif @if($repertoire->opening_key)<span class="sgml-badge-info">Tom {{ $repertoire->opening_key }}</span>@endif @if($openingReference)<a class="text-xs font-bold text-indigo-700 hover:underline" href="{{ $openingReference }}" target="_blank" rel="noopener">Ouvir referência ↗</a>@endif</div></div>
                        </div>
                    @endif
                    @forelse($repertoire?->items ?? collect() as $item)
                        @php
                            $defaultVersion = $item->song?->versions?->firstWhere('is_default', true) ?? $item->song?->versions?->first();
                            $reference = $item->specific_youtube_url ?: $item->songVersion?->youtube_url ?: $defaultVersion?->youtube_url;
                        @endphp
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-100 p-3.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-black text-slate-600">{{ $item->position ?: $loop->iteration }}</span>
                            <div class="min-w-0 flex-1"><div class="font-bold text-slate-900">{{ $item->song?->title ?? 'Louvor' }}</div><div class="mt-1 flex flex-wrap items-center gap-2">@if($item->minister)<span class="sgml-badge bg-violet-50 text-violet-700">{{ $item->minister->display_name }}</span>@endif @if($item->planned_key)<span class="sgml-badge-info">Tom {{ $item->planned_key }}</span>@endif @if($reference)<a class="text-xs font-bold text-indigo-700 hover:underline" href="{{ $reference }}" target="_blank" rel="noopener">Ouvir referência ↗</a>@endif</div></div>
                        </div>
                    @empty
                        @if(! $repertoire?->openingSong)<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Repertório ainda não informado.</div>@endif
                    @endforelse
                </div>
            </div>
        </article>
    @empty
        <div class="sgml-card col-span-full p-10 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><x-icon name="music" class="h-7 w-7"/></div><h2 class="font-black">Nenhum repertório publicado</h2><p class="mt-1 text-sm text-slate-500">Não há cultos publicados nos próximos 60 dias.</p></div>
    @endforelse
</div>
@endsection
