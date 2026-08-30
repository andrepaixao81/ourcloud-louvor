@extends('layouts.app')
@section('content')
@php
    $weekdayNames = [0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado'];
    $weekdayShort = [0 => 'DOM', 1 => 'SEG', 2 => 'TER', 3 => 'QUA', 4 => 'QUI', 5 => 'SEX', 6 => 'SÁB'];
    $swapStatusNames = [
        'requested' => 'Aguardando resposta',
        'accepted' => 'Aceita e aplicada',
        'rejected' => 'Recusada',
        'approved' => 'Aplicada pela administração',
        'cancelled' => 'Cancelada',
    ];
    $currentUser = auth()->user();
    $canManageAvailability = $currentUser?->hasPermission('availability.manage') ?? false;
    $canViewRepertoire = $currentUser?->hasPermission('repertoire.view') ?? false;
    $canSwap = $currentUser?->hasPermission('schedule.swap') ?? false;
    $nextAssignment = $assignments->first();
    $nextService = $nextAssignment?->service;
    $nextRepertoire = $nextService?->repertoire;
    $nextItems = $nextRepertoire?->items ?? collect();
    $attentionCount = $incomingSwaps->count();
@endphp

<div class="sgml-page-header">
    <div>
        <div class="sgml-page-kicker">Agenda pessoal</div>
        <h1 class="sgml-page-title">{{ $assistedPerson ? 'Agenda de ' . $assistedPerson->display_name : 'Minha Agenda' }}</h1>
        <p class="sgml-page-description">{{ $assistedPerson ? 'Assistência administrativa: compromissos e operações do ministro selecionado.' : 'Seu próximo compromisso, repertórios e pendências em um só lugar.' }}</p>
    </div>
    @if(! $assistedPerson)
        <a class="sgml-btn-secondary" href="{{ route('calendar.sync') }}"><x-icon name="sync" class="h-4 w-4"/>Sincronizar agenda</a>
    @endif
</div>

@if($nextAssignment)
<section class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 p-5 text-white shadow-xl shadow-indigo-950/10 sm:p-7">
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-black uppercase tracking-wider ring-1 ring-inset ring-white/20">Seu próximo compromisso</span>
                <span class="rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-black text-emerald-50 ring-1 ring-inset ring-emerald-200/30">✓ Escala publicada</span>
            </div>
            <h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ $nextService->name }}</h2>
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-indigo-50">
                <span class="inline-flex items-center gap-2"><x-icon name="calendar" class="h-4 w-4"/>{{ $weekdayNames[$nextService->starts_at->dayOfWeek] ?? '' }}, {{ $nextService->starts_at->format('d/m') }}</span>
                <span class="inline-flex items-center gap-2"><x-icon name="clock" class="h-4 w-4"/>{{ $nextService->starts_at->format('H:i') }}@if($nextService->ends_at) - {{ $nextService->ends_at->format('H:i') }}@endif</span>
                @if($nextService->location?->name)<span class="inline-flex items-center gap-2"><x-icon name="map-pin" class="h-4 w-4"/>{{ $nextService->location->name }}</span>@endif
            </div>
            <div class="mt-5 flex flex-wrap gap-2 text-sm">
                <span class="rounded-xl bg-white/10 px-3 py-2 ring-1 ring-inset ring-white/15">🎵 {{ $nextItems->count() + ($nextRepertoire?->openingSong ? 1 : 0) }} louvor(es) definido(s)</span>
                @if($nextService->type?->name)<span class="rounded-xl bg-white/10 px-3 py-2 ring-1 ring-inset ring-white/15">{{ $nextService->type->name }}</span>@endif
            </div>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
            @if($nextItems->isNotEmpty() || $nextRepertoire?->openingSong)
                <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow-sm transition hover:bg-indigo-50" href="{{ route('my.agenda.repertoire', $nextService) }}"><x-icon name="music" class="h-4 w-4"/>Ver repertório</a>
            @endif
            @if($canViewRepertoire)<a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-950/20 px-4 py-2.5 text-sm font-black text-white ring-1 ring-inset ring-white/20 transition hover:bg-indigo-950/30" href="{{ route('repertoire.show', $nextService) }}">{{ $nextRepertoire ? 'Editar repertório' : 'Montar repertório' }}</a>@endif
            @if($canSwap && !isset($activeSwapsByAssignment[$nextAssignment->id]))<a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-indigo-50 transition hover:bg-white/10" href="{{ route('my.agenda.swap-options', $nextAssignment) }}"><x-icon name="repeat" class="h-4 w-4"/>Solicitar troca</a>@endif
        </div>
    </div>
</section>
@else
<section class="sgml-card mb-6 p-6 sm:p-8">
    <div class="flex flex-col items-center justify-center py-6 text-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600"><x-icon name="calendar" class="h-7 w-7"/></div>
        <h2 class="text-lg font-black">Nenhum culto publicado nos próximos 90 dias</h2>
        <p class="mt-1 max-w-lg text-sm text-slate-500">Quando uma nova escala for publicada para você, o próximo compromisso aparecerá aqui automaticamente.</p>
    </div>
</section>
@endif

@if($attentionCount > 0)
<section class="mb-7">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div><h2 class="sgml-section-title">Precisa da sua atenção</h2><p class="sgml-section-description">Resolva primeiro o que depende de uma resposta sua.</p></div>
        <span class="sgml-badge-danger"><x-icon name="alert" class="h-3.5 w-3.5"/>{{ $attentionCount }} pendência(s)</span>
    </div>
    <div class="space-y-3">
        @foreach($incomingSwaps as $swap)
            <article class="sgml-card border-amber-200 p-5">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><x-icon name="repeat"/></div>
                        <div>
                            <div class="font-black text-slate-900">Troca aguardando sua resposta</div>
                            <div class="mt-1 text-sm text-slate-600"><strong>{{ $swap->requester?->display_name ?? 'Ministro' }}</strong> pediu que você assuma <strong>{{ $swap->assignment?->service?->name ?? 'este culto' }}</strong>.</div>
                            @if($swap->assignment?->service?->starts_at)<div class="mt-1 text-xs font-semibold text-slate-500">{{ $swap->assignment->service->starts_at->format('d/m/Y H:i') }}</div>@endif
                            @if($swap->reason)<div class="mt-2 text-sm text-slate-500">Motivo: {{ $swap->reason }}</div>@endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('swaps.accept', $swap) }}" data-confirm-title="Assumir este culto?" data-confirm-message="Ao aceitar, a troca será aplicada e este culto passará para sua agenda." data-confirm-label="Aceitar e assumir">@csrf<button class="sgml-btn-primary">Aceitar e assumir</button></form>
                        <form method="POST" action="{{ route('swaps.reject', $swap) }}">@csrf<button class="sgml-btn-secondary">Não posso</button></form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

<section class="mb-7">
    <div class="mb-3"><h2 class="sgml-section-title">Atalhos</h2><p class="sgml-section-description">Acesse rapidamente as tarefas mais comuns.</p></div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @if($canManageAvailability)<a class="sgml-quick-action" href="{{ route('availability.index') }}"><span class="sgml-quick-action-icon"><x-icon name="calendar"/></span><span><strong class="block text-sm">Disponibilidade</strong><span class="text-xs text-slate-500">Dias e exceções</span></span></a>@endif
        @if($currentUser?->hasPermission('rehearsals.view'))<a class="sgml-quick-action" href="{{ route('rehearsals.index') }}"><span class="sgml-quick-action-icon"><x-icon name="mic"/></span><span><strong class="block text-sm">Ensaios</strong><span class="text-xs text-slate-500">Próximos encontros</span></span></a>@endif
        @if($canSwap)<a class="sgml-quick-action" href="{{ route('swaps.index') }}"><span class="sgml-quick-action-icon"><x-icon name="repeat"/></span><span><strong class="block text-sm">Trocas</strong><span class="text-xs text-slate-500">{{ $incomingSwaps->count() ? $incomingSwaps->count().' aguardando' : 'Solicitações e histórico' }}</span></span></a>@endif
        <a class="sgml-quick-action" href="{{ route('programmed-songs.index') }}"><span class="sgml-quick-action-icon"><x-icon name="music"/></span><span><strong class="block text-sm">Louvores programados</strong><span class="text-xs text-slate-500">Repertórios publicados</span></span></a>
        @if($currentUser?->canSeeSpecialEvents())<a class="sgml-quick-action" href="{{ route('special-events.index') }}"><span class="sgml-quick-action-icon"><x-icon name="sparkles"/></span><span><strong class="block text-sm">Eventos especiais</strong><span class="text-xs text-slate-500">Programações extras</span></span></a>@endif
        <a class="sgml-quick-action" href="{{ route('account.show') }}"><span class="sgml-quick-action-icon"><x-icon name="user"/></span><span><strong class="block text-sm">Minha conta</strong><span class="text-xs text-slate-500">Telefone e dados pessoais</span></span></a>
    </div>
</section>

<section class="mb-8">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div><h2 class="sgml-section-title">Próximos compromissos</h2><p class="sgml-section-description">Sua escala pessoal em ordem cronológica.</p></div>
        @if($assignments->count() > 1)<span class="sgml-badge-muted">{{ $assignments->count() }} cultos</span>@endif
    </div>
    <div class="sgml-timeline space-y-4">
        @forelse($assignments as $assignment)
            @php
                $activeSwap = $activeSwapsByAssignment[$assignment->id] ?? null;
                $repertoire = $assignment->service->repertoire;
                $items = $repertoire?->items ?? collect();
            @endphp
            <article class="sgml-timeline-item">
                <div class="sgml-date-tile relative z-10 hidden sm:flex"><span>{{ $weekdayShort[$assignment->service->starts_at->dayOfWeek] ?? '' }}</span><strong>{{ $assignment->service->starts_at->format('d') }}</strong></div>
                <div class="sgml-timeline-dot sm:hidden"></div>
                <div class="sgml-card-interactive min-w-0 flex-1 p-5">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><h3 class="text-base font-black">{{ $assignment->service->name }}</h3>@if($activeSwap)<span class="sgml-badge-warning">Troca solicitada</span>@else<span class="sgml-badge-success">Escala publicada</span>@endif</div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="h-4 w-4"/>{{ $assignment->service->starts_at->format('d/m/Y H:i') }}@if($assignment->service->ends_at) - {{ $assignment->service->ends_at->format('H:i') }}@endif</span>
                                @if($assignment->service->location?->name)<span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="h-4 w-4"/>{{ $assignment->service->location->name }}</span>@endif
                            </div>
                            @if($assignment->service->description)<p class="mt-2 text-sm text-slate-600">{{ $assignment->service->description }}</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($items->isNotEmpty() || $repertoire?->openingSong)<a class="sgml-btn-secondary" href="{{ route('my.agenda.repertoire', $assignment->service) }}"><x-icon name="music" class="h-4 w-4"/>Ver louvores</a>@endif
                            @if($canViewRepertoire)<a class="sgml-btn-tertiary" href="{{ route('repertoire.show', $assignment->service) }}">{{ $repertoire ? 'Editar repertório' : 'Montar repertório' }}</a>@endif
                        </div>
                    </div>

                    @if($repertoire?->openingSong || $items->isNotEmpty())
                        <div class="mt-4 rounded-2xl bg-slate-50 p-4">
                            <div class="mb-2 flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-wider text-slate-500">Repertório</div><span class="text-xs font-bold text-slate-400">{{ $items->count() + ($repertoire?->openingSong ? 1 : 0) }} item(ns)</span></div>
                            <div class="space-y-2 text-sm text-slate-700">
                                @if($repertoire?->openingSong)<div class="flex flex-wrap items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-violet-100 text-xs font-black text-violet-700">A</span><span class="font-semibold">{{ $repertoire->openingSong->title }}</span>@if($repertoire->openingMinister)<span class="sgml-badge bg-violet-50 text-violet-700">{{ $repertoire->openingMinister->display_name }}</span>@endif @if($repertoire->opening_key)<span class="sgml-badge-info">Tom {{ $repertoire->opening_key }}</span>@endif</div>@endif
                                @foreach($items as $item)<div class="flex flex-wrap items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-xs font-black text-slate-500 ring-1 ring-inset ring-slate-200">{{ $loop->iteration }}</span><span class="font-semibold">{{ $item->song?->title ?? 'Louvor' }}</span>@if($item->minister)<span class="sgml-badge bg-violet-50 text-violet-700">{{ $item->minister->display_name }}</span>@endif @if($item->planned_key)<span class="sgml-badge-info">Tom {{ $item->planned_key }}</span>@endif</div>@endforeach
                            </div>
                        </div>
                    @endif

                    @if($canSwap)
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            @if($activeSwap)<div class="text-sm text-amber-800"><strong>Troca em andamento:</strong> aguardando {{ $activeSwap->targetPerson?->display_name ?? 'outro ministro' }}.</div>@else<div class="text-xs text-slate-500">Se houver um imprevisto, solicite a troca somente para este compromisso.</div><a class="text-sm font-bold text-slate-600 hover:text-indigo-700" href="{{ route('my.agenda.swap-options', $assignment) }}">Não posso ministrar →</a>@endif
                        </div>
                    @endif
                </div>
            </article>
        @empty
        @endforelse
    </div>
</section>

@if($additionalCommitments->isNotEmpty())
<section class="mb-8">
    <div class="mb-3"><h2 class="sgml-section-title">Participações adicionais</h2><p class="sgml-section-description">Cultos em que você participa como ministro adicional.</p></div>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach($additionalCommitments as $commitment)
            <a class="sgml-card-interactive flex items-center gap-4 p-4" href="{{ route('my.agenda.repertoire', $commitment->service) }}">
                <div class="sgml-date-tile"><span>{{ $weekdayShort[$commitment->service->starts_at->dayOfWeek] ?? '' }}</span><strong>{{ $commitment->service->starts_at->format('d') }}</strong></div>
                <div class="min-w-0"><div class="truncate font-black">{{ $commitment->service->name }}</div><div class="mt-1 text-sm text-slate-500">{{ $commitment->service->starts_at->format('d/m/Y H:i') }}@if($commitment->service->location?->name) · {{ $commitment->service->location->name }}@endif</div></div>
            </a>
        @endforeach
    </div>
</section>
@endif

@if($assistedPerson)
<div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Durante a assistência, os links de assinatura permanecem vinculados ao usuário real. Encerre a assistência para gerenciar a assinatura pessoal do seu próprio calendário.</div>
@endif

@if($outgoingSwaps->isNotEmpty())
<details class="sgml-card mb-8 overflow-hidden">
    <summary class="cursor-pointer list-none px-5 py-4 font-black text-slate-800">Histórico recente de trocas <span class="ml-2 text-xs font-semibold text-slate-400">{{ $outgoingSwaps->count() }} registro(s)</span></summary>
    <div class="divide-y divide-slate-100 border-t border-slate-100">
        @foreach($outgoingSwaps as $swap)
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div><strong>{{ $swap->assignment?->service?->name ?? 'Culto' }}</strong><div class="text-sm text-slate-500">{{ $swap->targetPerson?->display_name ?? 'Ministro' }}@if($swap->assignment?->service?->starts_at) · {{ $swap->assignment->service->starts_at->format('d/m/Y H:i') }}@endif</div></div><span class="sgml-badge-muted">{{ $swapStatusNames[$swap->status] ?? strtoupper($swap->status) }}</span></div>
        @endforeach
    </div>
</details>
@endif
@endsection
