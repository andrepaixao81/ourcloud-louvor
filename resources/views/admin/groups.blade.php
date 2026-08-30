@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.index') }}" class="text-sm font-semibold text-indigo-600">← Administração</a>
        <h1 class="mt-2 text-2xl font-black">Grupos de acesso</h1>
        <p class="text-sm text-slate-500">Gerencie os papéis como grupos: veja quem pertence a cada grupo e adicione ou remova usuários sem abrir uma conta por vez.</p>
    </div>
    <a href="{{ route('admin.users') }}" class="sgml-btn-secondary">Usuários e ACL individual</a>
</div>

@if($roles->isEmpty())
    <div class="sgml-card p-10 text-center text-slate-500">Nenhum grupo de acesso está cadastrado.</div>
@else
<div class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
    <aside class="sgml-card p-3">
        <div class="px-2 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Grupos</div>
        <div class="space-y-1">
            @foreach($roles as $role)
                <a href="{{ route('admin.groups', ['role' => $role->id]) }}" class="block rounded-xl px-3 py-3 {{ $selectedRole && $selectedRole->id === $role->id ? 'bg-indigo-50 text-indigo-800' : 'hover:bg-slate-50' }}">
                    <div class="flex items-center justify-between gap-2">
                        <strong>{{ $role->name }}</strong>
                        <span class="sgml-badge bg-slate-100 text-slate-700">{{ $role->users->count() }}</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ $role->permissions->count() }} permissão(ões){{ $role->system ? ' · grupo padrão' : '' }}</div>
                </a>
            @endforeach
        </div>
    </aside>

    <section class="space-y-5">
        <div class="sgml-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-black">{{ $selectedRole->name }}</h2>
                        @if($selectedRole->system)<span class="sgml-badge bg-indigo-50 text-indigo-700">Grupo padrão</span>@endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $selectedRole->description ?: 'Grupo de acesso da organização.' }}</p>
                </div>
                <span class="sgml-badge bg-emerald-50 text-emerald-700">{{ $selectedRole->users->count() }} usuário(s)</span>
            </div>

            <details class="mt-4 rounded-xl border border-slate-200 p-3">
                <summary class="cursor-pointer text-sm font-semibold">Permissões deste grupo ({{ $selectedRole->permissions->count() }})</summary>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse($selectedRole->permissions as $permission)
                        <span class="sgml-badge bg-slate-100 text-slate-700" title="{{ $permission->name }}">{{ $permission->description ?: $permission->name }}</span>
                    @empty
                        <span class="text-sm text-slate-500">Este grupo não concede permissões operacionais.</span>
                    @endforelse
                </div>
            </details>
        </div>

        <div class="sgml-card p-5">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black">Usuários neste grupo</h3>
                    <p class="text-sm text-slate-500">Remover alguém daqui retira somente este grupo. ACL individual e outros grupos permanecem inalterados.</p>
                </div>
                <input id="group-member-filter" class="sgml-input max-w-xs" type="search" placeholder="Buscar neste grupo...">
            </div>

            <div id="group-member-list" class="divide-y divide-slate-100">
                @forelse($selectedRole->users as $member)
                    <div class="group-member-row flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between" data-search="{{ mb_strtolower($member->name.' '.$member->email) }}">
                        <div>
                            <div class="font-semibold">{{ $member->name }}</div>
                            <div class="text-xs text-slate-500">{{ $member->email }} · {{ $member->active ? 'Ativo' : 'Inativo' }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.users', ['user' => $member->id]) }}" class="sgml-btn-secondary">Gerenciar conta</a>
                            <form method="POST" action="{{ route('admin.groups.members.destroy', [$selectedRole, $member]) }}" data-confirm-message="Remover este usuário deste grupo de acesso?">
                                @csrf @method('DELETE')
                                <button class="sgml-btn-danger">Remover do grupo</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">Nenhum usuário pertence a este grupo.</p>
                @endforelse
            </div>
        </div>

        <div class="sgml-card p-5">
            <h3 class="text-lg font-black">Adicionar usuário ao grupo</h3>
            <p class="mt-1 text-sm text-slate-500">A conta receberá imediatamente as permissões deste grupo, respeitando eventuais ACLs individuais de negação.</p>
            @if($availableUsers->isEmpty())
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Todos os usuários da organização já pertencem a este grupo.</div>
            @else
                <form method="POST" action="{{ route('admin.groups.members.store', $selectedRole) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    @csrf
                    <select class="sgml-input" name="user_id" required>
                        <option value="">Selecione um usuário</option>
                        @foreach($availableUsers as $availableUser)
                            <option value="{{ $availableUser->id }}">{{ $availableUser->name }} · {{ $availableUser->email }}{{ $availableUser->active ? '' : ' · inativo' }}</option>
                        @endforeach
                    </select>
                    <button class="sgml-btn-primary">Adicionar ao grupo</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('group-member-filter');
    const rows = [...document.querySelectorAll('.group-member-row')];
    input?.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        rows.forEach(row => row.classList.toggle('hidden', term !== '' && !row.dataset.search.includes(term)));
    });
});
</script>
@endsection
