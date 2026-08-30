@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.index') }}" class="text-sm font-semibold text-indigo-600">← Administração</a>
        <h1 class="mt-2 text-2xl font-black">SMTP e envio de e-mails</h1>
        <p class="text-sm text-slate-500">Configure o servidor de envio usado para recuperação de senha, testes e entrega de senhas temporárias.</p>
    </div>
    <span class="sgml-badge {{ $settings['enabled'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $settings['enabled'] ? 'SMTP habilitado' : 'SMTP desabilitado' }}</span>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('admin.mail.update') }}" class="sgml-card grid gap-4 p-5 md:grid-cols-2 xl:col-span-2">
        @csrf @method('PATCH')
        <label class="md:col-span-2 flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))> Habilitar envio SMTP para esta organização</label>
        <div><label class="sgml-label">Servidor SMTP</label><input class="sgml-input" name="host" value="{{ old('host', $settings['host']) }}" placeholder="smtp.seudominio.com.br" required></div>
        <div><label class="sgml-label">Porta</label><input class="sgml-input" type="number" min="1" max="65535" name="port" value="{{ old('port', $settings['port'] ?: 587) }}" required></div>
        <div><label class="sgml-label">Usuário</label><input class="sgml-input" name="username" value="{{ old('username', $settings['username']) }}" autocomplete="off"></div>
        <div><label class="sgml-label">Senha SMTP</label><input class="sgml-input" type="password" name="password" autocomplete="new-password" placeholder="{{ $settings['has_password'] ? '•••••••• (deixe em branco para manter)' : 'Informe a senha' }}"><p class="mt-1 text-xs text-slate-500">A senha é criptografada antes de ser armazenada.</p></div>
        <div><label class="sgml-label">Segurança</label><select class="sgml-input" name="security"><option value="smtp" @selected(old('security', $settings['security']) === 'smtp')>SMTP com STARTTLS automático (587 recomendado)</option><option value="smtps" @selected(old('security', $settings['security']) === 'smtps')>SMTPS/SSL (465)</option></select></div>
        <div><label class="sgml-label">Domínio EHLO (opcional)</label><input class="sgml-input" name="ehlo_domain" value="{{ old('ehlo_domain', $settings['ehlo_domain']) }}" placeholder="louvor.minhaigreja.org.br"></div>
        <div><label class="sgml-label">E-mail remetente</label><input class="sgml-input" type="email" name="from_address" value="{{ old('from_address', $settings['from_address']) }}" required></div>
        <div><label class="sgml-label">Nome do remetente</label><input class="sgml-input" name="from_name" value="{{ old('from_name', $settings['from_name'] ?: 'OurCloud Louvor') }}" required></div>
        <div class="md:col-span-2"><button class="sgml-btn-primary">Salvar SMTP</button></div>
    </form>

    <div class="space-y-4">
        <form method="POST" action="{{ route('admin.mail.test') }}" class="sgml-card p-5">
            @csrf
            <h2 class="font-bold">Testar envio</h2>
            <p class="mt-1 text-xs text-slate-500">Salve a configuração primeiro. O teste abre uma conexão real com o servidor SMTP.</p>
            <label class="sgml-label mt-4">Enviar teste para</label>
            <input class="sgml-input" type="email" name="recipient" value="{{ old('recipient', auth()->user()->email) }}" required>
            <button class="mt-3 sgml-btn-secondary">Enviar e-mail de teste</button>
        </form>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Boa prática</strong>
            <p class="mt-1 text-xs">Use uma conta exclusiva ou credencial de aplicação para o OurCloud Louvor. A senha SMTP nunca é exibida novamente na plataforma.</p>
        </div>
    </div>
</div>
@endsection
