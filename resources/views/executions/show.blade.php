@extends('layouts.app')
@section('content')
@php
    $statusNames = [
        'not_started' => 'Não iniciado',
        'in_progress' => 'Em andamento',
        'reopened' => 'Reaberto',
        'finished' => 'Finalizado',
        'interrupted' => 'Interrompido',
    ];
    $backRoute = $canManageAny ? route('services.show', $service) : route('my.agenda');
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ $backRoute }}" class="text-sm font-semibold text-indigo-600">← {{ $canManageAny ? $service->name : 'Minha Agenda' }}</a>
        <h1 class="mt-2 text-2xl font-black">Modo Culto</h1>
        <p class="text-sm text-slate-500">
            {{ $service->starts_at->format('d/m/Y H:i') }} · status:
            <strong>{{ $statusNames[$execution->status] ?? strtoupper($execution->status) }}</strong>
        </p>
    </div>

    @if($canOperate)
        <div class="flex flex-wrap gap-2">
            @if($execution->status === 'not_started')
                <form method="POST" action="{{ route('execution.start', $service) }}">
                    @csrf
                    <button class="sgml-btn-primary">Iniciar ministração</button>
                </form>
            @elseif(in_array($execution->status, ['in_progress', 'reopened'], true))
                <form method="POST" action="{{ route('execution.finish', $execution) }}" data-confirm-message="Finalizar a ministração deste culto?">
                    @csrf
                    <button class="sgml-btn-primary">Finalizar ministração</button>
                </form>
                <form method="POST" action="{{ route('execution.cancel', $execution) }}" data-confirm-message="Cancelar apenas a abertura do Modo Culto? O culto continuará agendado. Esta opção só funciona quando ainda não há registros efetivos.">
                    @csrf
                    <button class="sgml-btn-danger">Cancelar Modo Culto</button>
                </form>
            @endif
        </div>
    @endif
</div>

@if(!$canManageAny)
    <div class="mb-5 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-800">
        Este Modo Culto pertence à sua escala publicada. Você pode conduzir a execução dos louvores. O cadastro de músicos, backs, ministros efetivos e demais participantes é exclusivo da administração.
    </div>
@endif

<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-4 xl:col-span-2">
        <div class="sgml-card p-5">
            <h2 class="text-lg font-bold">Repertório planejado</h2>
            <div class="mt-3 space-y-2">
                @forelse($service->repertoire?->items ?? collect() as $item)
                    @php
                        $done = $execution->songs->firstWhere('repertoire_item_id', $item->id);
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border {{ $done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200' }} p-3">
                        <div class="w-7 text-center font-bold text-slate-400">{{ $item->position }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $item->song?->title }}</div>
                            <div class="text-xs text-slate-500">
                                Ministro: {{ $item->minister?->display_name ?: 'não definido' }} · Tom planejado: {{ $item->planned_key ?: '—' }}
                                @if($done)
                                    · ministrado em {{ $done->performed_key ?: 'tom não informado' }}
                                @endif
                            </div>
                        </div>
                        @if($execution->status === 'in_progress' && !$done && $canOperate)
                            <form method="POST" action="{{ route('execution.perform-repertoire', [$execution, $item]) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input class="sgml-input max-w-24" name="performed_key" value="{{ $item->planned_key }}" placeholder="Tom">
                                @if($canManageAny && $ministerOptions->count() > 1)
                                    <select class="sgml-input min-w-44" name="performed_by_person_id">
                                        <option value="">Quem ministrou?</option>
                                        @foreach($ministerOptions as $ministerOption)<option value="{{ $ministerOption->id }}" @selected($item->minister_person_id === $ministerOption->id)>{{ $ministerOption->display_name }}</option>@endforeach
                                    </select>
                                @endif
                                <button class="sgml-btn-primary whitespace-nowrap">Ministrado</button>
                            </form>
                        @elseif($done)
                            <span class="font-bold text-emerald-700">✓</span>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Nenhum repertório planejado.</p>
                @endforelse
            </div>
        </div>

        <div class="sgml-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">Execução real</h2>
                    <p class="mt-1 text-xs text-slate-500">Aqui ficam somente os louvores efetivamente registrados durante esta abertura do Modo Culto.</p>
                </div>
            </div>
            @if($canCorrectSongs && in_array($execution->status, ['in_progress', 'reopened'], true) && $execution->songs->isNotEmpty())
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Precisa desfazer uma marcação feita por engano? Corrija o tom ou use <strong>Desfazer registro</strong>. Para cancelar a abertura do Modo Culto, não pode restar nenhum louvor, ministro ou participante efetivo registrado.
                </div>
            @endif
            <div class="mt-3 divide-y divide-slate-100">
                @forelse($execution->songs as $performed)
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <span class="w-7 text-center font-black text-slate-400">{{ $performed->position }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $performed->song?->title ?? $performed->song_title_snapshot ?? 'Louvor' }}</div>
                            <div class="text-xs text-slate-500">{{ $performed->execution_source === 'repertoire' ? 'Planejado' : 'Adicionado durante o culto' }} · Tom: {{ $performed->performed_key ?: '—' }} @if($performed->performer) · Ministrado por: {{ $performed->performer->display_name }} @endif</div>
                        </div>
                        @if($canCorrectSongs)
                            <form method="POST" action="{{ route('execution.song.update', [$execution, $performed]) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <input class="sgml-input w-24" name="performed_key" value="{{ $performed->performed_key }}" placeholder="Tom">
                                @if($canManageAny && $ministerOptions->isNotEmpty())
                                    <select class="sgml-input min-w-44" name="performed_by_person_id">
                                        <option value="">Ministro não individualizado</option>
                                        @foreach($ministerOptions as $ministerOption)<option value="{{ $ministerOption->id }}" @selected($performed->performed_by_person_id === $ministerOption->id)>{{ $ministerOption->display_name }}</option>@endforeach
                                    </select>
                                @endif
                                <button class="sgml-btn-secondary whitespace-nowrap">Corrigir tom</button>
                            </form>
                            <form method="POST" action="{{ route('execution.song.destroy', [$execution, $performed]) }}" data-confirm-message="Desfazer este registro de louvor ministrado? Ele deixará de constar na execução real e poderá ser marcado novamente depois.">
                                @csrf @method('DELETE')
                                <button class="text-sm font-semibold text-rose-600 hover:underline">Desfazer registro</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Nenhum louvor registrado como ministrado.</p>
                @endforelse
            </div>
        </div>

        @if($execution->status === 'in_progress' && $canOperate)
            <form method="POST" action="{{ route('execution.perform-song', $execution) }}" class="sgml-card grid gap-3 p-5 sm:grid-cols-4">
                @csrf
                <h2 class="sm:col-span-4 font-bold">Louvor não planejado</h2>
                <select class="sgml-input sm:col-span-2" name="song_id" required>
                    <option value="">Selecione na biblioteca</option>
                    @foreach($songs as $song)
                        <option value="{{ $song->id }}">{{ $song->title }} @if($song->artist_reference) - {{ $song->artist_reference }} @endif</option>
                    @endforeach
                </select>
                <input class="sgml-input" name="performed_key" placeholder="Tom">
                @if($canManageAny && $ministerOptions->count() > 1)
                    <select class="sgml-input" name="performed_by_person_id"><option value="">Quem ministrou?</option>@foreach($ministerOptions as $ministerOption)<option value="{{ $ministerOption->id }}">{{ $ministerOption->display_name }}</option>@endforeach</select>
                @else
                    <div></div>
                @endif
                <div class="sm:col-span-4">
                    <button class="sgml-btn-primary">Adicionar como ministrado</button>
                </div>
            </form>
        @endif
    </div>

    <div class="space-y-4">
        <div class="sgml-card p-5">
            <h2 class="font-bold">Ministros efetivos</h2>
            <div class="mt-3 space-y-2">
                @forelse($execution->ministers as $minister)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <div>
                            <strong>{{ $minister->person?->display_name }}</strong>
                            @if($minister->is_primary)
                                <span class="ml-2 sgml-badge bg-indigo-50 text-indigo-700">Principal</span>
                            @endif
                        </div>
                        @if($canManageRoster)
                            <form method="POST" action="{{ route('execution.minister.destroy', [$execution, $minister]) }}" data-confirm-message="Remover este ministro do registro efetivo?">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Remover</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum ministro efetivo registrado.</p>
                @endforelse
            </div>

            @if($canManageRoster && in_array($execution->status, ['in_progress', 'finished', 'reopened'], true))
                <form method="POST" action="{{ route('execution.minister', $execution) }}" class="mt-4 space-y-2">
                    @csrf
                    <select class="sgml-input" name="person_id" required>
                        <option value="">Adicionar ministro</option>
                        @foreach($people as $person)
                            <option value="{{ $person->id }}">{{ $person->display_name }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" name="is_primary" value="1"> Ministro principal
                    </label>
                    <button class="sgml-btn-secondary">Registrar ministro</button>
                </form>
            @endif
        </div>

        <div class="sgml-card p-5">
            <h2 class="font-bold">Participantes efetivos</h2>
            <div class="mt-3 space-y-3">
                @forelse($execution->participants as $participant)
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <div>
                            <strong>{{ $participant->person?->display_name }}</strong>
                            <div class="text-xs text-slate-500">{{ $participant->roles->pluck('name')->join(', ') }}</div>
                        </div>
                        @if($canManageRoster)
                            <form method="POST" action="{{ route('execution.participant.destroy', [$execution, $participant]) }}" data-confirm-message="Remover este participante do registro efetivo?">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Remover</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum músico/back/participante registrado.</p>
                @endforelse
            </div>

            @if($canManageRoster && in_array($execution->status, ['in_progress', 'finished', 'reopened'], true))
                <form method="POST" action="{{ route('execution.participant', $execution) }}" class="mt-4 space-y-3">
                    @csrf
                    <select class="sgml-input" name="person_id" required>
                        <option value="">Pessoa</option>
                        @foreach($people as $person)
                            <option value="{{ $person->id }}">{{ $person->display_name }}</option>
                        @endforeach
                    </select>
                    <div class="grid gap-2">
                        @foreach($roles as $role)
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}"> {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    <textarea class="sgml-input" name="notes" placeholder="Observação opcional"></textarea>
                    <button class="sgml-btn-secondary">Registrar participante</button>
                </form>
            @elseif(!$canManageRoster)
                <p class="mt-4 text-xs text-slate-500">Inclusão e correção de músicos, backs e demais participantes: somente administração.</p>
            @endif
        </div>
    </div>
</div>
@endsection
