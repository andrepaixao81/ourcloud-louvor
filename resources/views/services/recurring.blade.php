@extends('layouts.app')

@section('content')
@php
    $weekdays = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];
    $nth = [
        1 => 'Primeira',
        2 => 'Segunda',
        3 => 'Terceira',
        4 => 'Quarta',
        5 => 'Quinta',
        -1 => 'Última',
    ];
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.index') }}" class="text-sm font-semibold text-indigo-600">← Administração</a>
        <h1 class="mt-2 text-2xl font-black">Regras da agenda</h1>
        <p class="text-sm text-slate-500">Revise padrões criados a partir do calendário. Para o uso diário, monte o mês em Cultos e utilize “Validar mês como padrão”.</p>
    </div>
    <a href="{{ route('services.index') }}" class="sgml-btn-primary">Abrir calendário de cultos</a>
</div>

<form method="GET" class="mb-4 flex items-center gap-2">
    <select class="sgml-input w-auto" name="state" onchange="this.form.submit()">
        <option value="active" @selected($state === 'active')>Ativas</option>
        <option value="deleted" @selected($state === 'deleted')>Excluídas</option>
        <option value="all" @selected($state === 'all')>Todas</option>
    </select>
</form>

<div class="space-y-4">
    @forelse($rules as $rule)
        @php
            $humanRule = match($rule->frequency) {
                'weekly' => 'Toda '.($weekdays[$rule->weekday] ?? 'semana'),
                'monthly_nth' => ($nth[$rule->week_of_month] ?? 'Ocorrência').' '.($weekdays[$rule->weekday] ?? '').' do mês',
                'monthly_date' => 'Todo dia '.$rule->day_of_month.' do mês',
                default => $rule->frequency,
            };
        @endphp

        <div class="sgml-card p-5 {{ $rule->trashed() ? 'opacity-70' : '' }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-lg font-bold">{{ $rule->name }}</span>
                        @if($rule->trashed())
                            <span class="sgml-badge bg-slate-200 text-slate-700">EXCLUÍDA</span>
                        @elseif(! $rule->active)
                            <span class="sgml-badge bg-amber-50 text-amber-700">PAUSADA</span>
                        @else
                            <span class="sgml-badge bg-emerald-50 text-emerald-700">ATIVA</span>
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                        {{ $humanRule }} · {{ substr((string) $rule->starts_time, 0, 5) }}
                        @if($rule->ends_time)
                            - {{ substr((string) $rule->ends_time, 0, 5) }}
                        @endif
                        · {{ $rule->type?->name }}
                    </div>
                    @if($rule->frequency === 'weekly' && !empty($rule->excluded_weeks_of_month))
                        <div class="mt-1 text-xs font-semibold text-amber-700">
                            Não se aplica na(s) ocorrência(s):
                            @foreach($rule->excluded_weeks_of_month as $excludedWeek)
                                {{ $excludedWeek == -1 ? 'última' : $excludedWeek.'ª' }}
                                @if(! $loop->last)
                                    ,
                                @endif
                            @endforeach
                            do mês.
                        </div>
                    @endif
                    <div class="mt-1 text-xs text-slate-400">
                        Desde {{ $rule->starts_on?->format('d/m/Y') }}
                        @if($rule->ends_on)
                            até {{ $rule->ends_on->format('d/m/Y') }}
                        @else
                            · sem data final
                        @endif
                    </div>
                </div>

                @if($rule->trashed())
                    <form method="POST" action="{{ route('services.recurring.restore', $rule->id) }}">
                        @csrf
                        <button class="sgml-btn-secondary">Restaurar regra</button>
                    </form>
                @endif
            </div>

            @if(! $rule->trashed())
                <details class="mt-4 rounded-xl border border-slate-200 p-4">
                    <summary class="cursor-pointer font-semibold">Editar regra</summary>

                    <form method="POST" action="{{ route('services.recurring.update', $rule) }}" class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        @csrf
                        @method('PATCH')

                        <div class="lg:col-span-2">
                            <label class="sgml-label">Nome</label>
                            <input class="sgml-input" name="name" value="{{ $rule->name }}" required>
                        </div>

                        <div>
                            <label class="sgml-label">Tipo</label>
                            <select class="sgml-input" name="service_type_id">
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" @selected($rule->service_type_id === $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="sgml-label">Repetição</label>
                            <select class="sgml-input" name="frequency">
                                <option value="weekly" @selected($rule->frequency === 'weekly')>Semanal</option>
                                <option value="monthly_nth" @selected($rule->frequency === 'monthly_nth')>Ocorrência do dia no mês</option>
                                <option value="monthly_date" @selected($rule->frequency === 'monthly_date')>Dia fixo do mês</option>
                            </select>
                        </div>

                        <div>
                            <label class="sgml-label">Dia da semana</label>
                            <select class="sgml-input" name="weekday">
                                <option value="">Não se aplica</option>
                                @foreach($weekdays as $value => $label)
                                    <option value="{{ $value }}" @selected((int) $rule->weekday === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="sgml-label">Ocorrência no mês</label>
                            <select class="sgml-input" name="week_of_month">
                                <option value="">Não se aplica</option>
                                @foreach($nth as $value => $label)
                                    <option value="{{ $value }}" @selected((int) $rule->week_of_month === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="font-bold text-amber-900">Exceções da regra semanal</div>
                            <p class="mt-1 text-sm text-amber-800">Marque as ocorrências do mês em que esta regra <strong>não deve gerar culto</strong>. Ex.: para Culto de Ensino todo domingo, marque <strong>2ª ocorrência</strong> quando o segundo domingo é reservado à Santa Ceia.</p>
                            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                                @foreach([1=>'1ª ocorrência',2=>'2ª ocorrência',3=>'3ª ocorrência',4=>'4ª ocorrência',5=>'5ª ocorrência',-1=>'Última ocorrência'] as $value=>$label)
                                    <label class="flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-2">
                                        <input type="checkbox" name="excluded_weeks_of_month[]" value="{{ $value }}" @checked(in_array($value, array_map('intval', $rule->excluded_weeks_of_month ?? []), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-amber-700">Este campo só é aplicado quando a repetição é <strong>Semanal</strong>.</p>
                        </div>

                        <div>
                            <label class="sgml-label">Dia fixo do mês</label>
                            <input class="sgml-input" type="number" min="1" max="31" name="day_of_month" value="{{ $rule->day_of_month }}">
                        </div>

                        <div>
                            <label class="sgml-label">Início da série</label>
                            <input class="sgml-input" type="date" name="starts_on" value="{{ $rule->starts_on?->format('Y-m-d') }}" required>
                        </div>

                        <div>
                            <label class="sgml-label">Fim da série</label>
                            <input class="sgml-input" type="date" name="ends_on" value="{{ $rule->ends_on?->format('Y-m-d') }}">
                        </div>

                        <div>
                            <label class="sgml-label">Horário inicial</label>
                            <input class="sgml-input" type="time" name="starts_time" value="{{ substr((string) $rule->starts_time, 0, 5) }}" required>
                        </div>

                        <div>
                            <label class="sgml-label">Horário final</label>
                            <input class="sgml-input" type="time" name="ends_time" value="{{ $rule->ends_time ? substr((string) $rule->ends_time, 0, 5) : '' }}">
                        </div>

                        <div>
                            <label class="sgml-label">Congregação</label>
                            <select class="sgml-input" name="congregation_id">
                                <option value="">Não definida</option>
                                @foreach($congregations as $item)
                                    <option value="{{ $item->id }}" @selected($rule->congregation_id === $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="sgml-label">Local</label>
                            <select class="sgml-input" name="location_id">
                                <option value="">Não definido</option>
                                @foreach($locations as $item)
                                    <option value="{{ $item->id }}" @selected($rule->location_id === $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" name="timezone" value="{{ $rule->timezone }}">

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="active" value="1" @checked($rule->active)>
                            Regra ativa
                        </label>

                        <div class="lg:col-span-3">
                            <button class="sgml-btn-secondary">Salvar alterações</button>
                        </div>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('services.recurring.destroy', $rule) }}"
                        class="mt-4 border-t border-slate-100 pt-4"
                        data-confirm-title="Excluir regra recorrente?" data-confirm-message="Os cultos já gerados serão preservados." data-confirm-danger="1" data-confirm-label="Excluir regra" data-confirm-reason-label="Motivo da exclusão" data-confirm-reason-min="3"
                    >
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="reason">
                        <button class="sgml-btn-danger">Excluir regra</button>
                    </form>
                </details>
            @endif
        </div>
    @empty
        <div class="sgml-card p-10 text-center text-slate-500">Nenhuma regra de agenda.</div>
    @endforelse
</div>
@endsection
