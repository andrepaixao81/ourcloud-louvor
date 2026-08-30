@extends('layouts.app')
@section('content')
<div class="sgml-page-header">
    <div>
        <div class="sgml-page-kicker">Pessoas</div>
        <h1 class="sgml-page-title">Pessoas do ministério</h1>
        <p class="sgml-page-description">Cadastre membros, ministros e participantes. Dados pessoais, funções ministeriais e acesso ao sistema ficam organizados sem expor a estrutura técnica de usuários.</p>
    </div>
    <div class="flex flex-wrap gap-2">@if(auth()->user()?->isAdministrator())<a href="{{ route('admin.users') }}" class="sgml-btn-secondary"><x-icon name="shield" class="h-4 w-4"/>Acessos</a>@endif @perm('users.create')<a href="{{ route('people.create') }}" class="sgml-btn-primary"><x-icon name="plus" class="h-4 w-4"/>Nova pessoa</a>@endperm</div>
</div>

<form method="GET" class="mb-5 flex max-w-2xl gap-2">
    <div class="relative min-w-0 flex-1"><input class="sgml-input pl-10" name="q" value="{{ $search ?? '' }}" placeholder="Buscar por nome, e-mail ou telefone"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="users" class="h-4 w-4"/></span></div>
    <button class="sgml-btn-secondary">Buscar</button>
    @if(filled($search ?? null))<a class="sgml-btn-tertiary" href="{{ route('people.index') }}">Limpar</a>@endif
</form>

<div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50/80 text-left text-[11px] font-black uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3.5">Pessoa</th><th class="px-5 py-3.5">Funções</th><th class="px-5 py-3.5">Congregação</th><th class="px-5 py-3.5">Acesso</th><th class="px-5 py-3.5">Status</th><th class="px-5 py-3.5"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($people as $person)
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="px-5 py-4"><div class="flex items-center gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 font-black text-indigo-700">{{ mb_strtoupper(mb_substr($person->display_name,0,1)) }}</div><div><div class="font-black text-slate-900">{{ $person->display_name }}</div><div class="text-xs text-slate-500">{{ $person->email ?: ($person->phone ?: 'Sem contato cadastrado') }}</div></div></div></td>
                        <td class="px-5 py-4"><div class="flex max-w-xs flex-wrap gap-1.5">@forelse($person->ministryRoles as $role)<span class="sgml-badge-muted">{{ $role->name }}</span>@empty<span class="text-slate-400">Sem função</span>@endforelse</div></td>
                        <td class="px-5 py-4 text-slate-600">{{ $person->congregation?->name ?? '—' }}</td>
                        <td class="px-5 py-4">@if($person->user)<span class="sgml-badge-success"><x-icon name="check" class="h-3 w-3"/>Ativo no sistema</span>@else<span class="sgml-badge-muted">Sem acesso</span>@endif</td>
                        <td class="px-5 py-4">@if($person->status==='active')<span class="sgml-badge-success">Ativo</span>@else<span class="sgml-badge-muted">Inativo</span>@endif</td>
                        <td class="px-5 py-4 text-right"><a class="sgml-btn-tertiary" href="{{ route('people.edit',$person) }}">Ver / editar</a></td>
                    </tr>
                @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhuma pessoa encontrada.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid gap-3 lg:hidden">
    @forelse($people as $person)
        <article class="sgml-card p-4">
            <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 font-black text-indigo-700">{{ mb_strtoupper(mb_substr($person->display_name,0,1)) }}</div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-black">{{ $person->display_name }}</h2>@if($person->status==='active')<span class="sgml-badge-success">Ativo</span>@else<span class="sgml-badge-muted">Inativo</span>@endif</div><div class="mt-1 text-sm text-slate-500">{{ $person->email ?: ($person->phone ?: 'Sem contato cadastrado') }}</div></div></div>
            <div class="mt-4 flex flex-wrap gap-1.5">@forelse($person->ministryRoles as $role)<span class="sgml-badge-muted">{{ $role->name }}</span>@empty<span class="text-xs text-slate-400">Sem função ministerial</span>@endforelse</div>
            <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-xs"><div><span class="block font-bold text-slate-400">Congregação</span><span class="mt-1 block font-semibold text-slate-700">{{ $person->congregation?->name ?? 'Não definida' }}</span></div><div><span class="block font-bold text-slate-400">Acesso</span><span class="mt-1 block font-semibold {{ $person->user ? 'text-emerald-700' : 'text-slate-600' }}">{{ $person->user ? 'Ativo no sistema' : 'Sem acesso' }}</span></div></div>
            <div class="mt-4 flex gap-2"><a class="sgml-btn-primary flex-1" href="{{ route('people.edit',$person) }}">Ver / editar</a>@if(auth()->user()?->isAdministrator() && $person->user)<a class="sgml-btn-secondary" href="{{ route('admin.users', ['user'=>$person->user->id]) }}#user-{{ $person->user->id }}">Acesso</a>@endif</div>
        </article>
    @empty<div class="sgml-card p-10 text-center text-slate-500">Nenhuma pessoa encontrada.</div>@endforelse
</div>

<div class="mt-5">{{ $people->links() }}</div>
@endsection
