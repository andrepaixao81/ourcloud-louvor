@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('reports.index', array_filter(['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')])) }}" class="text-sm font-semibold text-indigo-600">← Relatórios</a>
        <h1 class="mt-2 text-2xl font-black">{{ $songModel->title }}</h1>
        <p class="text-sm text-slate-500">Cultos e ministros associados às execuções registradas deste louvor.</p>
    </div>
</div>

<form method="GET" class="mb-6 sgml-card p-5">
    <div class="grid gap-3 md:grid-cols-[minmax(180px,1fr)_minmax(180px,1fr)_auto_auto] md:items-end">
        <div>
            <label class="sgml-label">Data inicial</label>
            <input class="sgml-input" type="date" name="from" value="{{ $from?->format('Y-m-d') }}">
        </div>
        <div>
            <label class="sgml-label">Data final</label>
            <input class="sgml-input" type="date" name="to" value="{{ $to?->format('Y-m-d') }}">
        </div>
        <button class="sgml-btn-primary">Aplicar período</button>
        <a class="sgml-btn-secondary text-center" href="{{ route('reports.song', ['song' => $songModel->id]) }}">Limpar</a>
    </div>
</form>

<div class="space-y-3">
    @forelse($executions as $execution)
        @php
            $service = $execution->serviceExecution?->service;
            $ministerNames = collect();
            if ($service?->assignment?->minister) {
                $ministerNames->push($service->assignment->minister->display_name);
            }
            if ($service?->additionalMinisters) {
                $ministerNames = $ministerNames->merge($service->additionalMinisters->pluck('minister.display_name')->filter());
            }
            if ($ministerNames->isEmpty() && $execution->performer) {
                $ministerNames->push($execution->performer->display_name);
            }
            $ministerNames = $ministerNames->filter()->unique()->values();
        @endphp
        <article class="sgml-card p-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(220px,auto)]">
                <div>
                    <div class="font-bold">{{ $service?->name ?? 'Culto não localizado' }}</div>
                    <div class="mt-1 text-sm text-slate-500">
                        {{ $service?->starts_at?->format('d/m/Y H:i') ?? $execution->started_at?->format('d/m/Y H:i') ?? 'Data não registrada' }}
                        @if($service?->type?->name)
                            · {{ $service->type->name }}
                        @endif
                        @if($service?->location?->name)
                            · {{ $service->location->name }}
                        @endif
                    </div>
                    <div class="mt-3 text-sm">
                        <strong>Ministro(s):</strong> {{ $ministerNames->isNotEmpty() ? $ministerNames->join(', ') : 'Não identificado' }}
                    </div>
                    @if($execution->performed_key || $execution->planned_key)
                        <div class="mt-1 text-sm text-slate-600"><strong>Tom:</strong> {{ $execution->performed_key ?: $execution->planned_key }}</div>
                    @endif
                    @if($execution->notes)
                        <div class="mt-1 text-sm text-slate-600"><strong>Observação:</strong> {{ $execution->notes }}</div>
                    @endif
                </div>
                @if($service)
                    <div class="flex items-start justify-end">
                        <a class="sgml-btn-secondary" href="{{ route('services.show', $service) }}">Ver culto</a>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <div class="sgml-card p-10 text-center text-slate-500">Este louvor não possui execuções registradas no período selecionado.</div>
    @endforelse
</div>

<div class="mt-5">{{ $executions->links() }}</div>
@endsection
