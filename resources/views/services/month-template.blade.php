@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('services.index', ['month' => $month->format('Y-m')]) }}" class="text-sm font-semibold text-indigo-600">← Calendário de cultos</a>
        <h1 class="mt-2 text-2xl font-black">Validar mês como padrão</h1>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <span class="text-sm font-semibold text-slate-600">Mês analisado:</span>
            <x-month-year-picker :month="$month" route-name="services.month-template" id="template-month-picker" />
        </div>
        <p class="mt-2 text-sm text-slate-500">O SGML analisa os cultos que você montou no mês selecionado. Você confirma quais padrões devem continuar nos meses seguintes. Nada é repetido sem sua confirmação.</p>
    </div>
    @perm('administration.settings')<a href="{{ route('services.recurring') }}" class="sgml-btn-secondary">Gerenciar regras existentes</a>@endperm
</div>

@if($candidates->isEmpty())
    <div class="sgml-card p-10 text-center">
        <div class="font-bold">Ainda não há cultos suficientes neste mês.</div>
        <p class="mt-1 text-sm text-slate-500">Volte ao calendário, clique nos dias e monte primeiro a agenda real do mês.</p>
        <a href="{{ route('services.index', ['month' => $month->format('Y-m')]) }}" class="mt-4 sgml-btn-primary">Montar agenda do mês</a>
    </div>
@else
<form method="POST" action="{{ route('services.month-template.apply') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

    <div class="sgml-card p-5">
        <div class="grid gap-4 md:grid-cols-[1fr,280px] md:items-end">
            <div>
                <h2 class="font-bold">Até quando aplicar o padrão?</h2>
                <p class="mt-1 text-sm text-slate-500">Para “sem data final”, o SGML cria apenas uma janela futura e continua expandindo automaticamente pelo agendador.</p>
            </div>
            <select class="sgml-input" name="horizon">
                <option value="3">Próximos 3 meses</option>
                <option value="6">Próximos 6 meses</option>
                <option value="12" selected>Próximos 12 meses</option>
                <option value="none">Sem data final</option>
            </select>
        </div>
    </div>

    <div class="space-y-3">
        @foreach($candidates as $index => $candidate)
            <div class="sgml-card grid gap-4 p-5 lg:grid-cols-[1fr,360px] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><span class="text-lg font-bold">{{ $candidate['name'] }}</span><span class="sgml-badge bg-indigo-50 text-indigo-700">{{ $candidate['type'] }}</span></div>
                    <div class="mt-1 text-sm text-slate-500">{{ ucfirst($candidate['weekday_label']) }} às {{ $candidate['starts_time'] }}@if($candidate['ends_time']) - {{ $candidate['ends_time'] }}@endif</div>
                    <div class="mt-2 text-xs text-slate-500">Datas encontradas no mês: {{ $candidate['dates']->join(', ') }}.</div>
                    @if($candidate['count'] >= 3)
                        <div class="mt-2 text-xs font-semibold text-emerald-700">Padrão semanal provável: apareceu {{ $candidate['count'] }} vezes no mesmo dia/horário.</div>
                    @else
                        <div class="mt-2 text-xs text-amber-700">Apareceu poucas vezes. O SGML não repetirá automaticamente; escolha uma regra somente se este culto realmente tiver padrão mensal.</div>
                    @endif
                </div>
                <div>
                    <input type="hidden" name="patterns[{{ $index }}][service_id]" value="{{ $candidate['sample_id'] }}">
                    <label class="sgml-label">Como deve repetir?</label>
                    <select class="sgml-input" name="patterns[{{ $index }}][mode]">
                        <option value="none" @selected($candidate['default_mode'] === 'none')>Não repetir - manter somente o que já foi cadastrado</option>
                        <option value="weekly" @selected($candidate['default_mode'] === 'weekly')>Toda {{ $candidate['weekday_label'] }}</option>
                        <option value="monthly_nth_1">Primeira {{ $candidate['weekday_label'] }} de cada mês</option>
                        <option value="monthly_nth_2">Segunda {{ $candidate['weekday_label'] }} de cada mês</option>
                        <option value="monthly_nth_3">Terceira {{ $candidate['weekday_label'] }} de cada mês</option>
                        <option value="monthly_nth_4">Quarta {{ $candidate['weekday_label'] }} de cada mês</option>
                        <option value="monthly_nth_5">Quinta {{ $candidate['weekday_label'] }} de cada mês</option>
                        <option value="monthly_nth_last">Última {{ $candidate['weekday_label'] }} de cada mês</option>
                    </select>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap justify-end gap-2">
        <a href="{{ route('services.index', ['month' => $month->format('Y-m')]) }}" class="sgml-btn-secondary">Cancelar</a>
        <button class="sgml-btn-primary">Validar e preparar meses seguintes</button>
    </div>
</form>
@endif
@endsection
