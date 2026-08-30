@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-black">Relatórios</h1>
        <p class="text-sm text-slate-500">Indicadores por período, com detalhamento das ministrações e dos louvores.</p>
    </div>
</div>

<form method="GET" action="{{ route('reports.index') }}" class="mb-6 sgml-card p-5">
    <div class="grid gap-3 md:grid-cols-[minmax(180px,1fr)_minmax(180px,1fr)_auto_auto] md:items-end">
        <div>
            <label class="sgml-label">Data inicial</label>
            <input class="sgml-input" type="date" name="from" value="{{ $from?->format('Y-m-d') }}">
        </div>
        <div>
            <label class="sgml-label">Data final</label>
            <input class="sgml-input" type="date" name="to" value="{{ $to?->format('Y-m-d') }}">
        </div>
        <button class="sgml-btn-primary">Aplicar período</button>
        <a class="sgml-btn-secondary text-center" href="{{ route('reports.index') }}">Limpar</a>
    </div>
    <p class="mt-3 text-xs text-slate-500">
        @if($from || $to)
            Período aplicado: <strong>{{ $from?->format('d/m/Y') ?? 'início dos registros' }}</strong> até <strong>{{ $to?->format('d/m/Y') ?? 'hoje/fim dos registros' }}</strong>.
        @else
            Sem filtro de período: exibindo todo o histórico disponível.
        @endif
    </p>
</form>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="sgml-card p-5">
        <h2 class="font-bold">Distribuição de Ministrações</h2>
        <div class="mt-4 space-y-3">
            @forelse($ministerDistribution as $row)
                <div>
                    <div class="flex justify-between text-sm">
                        <span>{{ $row['name'] ?: 'Sem ministro' }}</span>
                        <strong>{{ $row['count'] }}</strong>
                    </div>
                    <div class="mt-1 h-2 rounded bg-slate-100">
                        <div class="h-2 rounded bg-indigo-500" style="width: {{ min(100, $row['count'] * 10) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sem dados no período.</p>
            @endforelse
        </div>
    </div>

    <div class="sgml-card p-5">
        <h2 class="font-bold">Louvores mais ministrados</h2>
        <p class="mt-1 text-xs text-slate-500">Clique no louvor para ver em quais cultos foi ministrado e por quais ministros.</p>
        <div class="mt-4 divide-y divide-slate-100">
            @forelse($songs as $row)
                <a class="flex items-center justify-between gap-3 py-2 text-sm hover:text-indigo-700" href="{{ route('reports.song', array_filter(['song' => $row->song_id, 'from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')])) }}">
                    <span class="font-medium">{{ $row->song?->title ?? 'Louvor removido' }}</span>
                    <span class="sgml-badge bg-indigo-50 text-indigo-700">{{ $row->total }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Sem execuções finalizadas no período.</p>
            @endforelse
        </div>
    </div>

    <div class="sgml-card p-5">
        <h2 class="font-bold">Participações registradas</h2>
        <div class="mt-4 divide-y divide-slate-100">
            @forelse($participations as $row)
                <div class="flex justify-between py-2 text-sm">
                    <span>{{ $row->person?->display_name ?? 'Pessoa' }}</span>
                    <strong>{{ $row->total }}</strong>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sem participações no período.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
