@extends('layouts.app')
@section('content')
<div class="sgml-page-header">
    <div><div class="sgml-page-kicker">Visão geral</div><h1 class="sgml-page-title">Dashboard do Ministério</h1><p class="sgml-page-description">O que merece atenção agora e os principais indicadores do período.</p></div>
    <form method="GET" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-[1fr_1fr_auto]"><div><label class="sgml-label">De</label><input class="sgml-input" type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div><div><label class="sgml-label">Até</label><input class="sgml-input" type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div><div class="flex items-end"><button class="sgml-btn-primary w-full">Aplicar</button></div></form>
</div>

@php
    $attentionItems = collect([
        ['count'=>$pending_actions,'label'=>'ações aguardando você','url'=>route('actions.index'),'class'=>'bg-indigo-50 text-indigo-700','icon'=>'bell'],
        ['count'=>$pending_swaps,'label'=>'trocas aguardando resposta','url'=>route('swaps.index'),'class'=>'bg-amber-50 text-amber-700','icon'=>'repeat'],
        ['count'=>$upcoming_without_minister,'label'=>'cultos próximos sem ministro','url'=>route('schedule.index'),'class'=>'bg-rose-50 text-rose-700','icon'=>'mic'],
        ['count'=>$upcoming_without_repertoire,'label'=>'cultos próximos sem repertório','url'=>route('programmed-songs.index'),'class'=>'bg-violet-50 text-violet-700','icon'=>'music'],
    ])->filter(fn($item)=>(int)$item['count']>0)->values();
@endphp
<section class="mb-6">
    <div class="mb-3 flex items-center justify-between gap-3"><div><h2 class="sgml-section-title">Atenção necessária</h2><p class="sgml-section-description">Pendências operacionais que podem afetar os próximos cultos.</p></div>@if($attentionItems->isEmpty())<span class="sgml-badge-success"><x-icon name="check" class="h-3.5 w-3.5"/>Tudo em ordem</span>@endif</div>
    @if($attentionItems->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach($attentionItems as $attention)<a class="sgml-card-interactive flex items-center gap-4 p-4" href="{{ $attention['url'] }}"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $attention['class'] }}"><x-icon :name="$attention['icon']"/></div><div><div class="text-2xl font-black text-slate-950">{{ $attention['count'] }}</div><div class="text-xs font-semibold text-slate-500">{{ $attention['label'] }}</div></div></a>@endforeach</div>
    @else
        <div class="sgml-card flex items-center gap-4 p-5"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="check"/></div><div><div class="font-black">Nenhuma pendência operacional identificada</div><div class="mt-1 text-sm text-slate-500">Continue acompanhando as próximas escalas e repertórios.</div></div></div>
    @endif
</section>

<section class="mb-6">
    <div class="mb-3"><h2 class="sgml-section-title">Indicadores do período</h2><p class="sgml-section-description">Resumo de atividade ministerial entre {{ $from->format('d/m/Y') }} e {{ $to->format('d/m/Y') }}.</p></div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach([['Cultos planejados',$services_period],['Cultos realizados',$completed_services],['Ministrações',$ministerings_count],['Louvores ministrados',$songs_performed],['Média louvores/culto',$avg_songs_per_service],['Escalas publicadas',$published_assignments],['Trocas no período',$swaps_period],['Louvores ativos',$songs_active]] as [$label,$value])<div class="sgml-stat-card"><div class="sgml-stat-label">{{ $label }}</div><div class="sgml-stat-value">{{ $value }}</div></div>@endforeach</div>
</section>

<div class="grid gap-5 xl:grid-cols-2">
    <section class="sgml-card p-5 sm:p-6"><div class="mb-5"><h2 class="sgml-section-title">Ministros que mais ministraram</h2><p class="sgml-section-description">Cultos concluídos no período.</p></div><div class="space-y-4">@forelse($top_ministers as $item)@php($max=max(1,(int)($top_ministers->max('count')??1)))<div><div class="mb-1.5 flex justify-between gap-3 text-sm"><strong>{{ $item['name'] }}</strong><span class="font-black text-indigo-700">{{ $item['count'] }}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500" style="width:{{ max(4,round(($item['count']/$max)*100)) }}%"></div></div></div>@empty<p class="py-8 text-center text-sm text-slate-500">Ainda não há ministrações registradas.</p>@endforelse</div></section>
    <section class="sgml-card p-5 sm:p-6"><div class="mb-5"><h2 class="sgml-section-title">Louvores mais ministrados</h2><p class="sgml-section-description">Frequência, ministros e tons registrados.</p></div><div class="space-y-3">@forelse($top_songs as $item)<div class="rounded-2xl border border-slate-100 p-4"><div class="flex justify-between gap-3 text-sm"><strong>{{ $item['title'] }}</strong><span class="sgml-badge-success">{{ $item['count'] }}x</span></div>@if($item['minister_names'])<div class="mt-2 text-xs text-slate-500">Ministrado por: {{ $item['minister_names'] }}</div>@endif @if($item['tone_summary'])<div class="mt-1 text-xs text-slate-500">Tons: {{ $item['tone_summary'] }}</div>@endif</div>@empty<p class="py-8 text-center text-sm text-slate-500">Nenhum louvor executado neste período.</p>@endforelse</div></section>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <section class="sgml-card p-5 sm:p-6"><div class="mb-4"><h2 class="sgml-section-title">Cultos por dia e horário</h2><p class="sgml-section-description">Concentração operacional da agenda.</p></div><div class="divide-y divide-slate-100">@forelse($service_slots as $slot)<div class="flex items-start justify-between gap-3 py-3"><div><strong>{{ $slot['label'] }}</strong>@if($slot['common_name'])<div class="text-xs text-slate-500">Mais frequente: {{ $slot['common_name'] }}</div>@endif<div class="mt-1 text-xs text-slate-400">Realizados {{ $slot['completed'] }} de {{ $slot['count'] }}</div></div><span class="sgml-badge-muted">{{ $slot['count'] }} planejado(s)</span></div>@empty<p class="py-8 text-center text-sm text-slate-500">Sem cultos planejados.</p>@endforelse</div></section>
    <section class="sgml-card p-5 sm:p-6"><div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="sgml-section-title">Próximos cultos</h2><p class="sgml-section-description">Leitura rápida das próximas programações.</p></div>@if(auth()->user()->hasPermission('calendar.view'))<a href="{{ route('calendar.index',['view'=>'list']) }}" class="text-sm font-bold text-indigo-700">Ver agenda →</a>@endif</div><div class="space-y-2">@forelse($upcoming as $service)<a href="{{ route('services.show',$service) }}" class="flex items-center gap-3 rounded-2xl p-3 transition hover:bg-slate-50"><div class="sgml-date-tile !h-12 !w-12"><span>{{ $service->starts_at->translatedFormat('D') }}</span><strong class="!text-lg">{{ $service->starts_at->format('d') }}</strong></div><div class="min-w-0 flex-1"><div class="truncate font-black">{{ $service->name }}</div><div class="mt-0.5 text-xs text-slate-500">{{ $service->starts_at->format('H:i') }} · {{ collect([$service->assignment?->minister?->display_name])->filter()->merge($service->additionalMinisters->pluck('minister.display_name')->filter())->implode(', ') ?: 'Ministro ainda não definido' }}</div></div><x-icon name="chevron-right" class="h-4 w-4 text-slate-400"/></a>@empty<p class="py-8 text-center text-sm text-slate-500">Nenhum culto futuro cadastrado.</p>@endforelse</div></section>
</div>
@endsection
