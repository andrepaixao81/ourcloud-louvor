@extends('layouts.app')
@section('content')
@php
    $weekdayNames = [0=>'Domingo',1=>'Segunda-feira',2=>'Terça-feira',3=>'Quarta-feira',4=>'Quinta-feira',5=>'Sexta-feira',6=>'Sábado'];
    $parityNames = ['any'=>'Todas as datas deste dia','even'=>'Somente datas pares','odd'=>'Somente datas ímpares'];
    $monthParityNames = ['even'=>'Dias pares do mês','odd'=>'Dias ímpares do mês'];
    $hasRecurringRules = $parityRules->isNotEmpty() || $weeklyRules->isNotEmpty();
@endphp

<div class="sgml-page-header">
    <div>
        <div class="sgml-page-kicker">Minha Agenda</div>
        <h1 class="sgml-page-title">Minha disponibilidade</h1>
        <p class="sgml-page-description">Diga ao sistema quando você pode ministrar. Comece pela sua rotina semanal e use exceções para viagens, compromissos e datas pontuais.</p>
    </div>
    <a class="sgml-btn-secondary" href="{{ route('my.agenda') }}"><x-icon name="chevron-left" class="h-4 w-4"/>Voltar à agenda</a>
</div>

<div class="mb-6 rounded-2xl border {{ $hasRecurringRules ? 'border-indigo-200 bg-indigo-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $hasRecurringRules ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}"><x-icon name="info"/></div>
        <div>
            <div class="font-black {{ $hasRecurringRules ? 'text-indigo-950' : 'text-emerald-950' }}">{{ $hasRecurringRules ? 'Você possui regras de disponibilidade ativas' : 'Sem restrição semanal cadastrada' }}</div>
            <p class="mt-1 text-sm leading-relaxed {{ $hasRecurringRules ? 'text-indigo-800' : 'text-emerald-800' }}">@if($hasRecurringRules)O sistema considera disponível um culto quando ele combina com pelo menos uma das suas regras. Exceções por data têm prioridade.@elseSem regras recorrentes, você não é bloqueado por dia da semana. Cadastre abaixo somente se quiser limitar os dias em que pode ministrar; viagens e impedimentos pontuais podem ser informados como exceções.@endif</p>
        </div>
    </div>
</div>

<div class="space-y-7">
    <section>
        <div class="mb-3"><div class="sgml-page-kicker">1. Rotina habitual</div><h2 class="sgml-section-title">Quando você normalmente pode ministrar?</h2><p class="sgml-section-description">Adicione apenas os dias que fazem parte da sua rotina. Horários são opcionais.</p></div>
        <div class="grid gap-4 xl:grid-cols-[1.15fr_.85fr]">
            <div class="sgml-card p-5 sm:p-6">
                <div class="flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><x-icon name="calendar"/></div><div><h3 class="font-black">Por dia da semana</h3><p class="mt-1 text-sm text-slate-500">Ex.: domingos e quartas, ou apenas sexta à noite.</p></div></div>
                <form method="POST" action="{{ route('availability.fixed.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                    @csrf<input type="hidden" name="rule_scope" value="weekday">
                    <div class="sm:col-span-2"><label class="sgml-label">Dia da semana</label><select class="sgml-input" name="weekday" required>@foreach($weekdayNames as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label class="sgml-label">A partir de <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" type="time" name="starts_time"></div>
                    <div><label class="sgml-label">Até <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" type="time" name="ends_time"></div>
                    <input type="hidden" name="parity_rule" value="any">
                    <div class="sm:col-span-2"><button class="sgml-btn-primary"><x-icon name="plus" class="h-4 w-4"/>Adicionar dia disponível</button></div>
                </form>
            </div>

            <details class="sgml-card p-5 sm:p-6">
                <summary class="cursor-pointer list-none">
                    <div class="flex items-start justify-between gap-3"><div class="flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><x-icon name="settings"/></div><div><h3 class="font-black">Regra avançada por data par/ímpar</h3><p class="mt-1 text-sm text-slate-500">Para rotinas que dependem do número do dia do mês.</p></div></div><span class="sgml-badge-muted">Avançado</span></div>
                </summary>
                <form method="POST" action="{{ route('availability.fixed.store') }}" class="mt-5 space-y-3 border-t border-slate-100 pt-5">
                    @csrf<input type="hidden" name="rule_scope" value="month_parity">
                    <div><label class="sgml-label">Quais datas?</label><select class="sgml-input" name="parity_rule" required>@foreach($monthParityNames as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-2"><div><label class="sgml-label">De</label><input class="sgml-input" type="time" name="starts_time"></div><div><label class="sgml-label">Até</label><input class="sgml-input" type="time" name="ends_time"></div></div>
                    <button class="sgml-btn-secondary">Adicionar regra</button>
                </form>
            </details>
        </div>
    </section>

    @if($hasRecurringRules)
    <section>
        <div class="mb-3"><h2 class="sgml-section-title">Minha rotina cadastrada</h2><p class="sgml-section-description">Revise e ajuste as regras que o gerador de escala considera.</p></div>
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach($weeklyRules as $rule)
                <div class="sgml-card p-5">
                    <form method="POST" action="{{ route('availability.fixed.update', $rule) }}" class="space-y-3">@csrf @method('PATCH')<input type="hidden" name="rule_scope" value="weekday">
                        <div class="flex items-center justify-between gap-3"><span class="sgml-badge-info"><x-icon name="calendar" class="h-3.5 w-3.5"/>Rotina semanal</span><button type="submit" class="text-xs font-black text-indigo-700">Salvar alterações</button></div>
                        <select class="sgml-input" name="weekday" required>@foreach($weekdayNames as $value=>$label)<option value="{{ $value }}" @selected((int)$rule->weekday===$value)>{{ $label }}</option>@endforeach</select>
                        <div class="grid grid-cols-2 gap-2"><input class="sgml-input" type="time" name="starts_time" value="{{ $rule->starts_time }}"><input class="sgml-input" type="time" name="ends_time" value="{{ $rule->ends_time }}"></div>
                        <select class="sgml-input" name="parity_rule">@foreach($parityNames as $value=>$label)<option value="{{ $value }}" @selected($rule->parity_rule===$value)>{{ $label }}</option>@endforeach</select>
                    </form>
                    <form method="POST" action="{{ route('availability.fixed.destroy', $rule) }}" class="mt-3 border-t border-slate-100 pt-3" data-confirm-title="Remover disponibilidade?" data-confirm-message="Esta regra deixará de limitar sua disponibilidade para futuras escalas." data-confirm-label="Remover regra" data-confirm-danger="1">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-600">Remover regra</button></form>
                </div>
            @endforeach
            @foreach($parityRules as $rule)
                <div class="sgml-card p-5">
                    <form method="POST" action="{{ route('availability.fixed.update', $rule) }}" class="space-y-3">@csrf @method('PATCH')<input type="hidden" name="rule_scope" value="month_parity">
                        <div class="flex items-center justify-between gap-3"><span class="sgml-badge-muted">Regra avançada</span><button type="submit" class="text-xs font-black text-indigo-700">Salvar alterações</button></div>
                        <select class="sgml-input" name="parity_rule" required>@foreach($monthParityNames as $value=>$label)<option value="{{ $value }}" @selected($rule->parity_rule===$value)>{{ $label }}</option>@endforeach</select>
                        <div class="grid grid-cols-2 gap-2"><input class="sgml-input" type="time" name="starts_time" value="{{ $rule->starts_time }}"><input class="sgml-input" type="time" name="ends_time" value="{{ $rule->ends_time }}"></div>
                    </form>
                    <form method="POST" action="{{ route('availability.fixed.destroy', $rule) }}" class="mt-3 border-t border-slate-100 pt-3" data-confirm-title="Remover regra avançada?" data-confirm-message="Esta regra por data par/ímpar deixará de ser considerada nas futuras escalas." data-confirm-label="Remover regra" data-confirm-danger="1">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-600">Remover regra</button></form>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    <section>
        <div class="mb-3"><div class="sgml-page-kicker">2. Exceções</div><h2 class="sgml-section-title">Algo diferente em uma data?</h2><p class="sgml-section-description">Use para dizer “neste dia não posso” ou liberar uma data que normalmente ficaria fora da sua rotina.</p></div>
        <div class="sgml-card p-5 sm:p-6">
            <form method="POST" action="{{ route('availability.date.store') }}" class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_1fr]">@csrf
                <div><label class="sgml-label">Data</label><input class="sgml-input" type="date" name="override_date" required></div>
                <div><label class="sgml-label">Neste dia estarei</label><select class="sgml-input" name="kind" required><option value="unavailable">Indisponível</option><option value="available">Disponível</option></select></div>
                <div><label class="sgml-label">De <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" type="time" name="starts_time"></div>
                <div><label class="sgml-label">Até <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" type="time" name="ends_time"></div>
                <div class="lg:col-span-3"><label class="sgml-label">Observação <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" name="notes" placeholder="Ex.: compromisso de família"></div>
                <div class="flex items-end"><button class="sgml-btn-primary w-full">Salvar exceção</button></div>
            </form>
        </div>
        @if($dateOverrides->isNotEmpty())
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                @foreach($dateOverrides as $override)
                    <div class="sgml-card flex items-start gap-4 p-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $override->kind === 'available' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}"><x-icon :name="$override->kind === 'available' ? 'check' : 'x'"/></div>
                        <div class="min-w-0 flex-1"><div class="font-black">{{ $override->override_date->format('d/m/Y') }} · {{ $override->kind === 'available' ? 'Disponível' : 'Indisponível' }}</div><div class="mt-1 text-sm text-slate-500">@if($override->starts_time || $override->ends_time){{ $override->starts_time ?: '00:00' }} até {{ $override->ends_time ?: '23:59' }}@else Dia inteiro @endif @if($override->notes)· {{ $override->notes }}@endif</div></div>
                        <form method="POST" action="{{ route('availability.date.destroy', $override) }}" data-confirm-title="Remover exceção?" data-confirm-message="A data voltará a seguir suas regras normais de disponibilidade." data-confirm-label="Remover" data-confirm-danger="1">@csrf @method('DELETE')<button class="rounded-lg p-2 text-rose-600 hover:bg-rose-50" aria-label="Remover"><x-icon name="x" class="h-4 w-4"/></button></form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <div class="mb-3"><div class="sgml-page-kicker">3. Ausências</div><h2 class="sgml-section-title">Vou ficar indisponível por um período</h2><p class="sgml-section-description">Ideal para férias, viagens, trabalho ou qualquer intervalo com início e fim.</p></div>
        <div class="sgml-card p-5 sm:p-6">
            <form method="POST" action="{{ route('availability.temporary.store') }}" class="grid gap-3 lg:grid-cols-2">@csrf
                <div><label class="sgml-label">Início</label><input class="sgml-input" type="datetime-local" name="starts_at" required></div>
                <div><label class="sgml-label">Fim</label><input class="sgml-input" type="datetime-local" name="ends_at" required></div>
                <div><label class="sgml-label">Motivo</label><select class="sgml-input" name="reason_type"><option value="personal">Pessoal</option><option value="travel">Viagem</option><option value="work">Trabalho</option><option value="health">Saúde</option><option value="other">Outro</option></select></div>
                <div><label class="sgml-label">Observação privada <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" name="private_notes" placeholder="Somente para contexto administrativo"></div>
                <div class="lg:col-span-2"><button class="sgml-btn-primary">Registrar período</button></div>
            </form>
            @if($temporary->isNotEmpty())
                <div class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                    @foreach($temporary as $unavailability)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><x-icon name="calendar" class="h-4 w-4"/></span><div><div class="font-bold">{{ $unavailability->starts_at->format('d/m/Y H:i') }} até {{ $unavailability->ends_at->format('d/m/Y H:i') }}</div><div class="text-xs text-slate-500">Período bloqueado para escala</div></div></div><form method="POST" action="{{ route('availability.temporary.destroy', $unavailability) }}" data-confirm-title="Remover indisponibilidade?" data-confirm-message="Este período deixará de bloquear futuras escalas." data-confirm-label="Remover" data-confirm-danger="1">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-600">Remover</button></form></div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
