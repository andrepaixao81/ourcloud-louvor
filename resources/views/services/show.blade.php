@extends('layouts.app')
@section('content')
@php
    $weekdayNames = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    $serviceWeekday = $weekdayNames[$service->starts_at->dayOfWeek] ?? '';
@endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-indigo-600">← Voltar</a>
        <h1 class="mt-2 text-2xl font-black">{{ $service->name }}</h1>
        <p class="text-slate-500">{{ $service->starts_at->format('d/m/Y H:i') }}@if($service->ends_at) - {{ $service->ends_at->format('H:i') }}@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @perm('services.update')<a class="sgml-btn-secondary" href="{{ route('services.edit', $service) }}">Editar</a>@endperm
        @perm('repertoire.view')<a class="sgml-btn-secondary" href="{{ route('repertoire.show', $service) }}">Repertório</a>@endperm
        @perm('repertoire.view')<a class="sgml-btn-primary" href="{{ route('repertoire.show', $service) }}">Atualizar louvores</a>@endperm
    </div>
</div>

@if($errors->has('delete'))<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{{ $errors->first('delete') }}</div>@endif

<div class="grid gap-4 md:grid-cols-3">
    <div class="sgml-card p-5"><div class="text-xs uppercase text-slate-400">Culto</div><div class="mt-1 font-bold">{{ $serviceWeekday }}</div><div class="text-sm text-slate-500">{{ $service->name }}</div></div>
    <div class="sgml-card p-5"><div class="text-xs uppercase text-slate-400">Congregação / local</div><div class="mt-1 font-bold">{{ $service->congregation?->name ?? '—' }}</div><div class="text-sm text-slate-500">{{ $service->location?->name }}</div></div>
    <div class="sgml-card p-5"><div class="text-xs uppercase text-slate-400">{{ (int) $service->minister_slots > 1 ? 'Ministros previstos' : 'Ministro previsto' }}</div><div class="mt-1 font-bold">{{ collect([$service->assignment?->minister?->display_name])->filter()->merge($service->additionalMinisters->pluck('minister.display_name')->filter())->implode(', ') ?: 'Ainda não definido' }}</div><div class="text-sm text-slate-500">{{ (int) $service->minister_slots }} vaga(s) prevista(s) · {{ $service->assignment?->status }}</div></div>
</div>

@if($service->description || $service->notes)
<div class="mt-4 sgml-card p-5"><h2 class="font-bold">Informações</h2>@if($service->description)<p class="mt-2 text-sm text-slate-700">{{ $service->description }}</p>@endif @if($service->notes)<p class="mt-3 whitespace-pre-line text-sm text-slate-500">{{ $service->notes }}</p>@endif</div>
@endif

@php
    $serviceRepertoire = $service->repertoire;
    $serviceRepertoireItems = $serviceRepertoire?->items ?? collect();
@endphp
<div class="mt-4 sgml-card p-5">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-black">Louvores do culto</h2>
            <p class="text-sm text-slate-500">O mesmo repertório exibido na escala e no Agenda do Ministério.</p>
        </div>
        @perm('repertoire.view')<a class="sgml-btn-secondary" href="{{ route('repertoire.show', $service) }}">Abrir repertório</a>@endperm
    </div>
    @if($serviceRepertoire?->openingSong || $serviceRepertoireItems->isNotEmpty())
        <div class="space-y-2 text-sm text-slate-700">
            @if($serviceRepertoire?->openingSong)
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3">
                    <strong>Louvor de abertura:</strong> {{ $serviceRepertoire->openingSong->title }}@if($serviceRepertoire->openingMinister) · Ministro {{ $serviceRepertoire->openingMinister->display_name }}@endif @if($serviceRepertoire->opening_key) · Tom {{ $serviceRepertoire->opening_key }}@endif
                    @if($serviceRepertoire->opening_notes)<div class="mt-1 whitespace-pre-line text-slate-600">{{ $serviceRepertoire->opening_notes }}</div>@endif
                </div>
            @endif
            @foreach($serviceRepertoireItems as $item)
                <div class="rounded-xl border border-slate-200 p-3">
                    <strong>Louvor {{ $loop->iteration }}:</strong> {{ $item->song?->title ?? 'Louvor removido da biblioteca' }}@if($item->minister) · Ministro {{ $item->minister->display_name }}@endif @if($item->planned_key) · Tom {{ $item->planned_key }}@endif
                    @if($item->notes)<div class="mt-1 whitespace-pre-line text-slate-600">{{ $item->notes }}</div>@endif
                </div>
            @endforeach
            @if($serviceRepertoire?->final_notes)
                <div class="rounded-xl bg-slate-50 p-3"><strong>Observações:</strong><div class="mt-1 whitespace-pre-line text-slate-600">{{ $serviceRepertoire->final_notes }}</div></div>
            @endif
        </div>
    @else
        <p class="py-6 text-center text-slate-500">Nenhum louvor foi adicionado a este culto.</p>
    @endif
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @perm('services.cancel')
    <div class="sgml-card p-5">
        <h2 class="font-bold">Cancelar culto</h2>
        <p class="mt-1 text-sm text-slate-500">Use quando o culto existiu no planejamento, mas foi cancelado. O histórico e vínculos são preservados.</p>
        @if($service->status !== 'cancelled' && $service->status !== 'completed')
        <form method="POST" action="{{ route('services.cancel', $service) }}" class="mt-4 space-y-3">@csrf<input class="sgml-input" name="reason" required minlength="3" placeholder="Motivo do cancelamento"><button class="sgml-btn-danger">Cancelar culto</button></form>
        @else
        <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Status atual: {{ $service->status === 'cancelled' ? 'Cancelado' : 'Concluído' }}.</div>
        @endif
    </div>
    @endperm

    @if(auth()->user()?->isAdministrator() || auth()->user()?->isMinistryManager())
    <div class="sgml-card border border-amber-200 bg-amber-50/40 p-5">
        <h2 class="font-bold text-amber-900">Zerar dados de teste deste culto</h2>
        <p class="mt-1 text-sm text-amber-800">Use quando um culto foi utilizado em testes e acabou finalizado/publicado. O SGML remove a escala deste culto, ministros adicionais, repertório, participantes e todo o histórico de louvores ministrados, e devolve o culto ao status <strong>Agendado</strong> para que entre novamente na próxima geração da escala.</p>
        <p class="mt-2 text-xs text-amber-700"><strong>Não apaga o cadastro do culto</strong> nem a trilha de auditoria. Esta ação é exclusiva de Administrador/Responsável pelo Ministério e não deve ser usada para apagar um culto real já realizado.</p>
        @if($errors->resetTest->any())
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                @foreach($errors->resetTest->all() as $message)<div>{{ $message }}</div>@endforeach
            </div>
        @endif
        <details class="mt-4 rounded-xl border border-amber-200 bg-white p-3" @if($errors->resetTest->any()) open @endif>
            <summary class="cursor-pointer font-semibold text-amber-900">Quero zerar os dados de teste</summary>
            <form method="POST" action="{{ route('services.reset-test-data', $service) }}" class="mt-4 space-y-3" data-confirm-title="Zerar dados de teste?" data-confirm-message="Escala, repertório, participantes e registros operacionais deste culto serão removidos." data-confirm-danger="1" data-confirm-label="Zerar dados">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold">Motivo</label>
                    <input class="sgml-input" name="reason" required minlength="3" maxlength="2000" placeholder="Ex.: culto criado e finalizado apenas para teste do sistema">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Digite <strong>ZERAR</strong> para confirmar</label>
                    <input class="sgml-input" name="confirmation" required autocomplete="off" pattern="ZERAR" placeholder="ZERAR">
                </div>
                <label class="flex items-start gap-2 text-sm text-amber-900">
                    <input class="mt-1" type="checkbox" name="acknowledge" value="1" required>
                    <span>Entendo que escala, repertório, participantes e registros de <strong>Louvores ministrados</strong> deste culto serão removidos.</span>
                </label>
                <button class="sgml-btn-danger" type="submit">Zerar dados de teste</button>
            </form>
        </details>
    </div>
    @endif

    @perm('services.delete')
    <div class="sgml-card p-5">
        <h2 class="font-bold">Excluir cadastro de teste/rascunho</h2>
        <p class="mt-1 text-sm text-slate-500">Use para cadastro errado ou teste. Rascunho de escala/repertório sem publicação pode ser removido junto. Se já houver escala publicada, repertório publicado ou execução real, o SGML bloqueará e exigirá cancelamento.</p>
        <form method="POST" action="{{ route('services.destroy', $service) }}" class="mt-4" data-confirm-title="Excluir cadastro de teste?" data-confirm-message="O sistema validará se há histórico publicado ou execução real antes de permitir a exclusão." data-confirm-danger="1" data-confirm-label="Excluir culto" data-confirm-reason-label="Motivo da exclusão" data-confirm-reason-min="3">@csrf @method('DELETE')<input type="hidden" name="reason"><button class="sgml-btn-danger">Excluir culto</button></form>
    </div>
    @endperm
</div>
@endsection
