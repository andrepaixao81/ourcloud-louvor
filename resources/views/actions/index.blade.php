@extends('layouts.app')
@section('content')
@php
    $pendingCount = $items->getCollection()->where('status','pending')->count();
    $priorityMeta = [
        'critical'=>['label'=>'Crítica','class'=>'sgml-badge-danger'],
        'high'=>['label'=>'Alta','class'=>'sgml-badge-warning'],
        'normal'=>['label'=>'Normal','class'=>'sgml-badge-info'],
        'low'=>['label'=>'Baixa','class'=>'sgml-badge-muted'],
    ];
@endphp
<div class="sgml-page-header">
    <div><div class="sgml-page-kicker">Caixa de entrada</div><h1 class="sgml-page-title">Central de Ações</h1><p class="sgml-page-description">Tudo que exige sua atenção aparece primeiro. Informações permanecem separadas de tarefas operacionais.</p></div>
    @if($pendingCount)<span class="sgml-badge-warning"><x-icon name="bell" class="h-3.5 w-3.5"/>{{ $pendingCount }} pendente(s) nesta página</span>@endif
</div>

@if(($upcomingBirthdays ?? collect())->isNotEmpty())
<section class="mb-5 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><div class="flex items-center gap-2"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-amber-700 shadow-sm"><x-icon name="cake"/></span><div><h2 class="font-black text-amber-950">Próximos aniversários</h2><p class="text-xs text-amber-800">Pessoas do ministério nos próximos 7 dias</p></div></div><div class="mt-4 flex flex-wrap gap-2 text-sm">@foreach($upcomingBirthdays as $person)<span class="rounded-full bg-white/90 px-3 py-1.5 font-bold text-amber-900 shadow-sm">{{ $person->display_name }} · {{ $person->next_birthday_at->format('d/m') }} @if((int)$person->days_until_birthday===0)· hoje @elseif((int)$person->days_until_birthday===1)· amanhã @else· em {{ (int)$person->days_until_birthday }} dias @endif</span>@endforeach</div></div><a class="sgml-btn-secondary" href="{{ route('birthdays.index') }}">Ver aniversariantes</a></div>
</section>
@endif

<div class="space-y-3">
@forelse($items as $item)
    @php($meta=$priorityMeta[$item->priority] ?? $priorityMeta['normal'])
    <article class="sgml-card-interactive p-5 {{ $item->status !== 'pending' ? 'opacity-70' : '' }}">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="flex items-start gap-3"><div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $item->kind==='action' ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700' }}"><x-icon :name="$item->kind === 'action' ? 'bell' : 'info'"/></div><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="sgml-badge {{ $item->kind==='action'?'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200':'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200' }}">{{ $item->kind==='action'?'Ação necessária':'Informação' }}</span><span class="{{ $meta['class'] }}">{{ $meta['label'] }}</span></div><h2 class="mt-2 text-base font-black text-slate-900">{{ $item->title }}</h2><p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $item->message }}</p>@if($item->due_at)<p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold {{ $item->due_at->isPast() && $item->status==='pending' ? 'text-rose-600':'text-slate-500' }}"><x-icon name="clock" class="h-3.5 w-3.5"/>Prazo {{ $item->due_at->format('d/m/Y H:i') }}</p>@endif</div></div>
            <div class="flex flex-wrap gap-2 lg:justify-end">@if($item->action_url)<a class="{{ $item->kind==='action' ? 'sgml-btn-primary':'sgml-btn-secondary' }}" href="{{ route('actions.open', $item) }}"><x-icon name="chevron-right" class="h-4 w-4"/>Abrir</a>@endif @if($item->status==='pending')<form method="POST" action="{{ route('actions.complete',$item) }}">@csrf<button class="sgml-btn-secondary"><x-icon name="check" class="h-4 w-4"/>Concluir</button></form><form method="POST" action="{{ route('actions.dismiss',$item) }}">@csrf<button class="sgml-btn-tertiary text-slate-500">Dispensar</button></form>@endif</div>
        </div>
    </article>
@empty
    <div class="sgml-card p-10 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><x-icon name="check" class="h-7 w-7"/></div><h2 class="font-black">Nenhuma pendência</h2><p class="mt-1 text-sm text-slate-500">Você está em dia com as ações e informações do sistema.</p></div>
@endforelse
</div>
<div class="mt-5">{{ $items->links() }}</div>
@endsection
