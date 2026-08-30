@extends('layouts.app')
@section('content')
@php
    $currentUser = auth()->user();
    $canGenerateSchedule = $currentUser ? $currentUser->hasPermission('schedule.generate') : false;
    $canPublishSchedule = $currentUser ? $currentUser->hasPermission('schedule.publish') : false;
    $canResetSchedule = $currentUser ? $currentUser->hasPermission('schedule.reset') : false;
    $canManualAssign = $currentUser ? $currentUser->hasPermission('schedule.manual_assign') : false;
    $canUpdateSchedule = $currentUser ? $currentUser->hasPermission('schedule.update') : false;
    $canManageAdditionalMinisters = $currentUser ? $currentUser->hasPermission('schedule.additional_ministers') : false;
@endphp

<div class="sgml-page-header">
    <div>
        <div class="sgml-page-kicker">Ministério</div>
        <h1 class="sgml-page-title">Escala ministerial</h1>
        <p class="sgml-page-description">{{ $canManageSchedule ? 'Gere, revise e publique a escala com as pendências visíveis antes da decisão.' : 'Veja quem ministra em cada culto e acesse os repertórios publicados.' }}</p>
    </div>
    <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <a class="sgml-btn-tertiary !px-2" href="{{ route('schedule.index', array_filter(['month' => $month->copy()->subMonth()->format('Y-m'), 'view' => $canManageSchedule ? null : $readonlyView])) }}" aria-label="Mês anterior"><x-icon name="chevron-left" class="h-4 w-4"/></a>
        <x-month-year-picker :month="$month" route-name="schedule.index" :query="$canManageSchedule ? [] : ['view' => $readonlyView]" id="schedule-month-picker" />
        <a class="sgml-btn-tertiary !px-2" href="{{ route('schedule.index', array_filter(['month' => $month->copy()->addMonth()->format('Y-m'), 'view' => $canManageSchedule ? null : $readonlyView])) }}" aria-label="Próximo mês"><x-icon name="chevron-right" class="h-4 w-4"/></a>
    </div>
</div>

@if(! $canManageSchedule)
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <span class="sgml-badge-muted"><x-icon name="info" class="h-3.5 w-3.5"/>Somente leitura</span>
        <a class="{{ $readonlyView === 'minister' ? 'sgml-btn-primary' : 'sgml-btn-secondary' }}" href="{{ route('schedule.index', ['month' => $month->format('Y-m'), 'view' => 'minister']) }}">Por ministro</a>
        <a class="{{ $readonlyView === 'chronological' ? 'sgml-btn-primary' : 'sgml-btn-secondary' }}" href="{{ route('schedule.index', ['month' => $month->format('Y-m'), 'view' => 'chronological']) }}">Lista cronológica</a>
        @if($assignments->isNotEmpty())
            <a class="sgml-btn-secondary" href="{{ route('schedule.pdf', ['month' => $month->format('Y-m')]) }}">Baixar PDF da escala</a>
        @endif
        <a class="sgml-btn-secondary" href="{{ route('my.agenda') }}">Minha Agenda</a>
    </div>

    @if($assignments->isEmpty())
        <div class="sgml-card p-10 text-center text-slate-500">Ainda não existe escala publicada para este mês.</div>
    @elseif($readonlyView === 'minister')
        <section class="sgml-card p-5 sm:p-6">
            <div class="mb-4">
                <h2 class="sgml-section-title">Escala por ministro</h2>
                <p class="text-sm text-slate-500">Visualização padrão da escala publicada. Cada ministro aparece com seus cultos do mês.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($ministerScheduleOverview as $ministerOverview)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <strong>{{ $ministerOverview['name'] }}</strong>
                            <span class="sgml-badge-info">{{ $ministerOverview['count'] }} culto(s)</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($ministerOverview['services'] as $overviewService)
                                <a class="block rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-indigo-50" href="{{ route('services.show', ['service' => $overviewService['service_id'], 'return_to' => request()->getRequestUri()]) }}">
                                    <div class="font-semibold">{{ $overviewService['starts_at']->format('d/m H:i') }} · {{ $overviewService['name'] }}</div>
                                    @if($overviewService['type'])
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            {{ $overviewService['type'] }}
                                            @if($overviewService['additional'])
                                                · participação adicional
                                            @endif
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <div class="space-y-3">
            @foreach($assignments->sortBy(fn ($item) => $item->service?->starts_at) as $assignment)
                @php
                    $service = $assignment->service;
                    $repertoire = $service?->repertoire;
                    $items = $repertoire?->items ?? collect();
                    $allMinisters = collect([$assignment->minister?->display_name])
                        ->filter()
                        ->merge($service?->additionalMinisters?->pluck('minister.display_name')->filter() ?? collect());
                @endphp
                <article class="sgml-card p-5">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                        <div>
                            <div class="font-bold">{{ $service?->name ?? 'Culto' }}</div>
                            <div class="text-sm text-slate-500">
                                {{ $service?->starts_at?->format('d/m/Y H:i') }}
                                @if($service?->ends_at)
                                    - {{ $service->ends_at->format('H:i') }}
                                @endif
                                @if($service?->location?->name)
                                    · {{ $service->location->name }}
                                @endif
                            </div>
                            <div class="mt-2 text-sm"><strong>Ministro(s):</strong> {{ $allMinisters->implode(', ') ?: 'Ainda não definido' }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($service)
                                <a class="sgml-btn-secondary" href="{{ route('services.show', ['service' => $service, 'return_to' => request()->getRequestUri()]) }}">Detalhes</a>
                            @endif
                            @if($service && ($repertoire?->openingSong || $items->isNotEmpty()))
                                <a class="sgml-btn-secondary" href="{{ route('my.agenda.repertoire', $service) }}">Ver louvores</a>
                            @endif
                        </div>
                    </div>
                    @if($repertoire?->openingSong || $items->isNotEmpty())
                        <div class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-700">
                            @if($repertoire?->openingSong)
                                <div>
                                    <strong>Abertura:</strong> {{ $repertoire->openingSong->title }} @if($repertoire->openingMinister) · {{ $repertoire->openingMinister->display_name }} @endif
                                    @if($repertoire->opening_key)
                                        · Tom {{ $repertoire->opening_key }}
                                    @endif
                                </div>
                            @endif
                            @foreach($items as $item)
                                <div class="mt-1">
                                    <strong>Louvor {{ $loop->iteration }}:</strong> {{ $item->song?->title ?? 'Louvor removido da biblioteca' }} @if($item->minister) · {{ $item->minister->display_name }} @endif
                                    @if($item->planned_key)
                                        · Tom {{ $item->planned_key }}
                                    @endif
                                </div>
                            @endforeach
                            @if($repertoire?->final_notes)
                                <div class="mt-2 text-slate-500"><strong>Observações:</strong> {{ $repertoire->final_notes }}</div>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

@else

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="sgml-stat-card"><div class="sgml-stat-label">Cultos no período</div><div class="sgml-stat-value">{{ $validation['services'] }}</div><div class="mt-2 text-xs text-slate-400">{{ $month->translatedFormat('F/Y') }}</div></div>
    <div class="sgml-stat-card">
        <div class="sgml-stat-label">Status da escala</div>
        <div class="mt-2 text-lg font-black {{ $hasPublished ? ($validation['valid'] ? 'text-emerald-600' : 'text-amber-600') : ($validation['valid'] ? 'text-emerald-600' : 'text-rose-600') }}">
            {{ $hasPublished ? ($validation['valid'] ? '✓ Escala publicada' : '⚠ Publicada · revisar') : ($validation['valid'] ? '✓ Pronta para publicar' : '⚠ Há pendências') }}
        </div>
        <div class="mt-2 text-xs text-slate-500">{{ count($validation['errors'] ?? []) }} pendência(s) · {{ count($validation['warnings'] ?? []) }} aviso(s)</div>
    </div>
    <div class="sgml-stat-card">
        <div class="sgml-stat-label">Distribuição por ministro</div>
        <div class="mt-3 text-xs leading-relaxed text-slate-600">
            @if(count($validation['distribution']) === 0)
                Sem atribuições
            @else
                @foreach($validation['distribution'] as $distributionItem)
                    <span class="mr-2 inline-block">{{ $distributionItem['name'] }}: <strong>{{ $distributionItem['count'] }}</strong></span>
                @endforeach
            @endif
        </div>
    </div>
</div>

@if(!empty($validation['errors']))
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="font-bold text-rose-800">Pendências</div>
        <ul class="mt-2 list-disc pl-5 text-sm text-rose-700">
            @foreach($validation['errors'] as $validationError)
                <li>{{ $validationError }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(count($validation['warnings'] ?? []) > 0)
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="font-bold text-amber-800">Advertências aceitas</div>
        <ul class="mt-2 list-disc pl-5 text-sm text-amber-700">
            @foreach($validation['warnings'] as $validationWarning)
                <li>{{ $validationWarning }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-amber-700">Mais de um culto no mesmo dia é permitido quando a liderança escolhe deliberadamente o ministro e não há conflito de horário.</p>
    </div>
@endif

@if($errors->has('manual_minister') || $errors->has('reset'))
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {{ $errors->first('manual_minister') ?: $errors->first('reset') }}
    </div>
@endif

@if($canResetSchedule && $assignments->isNotEmpty())
    <div class="mb-5 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800">
        <strong>Revogação administrativa:</strong> você pode arquivar a escala atual mesmo que existam cultos sem ministro ou cultos com execução já registrada. A execução real e a auditoria são preservadas; ao gerar outra escala, apenas cultos ainda escaláveis serão redistribuídos.
    </div>
@endif

<div class="mb-5 flex flex-wrap gap-3">
    @if($canGenerateSchedule)
        <form method="POST" action="{{ route('schedule.generate') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <button class="sgml-btn-secondary"><x-icon name="sync" class="h-4 w-4"/>Gerar / revalidar sugestão</button>
        </form>
    @endif

    @if($canPublishSchedule)
        <form method="POST" action="{{ route('schedule.publish') }}" class="flex flex-col gap-2" data-confirm-title="Publicar escala?" data-confirm-message="A escala deste mês ficará visível para o ministério e os avisos serão enviados conforme a opção selecionada." data-confirm-label="Publicar escala">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <button class="sgml-btn-primary"><x-icon name="check" class="h-4 w-4"/>Validar e publicar</button>
            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                <input type="checkbox" name="notify_whatsapp" value="1" checked class="rounded border-slate-300">
                Enviar aviso da escala pelo WhatsApp
            </label>
        </form>
    @endif

    @if($validation['valid'] && $assignments->isNotEmpty())
        <a class="sgml-btn-secondary" href="{{ route('schedule.pdf', ['month' => $month->format('Y-m')]) }}">Baixar PDF da escala</a>
    @endif

    @if($canResetSchedule && $assignments->isNotEmpty())
        <form method="POST" action="{{ route('schedule.reset') }}" data-confirm-title="{{ $hasPublished ? 'Revogar escala publicada?' : 'Descartar sugestão atual?' }}" data-confirm-message="Cultos já executados terão o histórico preservado. Os demais poderão receber uma nova escala." data-confirm-danger="1" data-confirm-label="{{ $hasPublished ? 'Revogar e gerar outra' : 'Descartar e gerar outra' }}" data-confirm-reason-label="{{ $hasPublished ? 'Motivo para arquivar a versão atual' : 'Motivo para descartar a sugestão' }}" data-confirm-reason-min="3">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <input type="hidden" name="reason">
            <button class="sgml-btn-danger">{{ $hasPublished ? 'Revogar escala e gerar outra' : 'Excluir sugestão e gerar outra' }}</button>
        </form>
    @endif
</div>

@if($ministerScheduleOverview->isNotEmpty())
    <section class="mb-6 sgml-card p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-black">Distribuição por ministro</h2>
                <p class="text-sm text-slate-500">Além da quantidade, confira exatamente os cultos atribuídos a cada ministro neste mês.</p>
            </div>
            @if($hasPublished)
                <span class="sgml-badge-success">Versão publicada</span>
            @else
                <span class="sgml-badge-info">Sugestão em revisão</span>
            @endif
        </div>

        @if($hasPublished)
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                Esta visão representa a escala já publicada. O novo rodízio equilibrado por tipo de culto é aplicado ao gerar uma nova sugestão; uma escala publicada não é redistribuída silenciosamente.
            </div>
        @endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($ministerScheduleOverview as $ministerOverview)
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <strong>{{ $ministerOverview['name'] }}</strong>
                        <span class="sgml-badge-info">{{ $ministerOverview['count'] }} culto(s)</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @foreach($ministerOverview['services'] as $overviewService)
                            <a href="{{ route('services.show', ['service' => $overviewService['service_id'], 'return_to' => request()->getRequestUri()]) }}" class="block rounded-lg bg-slate-50 px-3 py-2 text-xs hover:bg-indigo-50">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-semibold text-slate-800">{{ $overviewService['starts_at']->format('d/m H:i') }} · {{ $overviewService['name'] }}</span>
                                    @if($overviewService['additional'])
                                        <span class="sgml-badge bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200">adicional</span>
                                    @endif
                                </div>
                                @if($overviewService['type'] && $overviewService['type'] !== $overviewService['name'])
                                    <div class="mt-1 text-slate-500">{{ $overviewService['type'] }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

@if($rotationByType->isNotEmpty())
    <section class="mb-6 sgml-card p-5">
        <div class="mb-4">
            <h2 class="text-lg font-black">Rodízio por tipo de culto</h2>
            <p class="text-sm text-slate-500">Mostra todos os ministros habilitados para cada tipo recorrente e quantas vezes cada um foi escalado no mês. Diferenças podem ser legítimas quando houver indisponibilidade ou conflito de horário.</p>
        </div>
        <div class="space-y-4">
            @foreach($rotationByType as $rotation)
                <div class="rounded-xl border {{ $rotation['balance_status'] === 'review' ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200' }} p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <strong>{{ $rotation['type_name'] }}</strong>
                            <div class="text-xs text-slate-500">{{ $rotation['service_count'] }} culto(s) no mês · {{ $rotation['authorized_count'] }} ministro(s) habilitado(s)</div>
                        </div>
                        @if($rotation['balance_status'] === 'review')
                            <span class="sgml-badge bg-amber-100 text-amber-800">revisar equilíbrio</span>
                        @elseif($rotation['balance_status'] === 'constrained')
                            <span class="sgml-badge-muted">diferença justificada</span>
                        @else
                            <span class="sgml-badge-success">equilibrado</span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($rotation['counts'] as $rotationMinister)
                            <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">{{ $rotationMinister['name'] }}: <strong>{{ $rotationMinister['count'] }}</strong> <span class="text-slate-400">· {{ $rotationMinister['opportunities'] }} data(s) viável(is)</span></span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

@if($manualServices->isNotEmpty())
    <div class="mb-6">
        <h2 class="mb-3 text-lg font-black">Cultos com ministro definido manualmente</h2>
        <div class="space-y-3">
            @foreach($manualServices as $service)
                @php
                    $manualCandidates = $manualCandidatesByService[$service->id] ?? collect();
                    $manualCandidatesEmpty = $manualCandidates->isEmpty();
                @endphp
                <div class="sgml-card border border-indigo-100 p-5">
                    <div class="font-bold">{{ $service->name }}</div>
                    <div class="text-sm text-slate-500">{{ $service->starts_at->format('d/m/Y H:i') }} · {{ $service->type ? $service->type->name : '' }}</div>
                    <p class="mt-2 text-sm text-indigo-700">Este tipo não entra no rodízio automático. Escolha deliberadamente quem ministrará.</p>

                    @if($canManualAssign)
                        <form method="POST" action="{{ route('schedule.manual-assign', $service) }}" class="mt-4 grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                            @csrf
                            <select class="sgml-input" name="minister_person_id" required {{ $manualCandidatesEmpty ? 'disabled' : '' }}>
                                @if($manualCandidatesEmpty)
                                    <option value="">Nenhum ministro disponível/sem conflito</option>
                                @else
                                    @foreach($manualCandidates as $candidate)
                                        <option value="{{ $candidate['person']->id }}">{{ $candidate['person']->display_name }}{{ !empty($candidate['warnings']) ? ' ⚠ já escalado neste dia' : '' }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <input class="sgml-input" name="reason" required minlength="3" maxlength="1000" placeholder="Motivo/observação da escolha manual">
                            <button class="sgml-btn-primary" {{ $manualCandidatesEmpty ? 'disabled' : '' }}>Definir ministro</button>
                        </form>
                        <p class="mt-2 text-xs text-slate-500">⚠ Um ministro já escalado em outro culto do mesmo dia continua selecionável quando os horários não se sobrepõem. A escolha será tratada como exceção deliberada.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="space-y-3">
    @if($assignments->isEmpty())
        <div class="sgml-card p-10 text-center">
            <p class="text-slate-500">Ainda não há atribuições neste mês.</p>
        </div>
    @else
        @foreach($assignments as $assignment)
            @php
                $service = $assignment->service;
                $ministerSlots = (int) ($service->minister_slots ?? 1);
                $eligibleMinisters = $eligibleMinistersByAssignment[$assignment->id] ?? collect();
                $eligibleMinistersEmpty = $eligibleMinisters->isEmpty();
                $currentIsEligible = $eligibleMinisters->contains(function ($candidate) use ($assignment) {
                    return ($candidate['person']->id ?? null) === $assignment->minister_person_id;
                });
                $extraCandidates = $additionalCandidatesByService[$service->id] ?? collect();
                $selectedExtraIds = $service->additionalMinisters->pluck('person_id')->values()->all();
                $neededExtras = max(0, $ministerSlots - 1);
            @endphp

            <div class="sgml-card-interactive p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><div class="text-base font-black">{{ $service->name }}</div>@if($ministerSlots > 1)<span class="sgml-badge-info">{{ $ministerSlots }} ministros</span>@endif</div>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500"><span class="inline-flex items-center gap-1"><x-icon name="calendar" class="h-4 w-4"/>{{ $service->starts_at->format('d/m/Y') }}</span><span class="inline-flex items-center gap-1"><x-icon name="clock" class="h-4 w-4"/>{{ $service->starts_at->format('H:i') }}</span>@if($service->type)<span>{{ $service->type->name }}</span>@endif</div>

                        <div class="mt-3 flex items-center gap-2 text-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><x-icon name="mic" class="h-4 w-4"/></span>
                            <strong>{{ $assignment->minister ? $assignment->minister->display_name : 'Ministro não definido' }}</strong>
                            @if($assignment->assignment_mode === 'manual')
                                <span class="ml-2 sgml-badge bg-indigo-50 text-indigo-700">MANUAL</span>
                            @endif
                        </div>

                        @if($assignment->score)
                            <div class="mt-1 text-xs text-slate-500">Pontuação: {{ $assignment->score }} · {{ implode(' · ', $assignment->explanation ?? []) }}</div>
                        @endif

                        @if($assignment->manual_reason)
                            <div class="mt-1 text-xs text-slate-500">Motivo da escolha manual: {{ $assignment->manual_reason }}</div>
                        @endif
                    </div>

                    <div class="text-sm">@if($assignment->minister)<span class="{{ $assignment->status === 'published' ? 'sgml-badge-success' : 'sgml-badge-info' }}"><x-icon name="check" class="h-3.5 w-3.5"/>{{ $assignment->status === 'published' ? 'Publicada' : 'Em revisão' }}</span>@else<span class="sgml-badge-danger"><x-icon name="alert" class="h-3.5 w-3.5"/>Ministro pendente</span>@endif</div>
                </div>

                @if($canUpdateSchedule)
                    @if(!$currentIsEligible)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <strong>Atribuição incompatível com as regras atuais.</strong> Escolha abaixo outro ministro disponível.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('schedule.update', $assignment) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        <select class="sgml-input" name="minister_person_id" {{ $eligibleMinistersEmpty ? 'disabled' : '' }}>
                            @if($eligibleMinistersEmpty)
                                <option value="">Nenhum ministro disponível</option>
                            @else
                                @foreach($eligibleMinisters as $candidate)
                                    <option value="{{ $candidate['person']->id }}" {{ $candidate['person']->id === $assignment->minister_person_id ? 'selected' : '' }}>{{ $candidate['person']->display_name }}{{ !empty($candidate['warnings']) ? ' ⚠ já escalado neste dia' : '' }}</option>
                                @endforeach
                            @endif
                        </select>
                        <button class="sgml-btn-secondary whitespace-nowrap" {{ $eligibleMinistersEmpty ? 'disabled' : '' }}>Alterar ministro</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">⚠ A liderança pode manter o mesmo ministro em dois cultos do mesmo dia quando não houver sobreposição de horário. O sistema mostra a advertência, mas permite a escolha.</p>
                @endif

                @if($ministerSlots > 1)
                    <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                        <div class="font-bold text-indigo-900">Ministros adicionais</div>
                        <p class="mt-1 text-xs text-slate-600">Selecione {{ $neededExtras }} ministro(s) adicional(is). Depois da publicação, todos terão acesso para colaborar no repertório deste culto.</p>

                        @if($canManageAdditionalMinisters)
                            <form method="POST" action="{{ route('schedule.additional-ministers', $service) }}" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @csrf
                                @foreach(range(0, $neededExtras - 1) as $slotIndex)
                                    <select class="sgml-input" name="minister_person_ids[]" required>
                                        <option value="">Ministro adicional {{ $slotIndex + 2 }}</option>
                                        @foreach($extraCandidates as $extraCandidate)
                                            <option value="{{ $extraCandidate['person']->id }}" {{ (($selectedExtraIds[$slotIndex] ?? null) === $extraCandidate['person']->id) ? 'selected' : '' }}>{{ $extraCandidate['person']->display_name }}{{ !empty($extraCandidate['warnings']) ? ' ⚠ já escalado neste dia' : '' }}</option>
                                        @endforeach
                                    </select>
                                @endforeach
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <button class="sgml-btn-secondary w-full sm:w-auto">Salvar ministros adicionais</button>
                                </div>
                            </form>
                        @else
                            <div class="mt-3 text-sm text-slate-700">
                                @if($service->additionalMinisters->isEmpty())
                                    Nenhum ministro adicional definido.
                                @else
                                    @foreach($service->additionalMinisters as $additionalMinister)
                                        <span class="mr-2 inline-block rounded-full bg-white px-3 py-1">{{ $additionalMinister->minister ? $additionalMinister->minister->display_name : 'Ministro' }}</span>
                                    @endforeach
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
@endif
@endsection
