@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-black">Eventos especiais</h1>
        <p class="text-sm text-slate-500">Eventos extraordinários do ministério com confirmação de participação, ministro e repertório.</p>
    </div>
    @if(auth()->user()->hasPermission('special_events.manage'))
        <a href="{{ route('special-events.create') }}" class="sgml-btn-primary">Novo evento especial</a>
    @endif
</div>

<div class="mb-5 flex flex-wrap items-center gap-2">
    <a class="sgml-btn-secondary" href="{{ route('special-events.index',['month'=>$month->copy()->subMonth()->format('Y-m')]) }}">←</a>
    <x-month-year-picker :month="$month" route-name="special-events.index" parameter="month" id="special-events-month-picker" />
    <a class="sgml-btn-secondary" href="{{ route('special-events.index',['month'=>$month->copy()->addMonth()->format('Y-m')]) }}">→</a>
</div>

<div class="space-y-4">
    @forelse($events as $event)
        @php
            $yes = $event->responses->where('response','yes')->count();
            $no = $event->responses->where('response','no')->count();
            $minister = $event->service?->assignment?->minister?->display_name;
        @endphp
        <a href="{{ route('special-events.show',$event) }}" class="sgml-card block p-5 transition hover:border-indigo-300 hover:bg-indigo-50/40">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-black">{{ $event->name }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ $event->starts_at->format('d/m/Y H:i') }}@if($event->ends_at) - {{ $event->ends_at->format('H:i') }}@endif</div>
                    <div class="mt-2 text-sm"><strong>Ministro:</strong> {{ $minister ?: 'Ainda não definido' }}</div>
                </div>
                <div class="flex gap-2 text-xs">
                    <span class="sgml-badge bg-emerald-50 text-emerald-700">{{ $yes }} confirma(m)</span>
                    <span class="sgml-badge bg-rose-50 text-rose-700">{{ $no }} não pode(m)</span>
                    @if($event->status==='cancelled')<span class="sgml-badge bg-rose-100 text-rose-700">CANCELADO</span>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="sgml-card p-10 text-center text-slate-500">Nenhum evento especial neste mês.</div>
    @endforelse
</div>
@endsection
