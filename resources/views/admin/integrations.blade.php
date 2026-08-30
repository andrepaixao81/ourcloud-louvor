@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Google Agenda</h1>
    <p class="text-sm text-slate-500">Integração opcional. O SGML continua sendo a fonte da verdade; as credenciais OAuth podem ser administradas pela própria plataforma.</p>
</div>

<div class="mb-6 sgml-card p-5">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-black">Configuração OAuth e calendário</h2>
            <p class="text-xs text-slate-500">Use Client ID e Client Secret de um aplicativo Web no Google Cloud. O Client Secret fica criptografado no banco.</p>
        </div>
        <span class="sgml-badge {{ $googleSettings['enabled'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $googleSettings['enabled'] ? 'Habilitado' : 'Desabilitado' }}</span>
    </div>

    <form method="POST" action="{{ route('integrations.google.settings.update') }}" class="mt-5 grid gap-3 md:grid-cols-2">
        @csrf @method('PATCH')
        <label class="md:col-span-2 flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm"><input type="checkbox" name="enabled" value="1" @checked($googleSettings['enabled'])> Habilitar integração Google Agenda</label>
        <div><label class="sgml-label">Client ID</label><input class="sgml-input" name="client_id" value="{{ $googleSettings['client_id'] }}" autocomplete="off" placeholder="...apps.googleusercontent.com"></div>
        <div><label class="sgml-label">Client Secret</label><input class="sgml-input" type="password" name="client_secret" autocomplete="new-password" placeholder="{{ $googleSettings['has_client_secret'] ? 'Já configurado - deixe vazio para manter' : 'Informe o Client Secret' }}"></div>
        <div class="md:col-span-2"><label class="sgml-label">URI de redirecionamento OAuth</label><input class="sgml-input" name="redirect_uri" value="{{ $googleSettings['redirect_uri'] ?: $recommendedRedirect }}" required><p class="mt-1 text-xs text-slate-500">Cadastre exatamente este endereço como URI autorizada no aplicativo OAuth do Google.</p></div>
        <div class="md:col-span-2"><label class="sgml-label">ID/endereço do calendário</label><input class="sgml-input" name="calendar_id" value="{{ $googleSettings['calendar_id'] ?: 'primary' }}" required><p class="mt-1 text-xs text-slate-500">Pode ser <code>primary</code> ou o ID/endereço do calendário de destino ao qual a conta conectada tenha acesso de escrita.</p></div>
        <div class="md:col-span-2 flex flex-wrap gap-2"><button class="sgml-btn-primary">Salvar configuração</button><span class="self-center text-xs text-slate-400">Origem atual: {{ $googleSettings['source']==='platform' ? 'plataforma' : 'compatibilidade com .env' }}</span></div>
    </form>
</div>

@if(!$googleSettings['enabled'])
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Habilite e salve as credenciais acima para iniciar a autorização OAuth.</div>
@endif

<div class="mb-6 flex flex-wrap gap-3">
    <form method="POST" action="{{ route('integrations.google.connect') }}">@csrf<input type="hidden" name="type" value="organization"><button class="sgml-btn-primary" @disabled(!$googleSettings['enabled'])>Conectar calendário institucional</button></form>
    <form method="POST" action="{{ route('integrations.google.connect') }}">@csrf<input type="hidden" name="type" value="personal"><button class="sgml-btn-secondary" @disabled(!$googleSettings['enabled'])>Conectar calendário pessoal</button></form>
</div>

<div class="space-y-3">
@forelse($integrations as $integration)
    <div class="sgml-card flex flex-wrap items-center justify-between gap-3 p-5"><div><div class="font-bold">Google · {{ $integration->integration_type }}</div><div class="text-sm text-slate-500">Calendário: {{ $integration->external_calendar_id ?: 'primary' }} · Status: {{ $integration->status }} · Última sincronização: {{ $integration->last_sync_at?->format('d/m/Y H:i') ?? 'nunca' }}</div></div>@if($integration->status!=='revoked')<form method="POST" action="{{ route('integrations.google.disconnect',$integration) }}">@csrf<button class="sgml-btn-danger">Desconectar</button></form>@endif</div>
@empty
    <div class="sgml-card p-10 text-center text-slate-500">Nenhuma integração Google conectada.</div>
@endforelse
</div>
@endsection
