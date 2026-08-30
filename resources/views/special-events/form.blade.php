@extends('layouts.app')
@section('content')
@php
    $editing = isset($specialEvent) && $specialEvent->exists;
    $service = $editing ? $specialEvent->service : null;
@endphp
<div class="mb-6">
    <a class="text-sm font-semibold text-indigo-600" href="{{ $editing ? route('special-events.show',$specialEvent) : route('special-events.index') }}">← {{ $editing ? 'Voltar ao evento' : 'Eventos especiais' }}</a>
    <h1 class="mt-2 text-2xl font-black">{{ $editing ? 'Editar evento especial' : 'Novo evento especial' }}</h1>
    <p class="text-sm text-slate-500">
        {{ $editing ? 'As alterações atualizam o calendário e geram um aviso para a equipe revisar os novos dados.' : 'Ao criar, a equipe do louvor recebe um aviso para confirmar se poderá participar.' }}
    </p>
</div>
<form method="POST" action="{{ $editing ? route('special-events.update',$specialEvent) : route('special-events.store') }}" class="sgml-card grid gap-4 p-6 md:grid-cols-2">
    @csrf
    @if($editing) @method('PATCH') @endif
    <div class="md:col-span-2"><label class="sgml-label">Nome do evento</label><input class="sgml-input" name="name" value="{{ old('name', $editing ? $specialEvent->name : '') }}" required></div>
    <div><label class="sgml-label">Início</label><input class="sgml-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $editing ? $specialEvent->starts_at?->format('Y-m-d\\TH:i') : '') }}" required></div>
    <div><label class="sgml-label">Término</label><input class="sgml-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $editing ? $specialEvent->ends_at?->format('Y-m-d\\TH:i') : '') }}"></div>
    <div><label class="sgml-label">Congregação</label><select class="sgml-input" name="congregation_id"><option value="">Não definida</option>@foreach($congregations as $item)<option value="{{ $item->id }}" @selected(old('congregation_id', $service?->congregation_id)===$item->id)>{{ $item->name }}</option>@endforeach</select></div>
    <div><label class="sgml-label">Local</label><select class="sgml-input" name="location_id"><option value="">Não definido</option>@foreach($locations as $item)<option value="{{ $item->id }}" @selected(old('location_id', $service?->location_id)===$item->id)>{{ $item->name }}</option>@endforeach</select></div>
    <div class="md:col-span-2"><label class="sgml-label">Resumo / informações adicionais</label><textarea class="sgml-input" rows="5" name="description" placeholder="Ex.: aniversário da igreja, horário de chegada, traje, orientações...">{{ old('description', $editing ? $specialEvent->description : '') }}</textarea></div>
    <div class="md:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
        Este evento é voltado à equipe do ministério e <strong>não é exibido para o perfil Membro</strong>. Participantes do Louvor, Ministros, Liderança e Administração poderão confirmar se participam.
    </div>
    <div class="md:col-span-2"><button class="sgml-btn-primary">{{ $editing ? 'Salvar alterações e avisar equipe' : 'Criar evento e avisar equipe' }}</button></div>
</form>
@endsection
