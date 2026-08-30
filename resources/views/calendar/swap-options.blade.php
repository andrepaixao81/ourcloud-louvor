@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Solicitar troca</h1>
            <p class="text-sm text-slate-500">{{ $assignment->service->name }} · {{ $assignment->service->starts_at->format('d/m/Y H:i') }}</p>
        </div>
        <a class="sgml-btn-secondary" href="{{ route('my.agenda') }}">Voltar à Minha Agenda</a>
    </div>

    @if($activeSwap)
        <div class="sgml-card p-5 text-sm text-amber-800">
            Já existe uma troca aguardando resposta de <strong>{{ $activeSwap->targetPerson?->display_name ?? 'outro ministro' }}</strong> para este culto.
        </div>
    @else
        @php($selectableCount = $targets->where('selectable', true)->count())
        <div class="sgml-card p-5">
            <form method="POST" action="{{ route('swaps.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="schedule_assignment_id" value="{{ $assignment->id }}">
                <div>
                    <label class="sgml-label">Ministro que poderá assumir</label>
                    <select class="sgml-input" name="target_person_id" required>
                        <option value="">Escolha o ministro</option>
                        @foreach($targets as $targetOption)
                            @php($target = $targetOption['person'])
                            <option value="{{ $target->id }}" @disabled(! $targetOption['selectable'])>
                                {{ $target->display_name }}{{ ! $target->user || ! $target->user->active ? ' · sem acesso ao sistema' : '' }}{{ ! $targetOption['selectable'] ? ' · indisponível: '.implode('; ', $targetOption['blocking_reasons']) : (($targetOption['warnings'] ?? []) ? ' · atenção: '.implode('; ', $targetOption['warnings']) : '') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sgml-label">Motivo</label>
                    <textarea class="sgml-input" name="reason" required minlength="3" maxlength="2000" rows="4" placeholder="Informe o motivo da troca"></textarea>
                </div>
                <button class="sgml-btn-primary" @disabled($selectableCount === 0)>Solicitar troca</button>
            </form>
            @if($targets->isEmpty())
                <p class="mt-3 text-sm text-amber-700">Não há outro ministro ativo cadastrado.</p>
            @elseif($selectableCount === 0)
                <p class="mt-3 text-sm text-amber-700">Todos os demais ministros possuem indisponibilidade ou conflito de horário neste momento.</p>
            @endif
        </div>
    @endif
</div>
@endsection
