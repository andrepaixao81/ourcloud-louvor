@extends('layouts.app')
@section('content')
@php
    $repertoire = $service->repertoire;
    $items = $repertoire?->items ?? collect();
    $ministers = collect([$service->assignment?->minister?->display_name])
        ->filter()
        ->merge($service->additionalMinisters->pluck('minister.display_name')->filter());
@endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a class="text-sm font-semibold text-indigo-600" href="{{ url()->previous() }}">← Voltar à Agenda</a>
        <h1 class="mt-2 text-2xl font-black">{{ $service->name }}</h1>
        <p class="text-slate-500">{{ $service->starts_at->format('d/m/Y H:i') }}@if($service->ends_at) - {{ $service->ends_at->format('H:i') }}@endif</p>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div class="sgml-card p-5"><div class="text-xs font-bold uppercase text-slate-400">Ministro(s)</div><div class="mt-2 font-bold">{{ $ministers->implode(', ') ?: 'Ainda não definido' }}</div></div>
    <div class="sgml-card p-5"><div class="text-xs font-bold uppercase text-slate-400">Tipo de culto</div><div class="mt-2 font-bold">{{ $service->type?->name ?? '—' }}</div></div>
    <div class="sgml-card p-5"><div class="text-xs font-bold uppercase text-slate-400">Local</div><div class="mt-2 font-bold">{{ $service->location?->name ?? $service->congregation?->name ?? '—' }}</div></div>
</div>

@if($service->description || $service->notes)
<div class="mt-4 sgml-card p-5">
    <h2 class="font-black">Informações adicionais</h2>
    @if($service->description)<p class="mt-2 text-sm text-slate-700">{{ $service->description }}</p>@endif
    @if($service->notes)<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $service->notes }}</p>@endif
</div>
@endif

<div class="mt-4 sgml-card p-5">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="font-black">Louvores</h2><p class="text-sm text-slate-500">Repertório atualmente vinculado a este culto.</p></div>
        @perm('repertoire.view')<a class="sgml-btn-secondary" href="{{ route('repertoire.show', $service) }}">Abrir repertório</a>@endperm
    </div>
    @if($repertoire?->openingSong || $items->isNotEmpty())
        <div class="space-y-2">
            @if($repertoire?->openingSong)
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3"><strong>Louvor de abertura:</strong> {{ $repertoire->openingSong->title }}@if($repertoire->openingMinister) · Ministro {{ $repertoire->openingMinister->display_name }}@endif @if($repertoire->opening_key) · Tom {{ $repertoire->opening_key }}@endif @if($repertoire->opening_notes)<div class="mt-1 text-sm text-slate-600">{{ $repertoire->opening_notes }}</div>@endif</div>
            @endif
            @foreach($items as $item)
                <div class="rounded-xl border border-slate-200 p-3"><strong>Louvor {{ $loop->iteration }}:</strong> {{ $item->song?->title ?? 'Louvor' }}@if($item->minister) · Ministro {{ $item->minister->display_name }}@endif @if($item->planned_key) · Tom {{ $item->planned_key }}@endif @if($item->notes)<div class="mt-1 text-sm text-slate-600">{{ $item->notes }}</div>@endif</div>
            @endforeach
            @if($repertoire?->final_notes)<div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><strong>Observações:</strong><div class="mt-1 whitespace-pre-line text-slate-600">{{ $repertoire->final_notes }}</div></div>@endif
        </div>
    @else
        <p class="py-8 text-center text-slate-500">O repertório ainda não foi preenchido.</p>
    @endif
</div>
@endsection
