@extends('layouts.app')
@section('content')
@php($editing = $editing ?? $entry->exists)
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <a class="text-sm font-semibold text-indigo-600" href="{{ route('calendar.index', ['view' => 'month', 'date' => ($entry->starts_at ?? now())->format('Y-m-d')]) }}">← Agenda</a>
        <h1 class="mt-2 text-2xl font-black">{{ $editing ? 'Editar programação da agenda' : 'Nova programação na agenda' }}</h1>
        <p class="text-sm text-slate-500">Use para avisos, programações e compromissos da igreja. <strong>Este item não cria culto, escala, ministro nem repertório.</strong></p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('calendar.events.update', $entry) : route('calendar.events.store') }}" class="sgml-card grid gap-4 p-6 md:grid-cols-2">
    @csrf
    @if($editing) @method('PATCH') @endif
    <div class="md:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-800">
        <strong>Agenda informativa:</strong> esta programação aparece apenas na Agenda do Ministério e na assinatura da agenda do sistema. Ela é independente de Cultos e Escalas.
    </div>
    <div class="md:col-span-2"><label class="sgml-label">Título</label><input class="sgml-input" name="title" value="{{ old('title', $entry->title) }}" required></div>
    <div><label class="sgml-label">Início</label><input class="sgml-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $entry->starts_at?->format('Y-m-d\\TH:i')) }}" required></div>
    <div><label class="sgml-label">Término</label><input class="sgml-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $entry->ends_at?->format('Y-m-d\\TH:i')) }}"></div>
    <div class="md:col-span-2"><label class="sgml-label">Fuso horário</label><input class="sgml-input" name="timezone" value="{{ old('timezone', $entry->timezone ?: config('app.timezone')) }}" required></div>
    <div class="md:col-span-2"><label class="sgml-label">Descrição / resumo</label><textarea class="sgml-input" rows="4" name="description" placeholder="Ex.: programação especial da igreja, reunião, conferência, aviso geral...">{{ old('description', $entry->description) }}</textarea></div>
    <div class="md:col-span-2 flex flex-wrap gap-2">
        <button class="sgml-btn-primary">{{ $editing && $entry->status === 'cancelled' ? 'Salvar e reativar programação' : ($editing ? 'Salvar alterações' : 'Criar programação e avisar') }}</button>
        <a class="sgml-btn-secondary" href="{{ route('calendar.index', ['view' => 'month', 'date' => ($entry->starts_at ?? now())->format('Y-m-d')]) }}">Voltar</a>
    </div>
</form>

@if($editing)
<div class="mt-6 grid gap-5 lg:grid-cols-2">
    @perm('calendar_event.cancel')
    <form method="POST" action="{{ route('calendar.events.cancel', $entry) }}" class="sgml-card p-5">
        @csrf
        <h2 class="text-lg font-black">Cancelar programação</h2>
        <p class="mt-1 text-sm text-slate-500">Mantém o registro para auditoria, mas retira a programação da agenda ativa e da assinatura do calendário.</p>
        <input class="mt-4 sgml-input" name="reason" placeholder="Motivo do cancelamento" required>
        <button class="mt-3 sgml-btn-secondary" @disabled($entry->status === 'cancelled')>{{ $entry->status === 'cancelled' ? 'Programação já cancelada' : 'Cancelar programação' }}</button>
    </form>
    @endperm

    @perm('calendar_event.delete')
    <form method="POST" action="{{ route('calendar.events.destroy', $entry) }}" class="sgml-card border-rose-200 p-5">
        @csrf @method('DELETE')
        <h2 class="text-lg font-black text-rose-800">Excluir programação</h2>
        <p class="mt-1 text-sm text-slate-500">Remove definitivamente este item operacional da Agenda. Isso não exclui nenhum culto ou escala porque programações da Agenda são independentes.</p>
        <input class="mt-4 sgml-input" name="reason" placeholder="Motivo da exclusão" required>
        <input class="mt-3 sgml-input" name="confirmation" placeholder="Digite EXCLUIR" required autocomplete="off">
        <button class="mt-3 sgml-btn-danger">Excluir programação</button>
    </form>
    @endperm
</div>
@endif
@endsection
