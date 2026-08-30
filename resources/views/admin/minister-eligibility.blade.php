@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.index') }}" class="text-sm font-semibold text-indigo-600">← Administração</a>
        <h1 class="mt-2 text-2xl font-black">Cultos em que pode ministrar</h1>
        <p class="text-sm text-slate-500">Gerencie a habilitação ministerial por tipo de culto como um grupo: abra o culto, veja quem está autorizado e adicione ou remova ministros.</p>
    </div>
    <a href="{{ route('people.index') }}" class="sgml-btn-secondary">Abrir cadastro de pessoas</a>
</div>

@if($serviceTypes->isEmpty())
    <div class="sgml-card p-10 text-center text-slate-500">Nenhum tipo de culto ativo está cadastrado.</div>
@else
<div class="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="sgml-card p-3">
        <div class="px-2 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Tipos de culto</div>
        <div class="space-y-1">
            @foreach($serviceTypes as $type)
                <a href="{{ route('admin.minister-eligibility', ['type' => $type->id]) }}" class="block rounded-xl px-3 py-3 {{ $selectedType && $selectedType->id === $type->id ? 'bg-indigo-50 text-indigo-800' : 'hover:bg-slate-50' }}">
                    <div class="flex items-center justify-between gap-2">
                        <strong>{{ $type->name }}</strong>
                        <span class="sgml-badge bg-slate-100 text-slate-700">{{ $type->allowed_ministers_count }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1 text-xs text-slate-500">
                        @if($type->requires_authorization)<span class="sgml-badge bg-amber-50 text-amber-700">Autorização específica</span>@endif
                        @if($type->manual_minister_assignment)<span class="sgml-badge bg-violet-50 text-violet-700">Ministro manual</span>@endif
                        @if(!$type->requires_authorization && !$type->manual_minister_assignment)<span>Habilitação ministerial comum</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </aside>

    <section class="space-y-5">
        <div class="sgml-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-black">{{ $selectedType->name }}</h2>
                        @if($selectedType->requires_authorization)<span class="sgml-badge bg-amber-50 text-amber-700">Autorização específica</span>@endif
                        @if($selectedType->manual_minister_assignment)<span class="sgml-badge bg-violet-50 text-violet-700">Definição manual</span>@endif
                    </div>
                    <p class="mt-1 max-w-3xl text-sm text-slate-500">
                        @if($selectedType->manual_minister_assignment)
                            O ministro principal é escolhido manualmente na escala, mas esta lista continua útil para indicar quem está normalmente apto a conduzir este culto.
                        @else
                            A geração automática da escala só poderá escolher pessoas que estejam nesta lista e também estejam disponíveis na data.
                        @endif
                    </p>
                </div>
                <span class="sgml-badge bg-emerald-50 text-emerald-700">{{ $allowedMinisters->count() }} ministro(s)</span>
            </div>
        </div>

        <div class="sgml-card p-5">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black">Ministros habilitados</h3>
                    <p class="text-sm text-slate-500">Remover alguém daqui impede novas sugestões automáticas para este tipo. Escalas já publicadas não são alteradas silenciosamente.</p>
                </div>
                <input id="minister-eligibility-filter" class="sgml-input max-w-xs" type="search" placeholder="Buscar ministro...">
            </div>

            <div id="minister-eligibility-list" class="divide-y divide-slate-100">
                @forelse($allowedMinisters as $minister)
                    <div class="minister-eligibility-row flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between" data-search="{{ mb_strtolower($minister->display_name.' '.$minister->email.' '.($minister->user?->email ?? '')) }}">
                        <div>
                            <div class="font-semibold">{{ $minister->display_name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $minister->congregation?->name ?? 'Sem congregação definida' }}
                                @if($minister->user) · acesso ao sistema: {{ $minister->user->email }} @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('people.edit', $minister) }}" class="sgml-btn-secondary">Editar pessoa</a>
                            <form method="POST" action="{{ route('admin.minister-eligibility.destroy', [$selectedType, $minister]) }}" data-confirm-message="Remover {{ $minister->display_name }} da habilitação para {{ $selectedType->name }}?" data-confirm-danger="1" data-confirm-label="Remover">
                                @csrf @method('DELETE')
                                <button class="sgml-btn-danger">Remover habilitação</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">Nenhum ministro está habilitado para este tipo de culto.</p>
                @endforelse
            </div>
        </div>

        <div class="sgml-card p-5">
            <h3 class="text-lg font-black">Adicionar ministro</h3>
            <p class="mt-1 text-sm text-slate-500">Somente pessoas ativas com uma função ministerial marcada como Ministro aparecem aqui.</p>
            @if($availableMinisters->isEmpty())
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Todos os ministros ativos já estão habilitados para este culto.</div>
            @else
                <form method="POST" action="{{ route('admin.minister-eligibility.store', $selectedType) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    @csrf
                    <select class="sgml-input" name="person_id" required>
                        <option value="">Selecione um ministro</option>
                        @foreach($availableMinisters as $minister)
                            <option value="{{ $minister->id }}">{{ $minister->display_name }} · {{ $minister->congregation?->name ?? 'sem congregação' }}</option>
                        @endforeach
                    </select>
                    <button class="sgml-btn-primary">Adicionar ao culto</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('minister-eligibility-filter');
    const rows = [...document.querySelectorAll('.minister-eligibility-row')];
    input?.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        rows.forEach(row => row.classList.toggle('hidden', term !== '' && !row.dataset.search.includes(term)));
    });
});
</script>
@endsection
