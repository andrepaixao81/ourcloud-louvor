@extends('layouts.app')
@section('content')
@php
    $weekdayNames = [0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado'];
@endphp
<div data-calendar-auto-view data-explicit-view="{{ request()->has('view') ? '1' : '0' }}" data-view="{{ $view }}" data-list-url="{{ route('calendar.index', ['view'=>'list','date'=>$date->format('Y-m-d')]) }}">
    <div class="sgml-page-header">
        <div>
            <div class="sgml-page-kicker">Planejamento</div>
            <h1 class="sgml-page-title">Agenda do Ministério</h1>
            <p class="sgml-page-description">Cultos publicados e programações informativas em uma única linha do tempo. Avisos criados aqui não geram escala automaticamente.</p>
        </div>
        @perm('calendar_event.create')<a href="{{ route('calendar.events.create') }}" class="sgml-btn-primary"><x-icon name="plus" class="h-4 w-4"/>Nova programação</a>@endperm
    </div>

    <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex overflow-x-auto rounded-xl bg-slate-100 p-1">
            @foreach(['month'=>'Mês','week'=>'Semana','day'=>'Dia','list'=>'Lista'] as $key=>$label)
                <a class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold transition {{ $view===$key ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}" href="{{ route('calendar.index',['view'=>$key,'date'=>$date->format('Y-m-d')]) }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex items-center gap-2">
            @if($view==='month')
                <a class="sgml-btn-secondary !px-3" href="{{ route('calendar.index',['view'=>'month','date'=>$date->copy()->subMonth()->startOfMonth()->format('Y-m-d')]) }}" aria-label="Mês anterior"><x-icon name="chevron-left" class="h-4 w-4"/></a>
                <x-month-year-picker :month="$date" route-name="calendar.index" :query="['view'=>'month']" parameter="date" id="calendar-month-picker" />
                <a class="sgml-btn-secondary !px-3" href="{{ route('calendar.index',['view'=>'month','date'=>$date->copy()->addMonth()->startOfMonth()->format('Y-m-d')]) }}" aria-label="Próximo mês"><x-icon name="chevron-right" class="h-4 w-4"/></a>
            @else
                <form method="GET" class="flex min-w-0 gap-2"><input type="hidden" name="view" value="{{ $view }}"><input class="sgml-input min-w-0 max-w-44" type="date" name="date" value="{{ $date->format('Y-m-d') }}"><button class="sgml-btn-secondary">Ir</button></form>
            @endif
        </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl bg-slate-50 px-4 py-3">
        <span class="sgml-calendar-legend sgml-calendar-legend-service">Culto / escala</span>
        <span class="sgml-calendar-legend sgml-calendar-legend-custom">Programação / aviso</span>
        <span class="sgml-calendar-legend sgml-calendar-legend-cancelled">Cancelado</span>
    </div>

    @if($view==='month')
        @php
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $leading = $monthStart->dayOfWeek;
            $cells = [];
            for($i=0;$i<$leading;$i++) $cells[] = null;
            for($d=1;$d<=$monthEnd->day;$d++) $cells[] = $monthStart->copy()->day($d);
            while(count($cells)%7!==0) $cells[] = null;
        @endphp
        <div class="sgml-card overflow-x-auto p-0">
            <div class="min-w-[850px]">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[11px] font-black tracking-wider text-slate-400">@foreach(['DOM','SEG','TER','QUA','QUI','SEX','SÁB'] as $label)<div class="p-3">{{ $label }}</div>@endforeach</div>
                <div class="grid grid-cols-7">
                    @foreach($cells as $day)
                        @php($isToday = $day && $day->isToday())
                        <div class="min-h-36 border-b border-r border-slate-100 p-2.5 {{ $isToday ? 'bg-indigo-50/30' : '' }}">
                            @if($day)
                                <div class="mb-2 flex h-7 w-7 items-center justify-center rounded-full text-sm font-black {{ $isToday ? 'bg-indigo-600 text-white' : 'text-slate-700' }}">{{ $day->day }}</div>
                                <div class="space-y-1.5">
                                    @foreach($entriesByDate[$day->format('Y-m-d')] ?? collect() as $entry)
                                        @if($entry->source_type === 'service' && $entry->source_id)
                                            <a href="{{ route('calendar.service-detail', $entry->source_id) }}" class="block rounded-xl border-l-[3px] {{ $entry->status==='cancelled' ? 'border-rose-500 bg-rose-50 text-rose-800' : 'border-indigo-500 bg-indigo-50/70 text-slate-800' }} px-2.5 py-2 text-xs transition hover:shadow-sm">
                                                <div class="font-black">{{ $entry->starts_at->format('H:i') }} · {{ $entry->title }}</div>
                                                <div class="mt-1 text-[10px] font-bold {{ $entry->status==='cancelled' ? 'text-rose-600' : 'text-indigo-600' }}">{{ $entry->status==='cancelled' ? 'Cancelado' : 'Culto · ver detalhes' }}</div>
                                            </a>
                                        @else
                                            <div class="rounded-xl border-l-[3px] {{ $entry->status==='cancelled' ? 'border-rose-500 bg-rose-50' : 'border-emerald-500 bg-emerald-50/80' }} px-2.5 py-2 text-xs">
                                                <div class="font-black text-slate-800">{{ $entry->starts_at->format('H:i') }} · {{ $entry->title }}</div>
                                                <div class="mt-1 text-[10px] font-bold {{ $entry->status==='cancelled' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $entry->status==='cancelled' ? 'Programação cancelada' : 'Programação / aviso' }}</div>
                                                @perm('calendar_event.update')<a class="mt-1.5 inline-block font-black text-indigo-600" href="{{ route('calendar.events.edit', $entry) }}">Editar</a>@endperm
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        @php($groups = $entries->groupBy(fn ($entry) => $entry->starts_at->format('Y-m-d')))
        <div class="mb-3 text-sm font-semibold text-slate-500">{{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}</div>
        <div class="space-y-6">
            @forelse($groups as $dateKey => $dateEntries)
                @php($groupDate = \Carbon\Carbon::parse($dateKey))
                <section>
                    <div class="mb-2 flex items-baseline gap-2"><h2 class="text-base font-black text-slate-900">{{ ucfirst($weekdayNames[$groupDate->dayOfWeek] ?? '') }}</h2><span class="text-sm font-semibold text-slate-400">{{ $groupDate->format('d/m/Y') }}</span>@if($groupDate->isToday())<span class="sgml-badge-info">Hoje</span>@endif</div>
                    <div class="space-y-2">
                        @foreach($dateEntries as $entry)
                            <article class="sgml-card-interactive flex gap-4 p-4 sm:p-5">
                                <div class="w-14 shrink-0 text-center"><div class="text-lg font-black text-slate-900">{{ $entry->starts_at->format('H:i') }}</div>@if($entry->ends_at)<div class="text-xs font-semibold text-slate-400">{{ $entry->ends_at->format('H:i') }}</div>@endif</div>
                                <div class="min-w-0 flex-1 border-l border-slate-100 pl-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2"><h3 class="font-black text-slate-900">{{ $entry->title }}</h3>@if($entry->status==='cancelled')<span class="sgml-badge-danger">Cancelado</span>@elseif($entry->source_type === 'custom')<span class="sgml-badge-success">Programação / aviso</span>@else<span class="sgml-badge-info">Culto</span>@endif</div>
                                            @if($entry->source_type === 'custom')<p class="mt-1 text-xs text-slate-500">Informativo: não gera escala ministerial automaticamente.</p>@endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($entry->source_type === 'service' && $entry->source_id)<a class="sgml-btn-tertiary" href="{{ route('calendar.service-detail',$entry->source_id) }}">Detalhes e louvores</a>@elseif($entry->source_type === 'custom')@perm('calendar_event.update')<a class="sgml-btn-tertiary" href="{{ route('calendar.events.edit',$entry) }}">Editar</a>@endperm @endif
                                            @perm('calendar.export')<a class="sgml-btn-secondary !min-h-9 !px-3 !py-1.5 text-xs" href="{{ route('calendar.event.ics',$entry) }}">ICS</a>@endperm
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="sgml-card p-10 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><x-icon name="calendar" class="h-7 w-7"/></div><h2 class="font-black">Nenhum compromisso nesta janela</h2><p class="mt-1 text-sm text-slate-500">Altere o período ou crie uma nova programação.</p></div>
            @endforelse
        </div>
    @endif
</div>
@endsection
