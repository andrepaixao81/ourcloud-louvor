@extends('layouts.app')
@section('content')
@php
    $editing = $service->exists;
    $monthBack = $service->starts_at?->format('Y-m') ?? substr($selectedDate, 0, 7);
    $specificTitle = old('name', $customTitle ?? ($editing ? '' : null));
@endphp

<div class="sgml-page-header">
    <div>
        <a href="{{ route('services.index', ['month' => $monthBack]) }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600"><x-icon name="chevron-left" class="h-4 w-4"/>Calendário de cultos</a>
        <div class="sgml-page-kicker mt-3">Planejamento</div>
        <h1 class="sgml-page-title">{{ $editing ? 'Editar culto' : 'Adicionar culto' }}</h1>
        <p class="sgml-page-description">Preencha primeiro as informações essenciais. Opções menos frequentes ficam separadas para a tela continuar simples.</p>
    </div>
</div>

@if($errors->any())
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <div class="flex items-center gap-2 font-black"><x-icon name="alert" class="h-5 w-5"/>Não foi possível salvar</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if($editing)
    <div class="mb-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
        <div class="flex gap-3"><x-icon name="info" class="mt-0.5 h-5 w-5 shrink-0"/><div><strong>Alteração somente desta ocorrência.</strong> O que você salvar será aplicado ao culto de <strong>{{ $service->starts_at->format('d/m/Y') }}</strong>.
        @if($isRecurringOccurrence ?? false) A regra que gera os demais cultos continuará igual e esta data ficará registrada como exceção. @else Os outros cultos já cadastrados não serão modificados. @endif</div></div>
    </div>
@endif

<form method="POST" action="{{ $editing ? route('services.update', $service) : route('services.store') }}" class="space-y-5">
    @csrf
    @if($editing) @method('PATCH') @endif

    <section class="sgml-card p-5 sm:p-6">
        <div class="mb-5"><h2 class="sgml-section-title">Informações básicas</h2><p class="sgml-section-description">Tipo, data e horário são os dados necessários para identificar o compromisso.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="sgml-label">Tipo de culto/evento</label>
                <select class="sgml-input" name="service_type_id" id="service-type-select" required>
                    <option value="">Selecione</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" data-type-name="{{ $type->name }}" @selected(old('service_type_id', $service->service_type_id) === $type->id)>{{ $type->name }}{{ $type->deleted_at ? ' (inativo)' : '' }}</option>
                    @endforeach
                </select>
                <p class="sgml-help">Ao mudar o tipo e deixar o título específico vazio, o nome padrão também será atualizado.</p>
            </div>
            <div><label class="sgml-label">Data</label><input class="sgml-input" type="date" name="service_date" value="{{ old('service_date', $service->starts_at?->format('Y-m-d') ?? $selectedDate) }}" required></div>
            <div class="grid grid-cols-2 gap-2"><div><label class="sgml-label">Início</label><input class="sgml-input" type="time" name="starts_time" value="{{ old('starts_time', $service->starts_at?->format('H:i')) }}" required></div><div><label class="sgml-label">Término</label><input class="sgml-input" type="time" name="ends_time" value="{{ old('ends_time', $service->ends_at?->format('H:i')) }}"></div></div>
            <div><label class="sgml-label">Congregação</label><select class="sgml-input" name="congregation_id"><option value="">Não definida</option>@foreach($congregations as $item)<option value="{{ $item->id }}" @selected(old('congregation_id', $service->congregation_id) === $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="sgml-label">Local</label><select class="sgml-input" name="location_id"><option value="">Não definido</option>@foreach($locations as $item)<option value="{{ $item->id }}" @selected(old('location_id', $service->location_id) === $item->id)>{{ $item->name }}</option>@endforeach</select></div>
        </div>
    </section>

    <section class="sgml-card p-5 sm:p-6">
        <div class="mb-5"><h2 class="sgml-section-title">Ministério e escala</h2><p class="sgml-section-description">Defina a quantidade necessária. A escolha manual fica disponível somente quando fizer sentido operacional.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="sgml-label">Quantidade de ministros</label>
                <select class="sgml-input" name="minister_slots">@for($slot = 1; $slot <= 12; $slot++)<option value="{{ $slot }}" @selected((int) old('minister_slots', $service->minister_slots ?: 1) === $slot)>{{ $slot }} {{ $slot === 1 ? 'ministro' : 'ministros' }}</option>@endfor</select>
                <p class="sgml-help">Use mais de um para cultos especiais. A equipe poderá colaborar no repertório após a publicação da escala.</p>
            </div>
            @if($editing)
                <div><label class="sgml-label">Status</label><select class="sgml-input" name="status">@foreach(['draft'=>'Rascunho','scheduled'=>'Programado','published'=>'Publicado','confirmed'=>'Confirmado','in_progress'=>'Em andamento','completed'=>'Realizado','cancelled'=>'Cancelado'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $service->status) === $value)>{{ $label }}</option>@endforeach</select></div>
            @endif
            @if($editing && auth()->user()->hasPermission('schedule.manual_assign'))
                <div class="md:col-span-2 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
                    <label class="sgml-label">Ministro definido manualmente <span class="font-normal text-slate-400">(opcional)</span></label>
                    <select class="sgml-input" name="manual_minister_person_id"><option value="">Deixar para a geração/revisão da escala</option>@foreach($manualMinisters ?? collect() as $minister)<option value="{{ $minister->id }}" @selected(old('manual_minister_person_id', $selectedManualMinisterId ?? '') === $minister->id)>{{ $minister->display_name }}</option>@endforeach</select>
                    <p class="sgml-help">Se a escala já estiver publicada, faça a troca em <strong>Escalas</strong> para preservar histórico e notificações.</p>
                </div>
            @endif
        </div>
    </section>

    <section class="sgml-card p-5 sm:p-6">
        <div class="mb-5"><h2 class="sgml-section-title">Detalhes complementares</h2><p class="sgml-section-description">Use apenas quando o culto precisar de um nome ou orientação diferente do padrão.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="sgml-label">Título específico <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" name="name" value="{{ $specificTitle }}" placeholder="Deixe em branco para usar automaticamente o nome do tipo">@if($editing && blank($specificTitle))<p class="sgml-help text-emerald-700">Este culto está usando o nome padrão do tipo.</p>@endif</div>
            <div><label class="sgml-label">Descrição <span class="font-normal text-slate-400">(opcional)</span></label><textarea class="sgml-input" rows="3" name="description">{{ old('description', $service->description) }}</textarea></div>
            <div><label class="sgml-label">Observações <span class="font-normal text-slate-400">(opcional)</span></label><textarea class="sgml-input" rows="3" name="notes">{{ old('notes', $service->notes) }}</textarea></div>
        </div>
    </section>

    <div class="sticky bottom-[4.6rem] z-20 -mx-2 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg shadow-slate-900/5 backdrop-blur lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
        <button class="sgml-btn-primary">{{ $editing ? 'Salvar este culto' : 'Adicionar à agenda' }}</button>
        <a class="sgml-btn-secondary" href="{{ $editing ? route('services.show', $service) : route('services.index', ['month' => $monthBack]) }}">Cancelar</a>
    </div>
</form>
@endsection
