@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Auditoria</h1>
    <p class="text-sm text-slate-500">Trilha de operações relevantes por organização, com identificação do usuário responsável.</p>
</div>

<form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row">
    <input class="sgml-input max-w-lg" name="event" value="{{ request('event') }}" placeholder="Filtrar evento">
    <button class="sgml-btn-secondary">Filtrar</button>
    @if(request()->filled('event'))<a href="{{ route('audit.index') }}" class="sgml-btn-secondary">Limpar</a>@endif
</form>

<div class="sgml-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Usuário</th>
                    <th class="px-4 py-3">Entidade</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-4 py-3">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $log->event }}</td>
                        <td class="px-4 py-3">
                            @if($log->user)
                                <div class="font-semibold text-slate-800">{{ $log->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $log->user->email }}</div>
                                <div class="mt-1 text-[11px] text-slate-400" title="ID do usuário">{{ $log->user_id }}</div>
                            @elseif($log->user_id)
                                <div class="font-semibold text-slate-600">Usuário removido</div>
                                <div class="text-[11px] text-slate-400">{{ $log->user_id }}</div>
                            @else
                                <span class="sgml-badge bg-slate-100 text-slate-700">Sistema</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="font-semibold text-slate-700">{{ $log->auditable_type ? class_basename($log->auditable_type) : '—' }}</div>
                            <div class="mt-1 break-all text-slate-400">{{ $log->auditable_id ?: '—' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $log->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Nenhum evento de auditoria encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($logs->hasPages())
    @php
        $current = $logs->currentPage();
        $last = $logs->lastPage();
        $firstPage = max(1, $current - 2);
        $lastPage = min($last, $current + 2);
    @endphp
    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="text-slate-500">
            Exibindo <strong class="text-slate-700">{{ $logs->firstItem() }}-{{ $logs->lastItem() }}</strong> de <strong class="text-slate-700">{{ $logs->total() }}</strong> registros
        </div>
        <nav aria-label="Paginação da auditoria" class="flex flex-wrap items-center gap-1">
            @if($logs->onFirstPage())
                <span class="rounded-lg border border-slate-200 px-3 py-2 text-slate-300">Anterior</span>
            @else
                <a class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50" href="{{ $logs->previousPageUrl() }}">Anterior</a>
            @endif

            @if($firstPage > 1)
                <a class="rounded-lg border border-slate-200 px-3 py-2" href="{{ $logs->url(1) }}">1</a>
                @if($firstPage > 2)<span class="px-1 text-slate-400">…</span>@endif
            @endif

            @for($page = $firstPage; $page <= $lastPage; $page++)
                @if($page === $current)
                    <span aria-current="page" class="rounded-lg bg-indigo-600 px-3 py-2 font-bold text-white">{{ $page }}</span>
                @else
                    <a class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50" href="{{ $logs->url($page) }}">{{ $page }}</a>
                @endif
            @endfor

            @if($lastPage < $last)
                @if($lastPage < $last - 1)<span class="px-1 text-slate-400">…</span>@endif
                <a class="rounded-lg border border-slate-200 px-3 py-2" href="{{ $logs->url($last) }}">{{ $last }}</a>
            @endif

            @if($logs->hasMorePages())
                <a class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50" href="{{ $logs->nextPageUrl() }}">Próxima</a>
            @else
                <span class="rounded-lg border border-slate-200 px-3 py-2 text-slate-300">Próxima</span>
            @endif
        </nav>
    </div>
@endif
@endsection
