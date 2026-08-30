@extends('layouts.app')
@section('content')
@php
    $monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $weekdayNames = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
@endphp

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-black">Cultos</h1>
        <p class="text-sm text-slate-500">Cadastre por uma data específica clicando no calendário ou preencha todos os dias de uma semana do mês de uma vez. Depois, valide o mês como padrão apenas se quiser repetir nos meses seguintes.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @perm('services.create')
            <a href="{{ route('services.month-template', ['month' => $month->format('Y-m')]) }}" class="sgml-btn-secondary">Validar mês como padrão</a>
            <button type="button" class="sgml-btn-secondary" data-open-weekday-dialog>+ Por dia da semana</button>
            <button type="button" class="sgml-btn-primary" data-open-service-dialog data-date="{{ $month->copy()->startOfMonth()->format('Y-m-d') }}">+ Data específica</button>
        @endperm
    </div>
</div>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <a class="sgml-btn-secondary" href="{{ route('services.index', ['month' => $month->copy()->subMonth()->format('Y-m'), 'state' => $state, 'view' => $view]) }}">←</a>
        <x-month-year-picker :month="$month" route-name="services.index" :query="['state' => $state, 'view' => $view]" id="services-month-picker" />
        <a class="sgml-btn-secondary" href="{{ route('services.index', ['month' => $month->copy()->addMonth()->format('Y-m'), 'state' => $state, 'view' => $view]) }}">→</a>
        <a class="text-sm font-semibold text-indigo-600" href="{{ route('services.index', ['month' => now()->format('Y-m')]) }}">Hoje</a>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <select class="sgml-input w-auto" name="state" onchange="this.form.submit()">
            <option value="active" @selected($state === 'active')>Ativos</option>
            <option value="deleted" @selected($state === 'deleted')>Excluídos</option>
            <option value="all" @selected($state === 'all')>Todos</option>
        </select>
        <select class="sgml-input w-auto" name="view" onchange="this.form.submit()">
            <option value="calendar" @selected($view === 'calendar')>Calendário</option>
            <option value="list" @selected($view === 'list')>Lista</option>
        </select>
    </form>
</div>

@if($view === 'calendar')
    <div class="sgml-card overflow-x-auto">
        <div style="min-width: 840px">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-bold uppercase tracking-wide text-slate-500" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr))">
            @foreach($weekdayNames as $weekday)
                <div class="px-2 py-3">{{ $weekday }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr))">
            @foreach($calendarDays as $day)
                @php
                    $dateKey = $day->format('Y-m-d');
                    $dayServices = $servicesByDate->get($dateKey, collect());
                    $outside = $day->month !== $month->month;
                    $today = $day->isToday();
                @endphp
                <div class="min-h-32 border-b border-r border-slate-100 p-2 {{ $outside ? 'bg-slate-50/70 text-slate-400' : 'bg-white' }}">
                    <div class="mb-2 flex items-center justify-between">
                        @perm('services.create')
                            <button type="button" data-open-service-dialog data-date="{{ $dateKey }}" class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $today ? 'bg-indigo-600 text-white' : 'hover:bg-indigo-50 hover:text-indigo-700' }}" title="Adicionar culto em {{ $day->format('d/m/Y') }}">{{ $day->day }}</button>
                        @else
                            <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $today ? 'bg-indigo-600 text-white' : '' }}">{{ $day->day }}</span>
                        @endperm
                        @perm('services.create')
                            <button type="button" data-open-service-dialog data-date="{{ $dateKey }}" class="text-lg leading-none text-slate-300 hover:text-indigo-600" title="Adicionar">+</button>
                        @endperm
                    </div>
                    <div class="space-y-1.5">
                        @foreach($dayServices as $service)
                            <a href="{{ $service->trashed() ? '#' : route('services.show', ['service' => $service, 'return_to' => request()->getRequestUri()]) }}" class="block rounded-lg border px-2 py-1.5 text-xs {{ $service->trashed() ? 'border-slate-200 bg-slate-100 text-slate-500' : ($service->status === 'cancelled' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-indigo-100 bg-indigo-50 text-indigo-800 hover:border-indigo-300') }}">
                                <div class="font-bold">{{ $service->starts_at->format('H:i') }} · {{ $service->name }}</div>
                                <div class="truncate opacity-80">{{ $service->type?->name ?? 'Tipo excluído' }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        </div>
    </div>
@else
    <div class="space-y-3">
        @forelse($services->filter(fn($service) => $service->starts_at->month === $month->month) as $service)
            <div class="sgml-card flex flex-wrap items-center justify-between gap-4 p-4 {{ $service->trashed() ? 'opacity-75' : '' }}">
                <div>
                    <div class="font-bold">{{ $service->name }}</div>
                    <div class="text-sm text-slate-500">{{ $service->starts_at->format('d/m/Y H:i') }}@if($service->ends_at) - {{ $service->ends_at->format('H:i') }}@endif · {{ $service->type?->name ?? 'Tipo excluído' }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($service->trashed())
                        @perm('services.restore')
                            <form method="POST" action="{{ route('services.restore', $service->id) }}">@csrf<button class="sgml-btn-secondary">Restaurar</button></form>
                        @endperm
                    @else
                        <a class="sgml-btn-secondary" href="{{ route('services.show', ['service' => $service, 'return_to' => request()->getRequestUri()]) }}">Abrir</a>
                        @perm('services.update')<a class="sgml-btn-secondary" href="{{ route('services.edit', $service) }}">Editar</a>@endperm
                        @perm('services.delete')
                            <form method="POST" action="{{ route('services.destroy', $service) }}" data-confirm-title="Excluir culto?" data-confirm-message="O culto será arquivado conforme as regras de preservação de histórico." data-confirm-danger="1" data-confirm-label="Excluir culto" data-confirm-reason-label="Motivo da exclusão" data-confirm-reason-min="3">@csrf @method('DELETE')<input type="hidden" name="reason"><button class="sgml-btn-danger">Excluir</button></form>
                        @endperm
                    @endif
                </div>
            </div>
        @empty
            <div class="sgml-card p-10 text-center text-slate-500">Nenhum culto neste mês.</div>
        @endforelse
    </div>
@endif

@perm('services.create')
<dialog id="service-dialog" class="m-auto w-[min(94vw,680px)] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/40">
    <form method="POST" action="{{ route('services.store') }}" class="bg-white p-6">
        @csrf
        <input type="hidden" name="return_to" value="calendar">
        <div class="flex items-start justify-between gap-3">
            <div><h2 class="text-xl font-black">Adicionar em uma data específica</h2><p id="service-dialog-date-label" class="mt-1 text-sm text-slate-500"></p></div>
            <button type="button" data-close-service-dialog class="rounded-lg px-3 py-1 text-xl text-slate-400 hover:bg-slate-100">×</button>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="sgml-label">Data</label><input class="sgml-input" type="date" name="service_date" id="dialog-service-date" required></div>
            <div class="md:col-span-2"><label class="sgml-label">Tipo de culto/evento</label><select class="sgml-input" name="service_type_id" required><option value="">Selecione</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">O tipo será usado como nome padrão do culto.</p></div>
            <div><label class="sgml-label">Início</label><input class="sgml-input" type="time" name="starts_time" required></div>
            <div><label class="sgml-label">Término</label><input class="sgml-input" type="time" name="ends_time"></div>
            <div><label class="sgml-label">Congregação</label><select class="sgml-input" name="congregation_id"><option value="">Não definida</option>@foreach($congregations as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="sgml-label">Local</label><select class="sgml-input" name="location_id"><option value="">Não definido</option>@foreach($locations as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label class="sgml-label">Título específico <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" name="name" placeholder="Ex.: Saída evangelística"><p class="mt-1 text-xs text-slate-500">Deixe em branco para usar o nome do tipo selecionado.</p></div>
            <div class="md:col-span-2"><label class="sgml-label">Observações <span class="font-normal text-slate-400">(opcional)</span></label><textarea class="sgml-input" name="notes" rows="2"></textarea></div>
        </div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close-service-dialog class="sgml-btn-secondary">Cancelar</button><button class="sgml-btn-primary">Adicionar à agenda</button></div>
    </form>
</dialog>

<dialog id="weekday-dialog" class="m-auto w-[min(94vw,720px)] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/40">
    <form method="POST" action="{{ route('services.month-batch.store') }}" class="bg-white p-6">
        @csrf
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-black">Adicionar por dia da semana</h2>
                <p class="mt-1 text-sm text-slate-500">Cria o mesmo culto em todas as ocorrências do dia escolhido em {{ $monthNames[$month->month] }} de {{ $month->year }}. Isso ainda não cria recorrência para outros meses.</p>
            </div>
            <button type="button" data-close-weekday-dialog class="rounded-lg px-3 py-1 text-xl text-slate-400 hover:bg-slate-100">×</button>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="sgml-label">Dia da semana</label><select class="sgml-input" name="weekday" required>@foreach(['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'] as $i => $label)<option value="{{ $i }}">{{ $label }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label class="sgml-label">Tipo de culto/evento</label><select class="sgml-input" name="service_type_id" required><option value="">Selecione</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
            <div><label class="sgml-label">Início</label><input class="sgml-input" type="time" name="starts_time" required></div>
            <div><label class="sgml-label">Término</label><input class="sgml-input" type="time" name="ends_time"></div>
            <div><label class="sgml-label">Congregação</label><select class="sgml-input" name="congregation_id"><option value="">Não definida</option>@foreach($congregations as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="sgml-label">Local</label><select class="sgml-input" name="location_id"><option value="">Não definido</option>@foreach($locations as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label class="sgml-label">Título específico <span class="font-normal text-slate-400">(opcional)</span></label><input class="sgml-input" name="name" placeholder="Deixe em branco para usar o tipo"></div>
            <div class="md:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-sm text-indigo-800"><strong>Próximo passo:</strong> depois de montar e revisar o mês, use “Validar mês como padrão” somente se quiser levar essa agenda para os meses seguintes.</div>
        </div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close-weekday-dialog class="sgml-btn-secondary">Cancelar</button><button class="sgml-btn-primary">Preencher este mês</button></div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('service-dialog');
    const weekdayDialog = document.getElementById('weekday-dialog');
    const dateInput = document.getElementById('dialog-service-date');
    const dateLabel = document.getElementById('service-dialog-date-label');
    const open = (date) => {
        if (!dialog) return;
        dateInput.value = date;
        const parsed = new Date(date + 'T12:00:00');
        dateLabel.textContent = parsed.toLocaleDateString('pt-BR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
        dialog.showModal();
    };
    document.querySelectorAll('[data-open-service-dialog]').forEach((button) => button.addEventListener('click', () => open(button.dataset.date)));
    document.querySelectorAll('[data-close-service-dialog]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
    document.querySelectorAll('[data-open-weekday-dialog]').forEach((button) => button.addEventListener('click', () => weekdayDialog?.showModal()));
    document.querySelectorAll('[data-close-weekday-dialog]').forEach((button) => button.addEventListener('click', () => weekdayDialog?.close()));
});
</script>
@endperm
@endsection
