@extends('layouts.app')
@section('content')
@php
    $service = $specialEvent->service;
    $yesResponses = $specialEvent->responses->where('response','yes')->sortBy(fn($r)=>$r->person?->display_name);
    $noResponses = $specialEvent->responses->where('response','no')->sortBy(fn($r)=>$r->person?->display_name);
    $canManage = auth()->user()->hasPermission('special_events.manage');
@endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a class="text-sm font-semibold text-indigo-600" href="{{ route('special-events.index',['month'=>$specialEvent->starts_at->format('Y-m')]) }}">← Eventos especiais</a>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-black">{{ $specialEvent->name }}</h1>
            @if($specialEvent->status==='cancelled')<span class="sgml-badge bg-rose-100 text-rose-700">CANCELADO</span>@endif
        </div>
        <p class="text-sm text-slate-500">{{ ucfirst($specialEvent->starts_at->locale('pt_BR')->translatedFormat('l')) }}, {{ $specialEvent->starts_at->format('d/m/Y H:i') }}@if($specialEvent->ends_at) - {{ $specialEvent->ends_at->format('H:i') }}@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if($service && auth()->user()->hasPermission('repertoire.view') && $specialEvent->status!=='cancelled')
            <a href="{{ route('repertoire.show',$service) }}" class="sgml-btn-primary">Abrir repertório</a>
        @endif
        @if($canManage && $specialEvent->status!=='cancelled')
            <a href="{{ route('special-events.edit',$specialEvent) }}" class="sgml-btn-secondary">Editar evento</a>
            <a href="#cancelar-evento" class="sgml-btn-secondary">Cancelar evento</a>
        @endif
        @if($canManage)
            <a href="#excluir-evento" class="sgml-btn-danger">Excluir evento</a>
        @endif
    </div>
</div>

@if($specialEvent->status==='cancelled')
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        Este evento está cancelado e foi retirado da agenda/escala ativa. A liderança pode excluí-lo definitivamente da operação se não houver histórico efetivo.
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">
    <div class="sgml-card p-5 lg:col-span-2">
        <h2 class="text-lg font-black">Informações do evento</h2>
        <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $specialEvent->description ?: 'Sem informações adicionais.' }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
            <div><strong>Local:</strong> {{ $service?->location?->name ?: 'Não definido' }}</div>
            <div><strong>Ministro:</strong> {{ $service?->assignment?->minister?->display_name ?: 'Ainda não definido' }}</div>
        </div>
    </div>

    <div class="sgml-card p-5">
        <h2 class="font-black">Minha participação</h2>
        @if($person && auth()->user()->hasPermission('special_events.respond') && $specialEvent->status!=='cancelled')
            <form method="POST" action="{{ route('special-events.respond',$specialEvent) }}" class="mt-3 space-y-3">
                @csrf
                <label class="flex items-center gap-2"><input type="radio" name="response" value="yes" @checked($myResponse?->response==='yes') required> Posso participar</label>
                <label class="flex items-center gap-2"><input type="radio" name="response" value="no" @checked($myResponse?->response==='no') required> Não poderei participar</label>
                <div><label class="sgml-label">Observação <span class="font-normal text-slate-400">(opcional)</span></label><textarea class="sgml-input" name="note" rows="2">{{ $myResponse?->note }}</textarea></div>
                <button class="sgml-btn-primary w-full">Salvar confirmação</button>
            </form>
        @elseif($specialEvent->status==='cancelled')
            <p class="mt-2 text-sm text-rose-600">Evento cancelado. Não há confirmação pendente.</p>
        @else
            <p class="mt-2 text-sm text-slate-500">Sua conta não possui confirmação de participação neste evento.</p>
        @endif
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="sgml-card p-5">
        <h2 class="font-black">Confirmaram participação <span class="text-emerald-700">({{ $yesResponses->count() }})</span></h2>
        <div class="mt-3 space-y-2">@forelse($yesResponses as $response)<div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm"><strong>{{ $response->person?->display_name }}</strong>@if($response->note)<div class="text-xs text-slate-600">{{ $response->note }}</div>@endif</div>@empty<p class="text-sm text-slate-500">Ainda ninguém confirmou.</p>@endforelse</div>
    </div>
    <div class="sgml-card p-5">
        <h2 class="font-black">Não poderão participar <span class="text-rose-700">({{ $noResponses->count() }})</span></h2>
        <div class="mt-3 space-y-2">@forelse($noResponses as $response)<div class="rounded-lg bg-rose-50 px-3 py-2 text-sm"><strong>{{ $response->person?->display_name }}</strong>@if($response->note)<div class="text-xs text-slate-600">{{ $response->note }}</div>@endif</div>@empty<p class="text-sm text-slate-500">Nenhuma indisponibilidade informada.</p>@endforelse</div>
    </div>
</div>

@if($canManage && $specialEvent->status!=='cancelled')
    <div class="sgml-card mt-4 p-5">
        <h2 class="text-lg font-black">Definir ministro do evento</h2>
        <p class="text-sm text-slate-500">Use as confirmações acima como apoio. Ao definir o ministro, o evento passa a integrar a escala publicada.</p>
        <form method="POST" action="{{ route('special-events.assign-minister',$specialEvent) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
            @csrf
            <select class="sgml-input" name="minister_person_id" required>
                <option value="">Escolha o ministro</option>
                @foreach($ministers as $minister)
                    @php $response = $specialEvent->responses->firstWhere('person_id',$minister->id); @endphp
                    <option value="{{ $minister->id }}" @selected($service?->assignment?->minister_person_id===$minister->id)>
                        {{ $minister->display_name }}@if($response?->response==='yes') · confirmou participação @elseif($response?->response==='no') · informou que não poderá @else · sem resposta @endif
                    </option>
                @endforeach
            </select>
            <input class="sgml-input" name="reason" required placeholder="Motivo / observação da definição">
            <button class="sgml-btn-primary">Definir ministro</button>
        </form>
    </div>
@endif

@if($canManage)
    <div id="gerenciar-evento" class="sgml-card mt-4 p-5">
        <h2 class="text-lg font-black">Gerenciar evento</h2>
        <p class="mt-1 text-sm text-slate-500">Editar mantém o evento ativo. Cancelar preserva o registro, mas o retira da agenda e da escala. Excluir remove o evento da operação quando não existe histórico efetivo.</p>

        @if($specialEvent->status!=='cancelled')
            <div id="cancelar-evento" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h3 class="font-black text-amber-900">Cancelar evento</h3>
                <p class="mt-1 text-sm text-amber-800">O evento continuará registrado como cancelado, mas sairá da agenda e da escala ativa. A equipe será avisada.</p>
                <form method="POST" action="{{ route('special-events.cancel',$specialEvent) }}" class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]" data-confirm-message="Cancelar este evento especial e retirá-lo da agenda?">
                    @csrf
                    <input class="sgml-input" name="reason" required minlength="3" placeholder="Motivo do cancelamento">
                    <button class="sgml-btn-secondary">Cancelar evento</button>
                </form>
            </div>
        @endif

        <div id="excluir-evento" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
            <h3 class="font-black text-rose-900">Excluir evento</h3>
            <p class="mt-1 text-sm text-rose-800">Remove o evento da lista, agenda, escala, confirmações e planejamento associado. A auditoria permanece. Se houver histórico efetivo de execução, o SGML bloqueará a exclusão.</p>
            @error('delete')<div class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</div>@enderror
            <form method="POST" action="{{ route('special-events.destroy',$specialEvent) }}" class="mt-3 grid gap-3 md:grid-cols-2" data-confirm-message="Excluir este evento especial? Esta ação removerá agenda, escala e confirmações associadas.">
                @csrf
                @method('DELETE')
                <input class="sgml-input md:col-span-2" name="reason" required minlength="3" placeholder="Motivo da exclusão">
                <div>
                    <label class="sgml-label">Digite EXCLUIR para confirmar</label>
                    <input class="sgml-input" name="confirmation" required autocomplete="off" placeholder="EXCLUIR">
                    @error('confirmation','deleteSpecialEvent')<div class="mt-1 text-sm text-rose-700">{{ $message }}</div>@enderror
                </div>
                <div class="flex items-end"><button class="sgml-btn-danger w-full">Excluir evento definitivamente</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
