@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <div class="sgml-page-header">
        <div><div class="sgml-page-kicker">Perfil</div><h1 class="sgml-page-title">Minha conta</h1><p class="sgml-page-description">Mantenha seus dados atualizados para receber avisos e aparecer corretamente na agenda do ministério.</p></div>
    </div>

    <section class="sgml-card mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-5 sm:p-6">
            <div class="flex items-center gap-4"><div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-xl font-black text-indigo-700 shadow-sm">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</div><div class="min-w-0"><h2 class="truncate text-xl font-black">{{ $user->name }}</h2><div class="mt-1 text-sm text-slate-600">{{ $user->roles->pluck('name')->join(', ') ?: 'Sem papel operacional' }}</div></div></div>
        </div>
        <div class="grid gap-px bg-slate-100 sm:grid-cols-3"><div class="bg-white p-4"><div class="text-[11px] font-black uppercase tracking-wider text-slate-400">E-mail</div><div class="mt-1 truncate font-bold">{{ $user->email }}</div></div><div class="bg-white p-4"><div class="text-[11px] font-black uppercase tracking-wider text-slate-400">Telefone / WhatsApp</div><div class="mt-1 font-bold">{{ $phoneDisplay ?: 'Não informado' }}</div></div><div class="bg-white p-4"><div class="text-[11px] font-black uppercase tracking-wider text-slate-400">Nascimento</div><div class="mt-1 font-bold">{{ $birthDateDisplay ?: 'Não informado' }}</div></div></div>
    </section>

    @if($user->isBasicMemberOnly())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"><div class="flex gap-2"><x-icon name="info" class="h-5 w-5 shrink-0 text-slate-500"/><span>Seu perfil principal é <strong>Membro</strong>. A administração pode liberar módulos específicos quando necessário, sem alterar seu papel principal.</span></div></div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="sgml-card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3"><div><div class="flex items-center gap-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="bell" class="h-4 w-4"/></span><h2 class="font-black">Telefone / WhatsApp</h2></div><p class="mt-2 text-sm text-slate-500">Usado nos avisos do ministério quando o WhatsApp estiver habilitado.</p></div>@if($user->person?->phone)<span class="sgml-badge-success">Cadastrado</span>@else<span class="sgml-badge-warning">Pendente</span>@endif</div>
            @if($user->person)<form method="POST" action="{{ route('account.phone.update') }}" class="mt-4 space-y-3">@csrf @method('PATCH')<div><label class="sgml-label">DDD + número do telefone</label><input class="sgml-input" type="tel" name="phone" inputmode="tel" autocomplete="tel" data-br-phone value="{{ old('phone',$phoneDisplay) }}" placeholder="(21) 99999-9999" maxlength="16"><p class="sgml-help">Você pode colar com +55; o sistema normaliza automaticamente.</p></div><button class="sgml-btn-primary" data-loading-label="Salvando...">Salvar telefone</button></form>@else<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Sua conta ainda não está vinculada a uma pessoa. Peça ao Administrador para concluir o vínculo.</div>@endif
        </section>

        <section class="sgml-card p-5 sm:p-6">
            <div class="flex items-center gap-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><x-icon name="cake" class="h-4 w-4"/></span><h2 class="font-black">Data de nascimento</h2></div><p class="mt-2 text-sm text-slate-500">Usada nos aniversariantes e na felicitação automática. Outros usuários autorizados veem apenas dia e mês; o ano e a idade não são exibidos.</p>
            @if($user->person)<form method="POST" action="{{ route('account.birth-date.update') }}" class="mt-4 space-y-3">@csrf @method('PATCH')<div><label class="sgml-label">Data de nascimento</label><input class="sgml-input" type="date" name="birth_date" value="{{ old('birth_date',$user->person?->birth_date?->format('Y-m-d')) }}"></div><button class="sgml-btn-primary" data-loading-label="Salvando...">Salvar nascimento</button></form>@else<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Sua conta precisa estar vinculada a uma pessoa.</div>@endif
        </section>

        <section class="sgml-card p-5 sm:p-6">
            <div class="flex items-center gap-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><x-icon name="user" class="h-4 w-4"/></span><h2 class="font-black">E-mail de acesso</h2></div><p class="mt-2 text-sm text-slate-500">O novo e-mail passa a valer no próximo login. Confirme com sua senha atual.</p>
            <form method="POST" action="{{ route('account.email.update') }}" class="mt-4 space-y-3">@csrf @method('PATCH')<div><label class="sgml-label">Novo e-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email',$user->email) }}" required></div><div><label class="sgml-label">Senha atual</label><input class="sgml-input" type="password" name="current_password" autocomplete="current-password" required></div><button class="sgml-btn-primary">Atualizar e-mail</button></form>
        </section>

        <section class="sgml-card p-5 sm:p-6">
            <div class="flex items-center gap-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-icon name="shield" class="h-4 w-4"/></span><h2 class="font-black">Senha</h2></div><p class="mt-2 text-sm text-slate-500">Use pelo menos 12 caracteres e uma senha diferente da atual.</p>
            <form method="POST" action="{{ route('account.password.update') }}" class="mt-4 space-y-3">@csrf @method('PATCH')<div><label class="sgml-label">Senha atual</label><input class="sgml-input" type="password" name="current_password" autocomplete="current-password" required></div><div class="grid gap-3 sm:grid-cols-2"><div><label class="sgml-label">Nova senha</label><input class="sgml-input" type="password" name="password" minlength="12" autocomplete="new-password" required></div><div><label class="sgml-label">Confirmar</label><input class="sgml-input" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></div></div><button class="sgml-btn-primary">Alterar senha</button></form>
        </section>
    </div>

    <details class="sgml-card mt-5 p-5 sm:p-6"><summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h2 class="font-black">Preciso redefinir minha senha por e-mail</h2><p class="mt-1 text-sm text-slate-500">Envia um link de redefinição para {{ $user->email }}.</p></div><span class="sgml-badge-muted">Opção alternativa</span></div></summary><form method="POST" action="{{ route('account.password.reset-email') }}" class="mt-4 border-t border-slate-100 pt-4" data-confirm-title="Enviar link de redefinição?" data-confirm-message="Enviaremos um link para {{ $user->email }} se o SMTP da organização estiver configurado." data-confirm-label="Enviar link">@csrf<button class="sgml-btn-secondary">Enviar link de redefinição</button></form></details>
</div>
@endsection
