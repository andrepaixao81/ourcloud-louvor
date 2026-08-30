@extends('layouts.app')
@section('content')
<div class="sgml-page-header">
    <div><a href="{{ route('admin.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm font-bold text-indigo-700"><x-icon name="chevron-left" class="h-4 w-4"/>Administração</a><div class="sgml-page-kicker">Acesso ao sistema</div><h1 class="sgml-page-title">Usuários e acessos</h1><p class="sgml-page-description">Crie contas, atribua perfis e ajuste permissões específicas somente quando necessário.</p></div>
    <div class="flex flex-wrap gap-2"><a href="{{ route('admin.groups') }}" class="sgml-btn-secondary">Grupos de acesso</a><a href="{{ route('admin.mail.index') }}" class="sgml-btn-secondary">SMTP: {{ $smtpConfigured ? 'configurado' : 'configurar' }}</a><a href="{{ route('people.index') }}" class="sgml-btn-secondary">Pessoas</a></div>
</div>

<details class="mb-6 sgml-card p-5" @if($selectedPerson) open @endif>
    <summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h2 class="font-black text-slate-900">Conceder novo acesso</h2><p class="mt-1 text-sm text-slate-500">Crie uma conta para uma pessoa já cadastrada ou informe os dados para gerar o cadastro pessoal automaticamente.</p></div><span class="sgml-btn-primary !min-h-9 !px-3 !py-1.5"><x-icon name="plus" class="h-4 w-4"/>Novo acesso</span></div></summary>
    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-5 grid gap-4 lg:grid-cols-2">
        @csrf
        <div><label class="sgml-label">Pessoa cadastrada</label><select class="sgml-input" name="person_id" id="access-person"><option value="">Criar cadastro pessoal automaticamente</option>@foreach($people as $person)<option value="{{ $person->id }}" data-name="{{ $person->display_name }}" data-email="{{ $person->email }}" data-phone="{{ $person->phone }}" data-birth="{{ $person->birth_date?->format('Y-m-d') }}" @selected(old('person_id', $selectedPerson?->id) === $person->id)>{{ $person->display_name }}{{ $person->email ? ' · '.$person->email : '' }}</option>@endforeach</select></div>
        <div><label class="sgml-label">Nome da conta</label><input id="access-name" class="sgml-input" name="name" value="{{ old('name', $selectedPerson?->display_name) }}" required></div>
        <div><label class="sgml-label">E-mail de login</label><input id="access-email" class="sgml-input" type="email" name="email" value="{{ old('email', $selectedPerson?->email) }}" required></div>
        <div><label class="sgml-label">Telefone / WhatsApp</label><input id="access-phone" class="sgml-input" type="tel" data-br-phone name="phone" value="{{ old('phone', $selectedPerson?->phone) }}" placeholder="(21) 99999-9999"></div>
        <div><label class="sgml-label">Data de nascimento</label><input id="access-birth" class="sgml-input" type="date" name="birth_date" value="{{ old('birth_date', $selectedPerson?->birth_date?->format('Y-m-d')) }}"></div>
        <div>
            <label class="sgml-label">Entrega da senha</label>
            <div class="space-y-2 rounded-xl border border-slate-200 p-3 text-sm">
                <label class="flex items-start gap-2"><input type="radio" name="delivery_mode" value="manual" @checked(old('delivery_mode', 'manual') === 'manual')><span><strong>Criar senha agora</strong><span class="block text-xs text-slate-500">O líder informa a senha à pessoa.</span></span></label>
                <label class="flex items-start gap-2 {{ $smtpConfigured ? '' : 'opacity-60' }}"><input type="radio" name="delivery_mode" value="generated_email" @checked(old('delivery_mode') === 'generated_email') @disabled(! $smtpConfigured)><span><strong>Gerar e enviar por e-mail</strong><span class="block text-xs text-slate-500">Senha temporária com troca obrigatória no primeiro acesso.</span></span></label>
            </div>
        </div>
        <div id="manual-password-fields"><label class="sgml-label">Senha inicial</label><input class="sgml-input" type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Mínimo de 12 caracteres"><label class="mt-2 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="force_change_password" value="1" @checked(old('force_change_password', true))> Exigir alteração no primeiro acesso</label></div>
        <div><div class="sgml-label">Papéis</div><div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">@foreach($roles as $role)<label class="flex items-start gap-2 text-sm"><input class="mt-1" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(collect(old('roles', []))->contains($role->id))><span><strong>{{ $role->name }}</strong>@if($role->description)<span class="block text-xs text-slate-500">{{ $role->description }}</span>@endif</span></label>@endforeach</div></div>
        <div class="lg:col-span-2"><button class="sgml-btn-primary">Criar acesso</button></div>
    </form>
</details>

<div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
    <div class="sgml-card p-5">
        <div class="mb-4"><h2 class="font-bold">Usuários cadastrados</h2><p class="text-sm text-slate-500">Pesquise por nome, e-mail ou pessoa vinculada.</p></div>
        <form method="GET" class="mb-4 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
            <input class="sgml-input" name="q" value="{{ $search }}" placeholder="Buscar usuário...">
            <select class="sgml-input" name="status"><option value="all" @selected($status==='all')>Todos</option><option value="active" @selected($status==='active')>Ativos</option><option value="inactive" @selected($status==='inactive')>Inativos</option></select>
            <button class="sgml-btn-secondary">Buscar</button>
        </form>
        <div class="divide-y divide-slate-100">
            @forelse($users as $user)
                <div class="py-3 {{ $selectedUser?->id === $user->id ? 'rounded-lg bg-indigo-50 px-3' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><div class="truncate font-semibold">{{ $user->name }}</div><div class="truncate text-xs text-slate-500">{{ $user->email }}</div><div class="mt-1 text-xs text-slate-500">{{ $user->person?->display_name ?? 'Sem pessoa vinculada' }} · {{ $user->roles->pluck('name')->join(', ') ?: 'Sem papel' }}</div></div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <a class="text-sm font-semibold text-indigo-600" href="{{ route('admin.users', array_filter(['q'=>$search,'status'=>$status,'page'=>request('page'),'user'=>$user->id])) }}">Gerenciar</a>
                            @if($user->active && $user->person?->isMinister())
                                <form method="POST" action="{{ route('admin.assistance.start') }}" data-confirm-title="Atuar como este usuário?" data-confirm-message="Você verá o sistema com a mesma visão e permissões desta conta. Sua identidade real continuará registrada na auditoria." data-confirm-label="Iniciar assistência">
                                    @csrf
                                    <input type="hidden" name="person_id" value="{{ $user->person->id }}">
                                    <input type="hidden" name="reason" value="Assistência iniciada em Usuários e acessos para apoio operacional/testes.">
                                    <button class="text-xs font-semibold text-amber-700 hover:underline">Atuar como usuário</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-sm text-slate-500">Nenhum usuário encontrado.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>

    <div>
        @if($selectedUser)
            @php($user = $selectedUser)
            <div class="sgml-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-bold">{{ $user->name }}</h2><div class="text-sm text-slate-500">{{ $user->email }}</div></div><span class="sgml-badge {{ $user->active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $user->active ? 'Ativo' : 'Inativo' }}</span></div>
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-5 space-y-4">@csrf @method('PATCH')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="sgml-label">Nome</label><input class="sgml-input" name="name" value="{{ old('name', $user->name) }}" required></div>
                        <div><label class="sgml-label">E-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                        <div><label class="sgml-label">Telefone / WhatsApp</label><input class="sgml-input" type="tel" data-br-phone name="phone" value="{{ old('phone', $user->person?->phone) }}" placeholder="(21) 99999-9999"></div>
                        <div><label class="sgml-label">Data de nascimento</label><input class="sgml-input" type="date" name="birth_date" value="{{ old('birth_date', $user->person?->birth_date?->format('Y-m-d')) }}"></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($user->active)> Conta ativa</label>
                    <div><label class="sgml-label">Cadastro pessoal relacionado</label><select class="sgml-input" name="person_id"><option value="">Manter/gerar cadastro pessoal automaticamente</option>@foreach($allPeople as $person)@php($linkedElsewhere = filled($person->user_id) && $person->user_id !== $user->id)<option value="{{ $person->id }}" @selected($user->person?->id === $person->id) @disabled($linkedElsewhere)>{{ $person->display_name }}{{ $linkedElsewhere ? ' · já vinculada' : '' }}</option>@endforeach</select><p class="sgml-help">Normalmente não é necessário alterar este vínculo. Use apenas para corrigir qual cadastro pessoal pertence à conta.</p></div>
                    <div><div class="sgml-label">Perfis de acesso</div><div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">@foreach($roles as $role)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))> {{ $role->name }}</label>@endforeach</div></div>
                    <button class="sgml-btn-primary">Salvar usuário</button>
                </form>

                <details class="mt-5 rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer font-semibold">Redefinir senha</summary><div class="mt-3 grid gap-3 md:grid-cols-2">
                    <form method="POST" action="{{ route('admin.users.password.reset', $user) }}" class="rounded-lg bg-slate-50 p-3">@csrf<input type="hidden" name="mode" value="manual"><label class="sgml-label">Senha temporária</label><input class="sgml-input" type="password" name="password" minlength="12" required><button class="mt-2 sgml-btn-secondary">Redefinir manualmente</button></form>
                    <form method="POST" action="{{ route('admin.users.password.reset', $user) }}" class="rounded-lg bg-slate-50 p-3">@csrf<input type="hidden" name="mode" value="generated_email"><div class="text-sm font-semibold">Gerar e enviar por e-mail</div><p class="mt-1 text-xs text-slate-500">Envia senha temporária para {{ $user->email }}.</p><button class="mt-2 sgml-btn-secondary" @disabled(! $smtpConfigured)>Gerar e enviar</button></form>
                </div></details>

                <details class="mt-5 rounded-2xl border border-slate-200 p-4"><summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><span class="font-black">Permissões específicas</span><p class="mt-1 text-xs text-slate-500">Exceções ao perfil normal. Use somente quando uma pessoa precisar de acesso diferente do grupo.</p></div><span class="sgml-badge-muted">Avançado</span></div></summary><div class="mt-4"><p class="mb-3 text-xs text-slate-500">Escolha a permissão pela descrição. Os códigos internos aparecem apenas como referência técnica secundária.</p>
                    @foreach($overrides as $override)<div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-xs"><span><strong>{{ $override->permission?->description ?? $override->permission?->name }}</strong><span class="font-bold {{ $override->allowed ? 'text-emerald-700' : 'text-rose-700' }}"> · {{ $override->allowed ? 'PERMITIR' : 'NEGAR' }}</span>@if($override->expires_at) · até {{ $override->expires_at->format('d/m/Y H:i') }}@endif<details class="mt-1"><summary class="cursor-pointer text-[10px] text-slate-400">Ver código técnico</summary><code class="mt-1 block text-[10px] text-slate-400">{{ $override->permission?->name }}</code></details></span><form method="POST" action="{{ route('admin.users.overrides.destroy', [$user, $override]) }}">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Remover</button></form></div>@endforeach
                    <form method="POST" action="{{ route('admin.users.overrides.store', $user) }}" class="mt-3 grid gap-2 md:grid-cols-4">@csrf<select class="sgml-input md:col-span-2" name="permission_id" required><option value="">Permissão</option>@foreach($permissionGroups as $module=>$group)<optgroup label="{{ ucfirst($module) }}">@foreach($group as $permission)<option value="{{ $permission->id }}">{{ $permission->description }}</option>@endforeach</optgroup>@endforeach</select><select class="sgml-input" name="allowed"><option value="1">Permitir</option><option value="0">Negar</option></select><input class="sgml-input" type="datetime-local" name="expires_at"><input class="sgml-input md:col-span-3" name="reason" placeholder="Motivo obrigatório" required><button class="sgml-btn-secondary">Aplicar ACL</button></form>
                </div></details>
            </div>
        @else
            <div class="sgml-card p-10 text-center text-slate-500">Selecione <strong>Gerenciar</strong> ao lado de um usuário para abrir os detalhes sem carregar todas as contas de uma vez.</div>
        @endif
    </div>
</div>

<details class="mt-8 sgml-card p-5"><summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-black">Perfis e matriz de permissões</h2><p class="mt-1 text-sm text-slate-500">Configuração avançada dos perfis de acesso do sistema.</p></div><span class="sgml-badge-muted">Avançado</span></div></summary><div class="mt-5 grid gap-4 lg:grid-cols-2">@foreach($roles as $role)<form method="POST" action="{{ route('admin.roles.update', $role) }}" class="rounded-xl border border-slate-200 p-4">@csrf @method('PATCH')<div class="font-bold">{{ $role->name }}</div>@foreach($permissionGroups as $module=>$group)<div class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $module }}</div><div class="mt-2 space-y-1">@foreach($group as $permission)<label class="flex items-start gap-2 text-xs"><input class="mt-1" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id',$permission->id))><span>{{ $permission->description }}<span class="block text-[10px] text-slate-400">Código: {{ $permission->name }}</span></span></label>@endforeach</div>@endforeach<button class="mt-4 sgml-btn-secondary">Salvar permissões</button></form>@endforeach</div>
<form method="POST" action="{{ route('admin.roles.store') }}" class="mt-6 rounded-xl border border-dashed border-slate-300 p-4">@csrf<div class="font-bold">Novo papel personalizado</div><input class="mt-3 sgml-input max-w-md" name="name" placeholder="Nome do papel" required><div class="mt-4 grid gap-4 md:grid-cols-3">@foreach($permissionGroups as $module=>$group)<div><div class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $module }}</div><div class="mt-2 space-y-1">@foreach($group as $permission)<label class="flex items-start gap-2 text-xs"><input class="mt-1" type="checkbox" name="permissions[]" value="{{ $permission->id }}"><span>{{ $permission->description }}<span class="block text-[10px] text-slate-400">Código: {{ $permission->name }}</span></span></label>@endforeach</div></div>@endforeach</div><button class="mt-4 sgml-btn-primary">Criar papel</button></form></details>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const person=document.getElementById('access-person'), name=document.getElementById('access-name'), email=document.getElementById('access-email'), phone=document.getElementById('access-phone'), birth=document.getElementById('access-birth'), manual=document.getElementById('manual-password-fields');
    const toggle=()=>{ const mode=document.querySelector('input[name="delivery_mode"]:checked')?.value||'manual'; manual?.classList.toggle('hidden',mode!=='manual'); const p=manual?.querySelector('input[name="password"]'); if(p)p.required=mode==='manual'; };
    document.querySelectorAll('input[name="delivery_mode"]').forEach(i=>i.addEventListener('change',toggle)); toggle();
    person?.addEventListener('change',()=>{ const o=person.options[person.selectedIndex]; if(!o?.value)return; if(!name.value||name.dataset.autofilled==='1'){name.value=o.dataset.name||'';name.dataset.autofilled='1';} if(!email.value||email.dataset.autofilled==='1'){email.value=o.dataset.email||'';email.dataset.autofilled='1';} if(phone) phone.value=o.dataset.phone||''; if(birth) birth.value=o.dataset.birth||''; });
});
</script>
@endsection
