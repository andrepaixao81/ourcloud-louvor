@extends('layouts.app')
@section('content')
@php
    $monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $prev = $monthDate->copy()->subMonth()->format('Y-m');
    $next = $monthDate->copy()->addMonth()->format('Y-m');
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">🎂 Aniversariantes do mês</h1>
            <p class="mt-1 text-sm text-slate-500">A lista mostra somente dia e mês. Ano de nascimento e idade não são exibidos aos demais usuários.</p>
        </div>
        <div class="flex items-center gap-2">
            <a class="sgml-btn-secondary" href="{{ route('birthdays.index', ['month' => $prev]) }}">←</a>
            <span class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-bold">{{ $monthNames[$monthDate->month] }} {{ $monthDate->year }}</span>
            <a class="sgml-btn-secondary" href="{{ route('birthdays.index', ['month' => $next]) }}">→</a>
        </div>
    </div>

    <section class="sgml-card p-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($birthdays as $person)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-xs font-bold uppercase text-indigo-600">{{ $person->birth_date->format('d/m') }}</div>
                    <div class="mt-1 font-black text-slate-900">{{ $person->display_name }}</div>
                    @if($person->birth_date->format('m-d') === now()->format('m-d'))
                        <div class="mt-2 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">🎉 É hoje!</div>
                    @endif
                </div>
            @empty
                <div class="sm:col-span-2 lg:col-span-3 rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Nenhum aniversário cadastrado neste mês.</div>
            @endforelse
        </div>
    </section>

    <section class="sgml-card p-6">
        <h2 class="font-black">Próximos aniversários</h2>
        <p class="mt-1 text-sm text-slate-500">Visão rápida dos próximos 30 dias.</p>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($upcoming as $person)
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                    <span class="font-bold">{{ $person->display_name }}</span>
                    <span class="text-slate-500">· {{ $person->next_birthday_at->format('d/m') }}</span>
                    <div class="text-xs text-slate-500">
                        @if((int)$person->days_until_birthday === 0) hoje
                        @elseif((int)$person->days_until_birthday === 1) amanhã
                        @else em {{ (int)$person->days_until_birthday }} dias
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">Nenhum aniversário nos próximos 30 dias.</div>
            @endforelse
        </div>
    </section>

    @if($canManageSettings)
        <section class="sgml-card p-6">
            <h2 class="text-lg font-black">Mensagem automática de aniversário</h2>
            <p class="mt-1 text-sm text-slate-500">Administrador e Responsável pelo Ministério podem personalizar a felicitação enviada pelo WhatsApp no dia do aniversário.</p>
            <form method="POST" action="{{ route('birthdays.settings.update') }}" class="mt-4 space-y-4">
                @csrf @method('PATCH')
                <label class="flex items-center gap-2 font-semibold">
                    <input type="checkbox" name="enabled" value="1" @checked($settings['enabled'])>
                    Enviar felicitação automática pelo WhatsApp
                </label>
                <div class="max-w-xs">
                    <label class="sgml-label">Horário de envio</label>
                    <input class="sgml-input" type="time" name="send_time" value="{{ old('send_time', $settings['send_time']) }}" required>
                    <p class="mt-1 text-xs text-slate-500">O scheduler verifica periodicamente os aniversariantes e evita envio duplicado no mesmo dia.</p>
                </div>
                <div>
                    <label class="sgml-label">Mensagem</label>
                    <textarea class="sgml-input min-h-56" name="message" maxlength="4000" required>{{ old('message', $settings['message']) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Variáveis disponíveis: <strong>{nome}</strong>, <strong>{primeiro_nome}</strong>, <strong>{dia}</strong>, <strong>{mes}</strong> e <strong>{igreja}</strong>.</p>
                </div>
                <button class="sgml-btn-primary" data-loading-label="Salvando...">Salvar mensagem de aniversário</button>
            </form>
        </section>
    @endif
</div>
@endsection
