@extends('layouts.app')
@section('content')
<div class="mb-6">
    <a class="text-sm font-semibold text-indigo-600" href="{{ route('admin.index') }}">← Administração</a>
    <h1 class="mt-2 text-2xl font-black">Avisos pelo WhatsApp</h1>
    <p class="text-sm text-slate-500">Escolha o provedor de envio. O SGML mantém os avisos internos mesmo quando o WhatsApp estiver desabilitado.</p>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('admin.whatsapp.update') }}" class="sgml-card grid gap-4 p-6 lg:col-span-2" id="whatsapp-settings-form">
        @csrf
        @method('PATCH')

        <label class="flex items-center gap-2 font-semibold">
            <input type="checkbox" name="enabled" value="1" @checked($setting->enabled)>
            Habilitar avisos automáticos pelo WhatsApp
        </label>

        <div>
            <label class="sgml-label">Provedor</label>
            <select class="sgml-input" name="provider" id="whatsapp-provider" required>
                <option value="evolution" @selected(($setting->provider ?? 'meta') === 'evolution')>Evolution API</option>
                <option value="meta" @selected(($setting->provider ?? 'meta') === 'meta')>Meta WhatsApp Cloud API</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">A troca de provedor não apaga as credenciais do outro. Os segredos salvos nunca são exibidos novamente.</p>
        </div>

        <section id="provider-evolution" class="space-y-4 rounded-xl border border-slate-200 p-4">
            <div>
                <h2 class="font-black">Evolution API</h2>
                <p class="text-sm text-slate-500">Use uma instância já criada e conectada na sua Evolution API.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="sgml-label">URL da Evolution API</label>
                    <input class="sgml-input" type="url" name="evolution_base_url" value="{{ $setting->evolution_base_url }}" placeholder="https://whatsapp.exemplo.com.br">
                    <p class="mt-1 text-xs text-slate-500">Informe somente a URL base, sem /manager e sem /message/sendText.</p>
                </div>
                <div>
                    <label class="sgml-label">Instância</label>
                    <input class="sgml-input" name="evolution_instance" value="{{ $setting->evolution_instance }}" placeholder="igreja-principal">
                </div>
                <div>
                    <label class="sgml-label">API Key <span class="font-normal text-slate-400">(vazio mantém a atual)</span></label>
                    <input class="sgml-input" type="password" name="evolution_api_key" autocomplete="new-password" placeholder="••••••••••••">
                </div>
                <div>
                    <label class="sgml-label">Atraso antes do envio (ms)</label>
                    <input class="sgml-input" type="number" min="0" max="10000" step="100" name="evolution_delay_ms" value="{{ $setting->evolution_delay_ms ?? 500 }}">
                </div>
                <label class="flex items-center gap-2 self-end pb-3 font-semibold">
                    <input type="checkbox" name="evolution_link_preview" value="1" @checked($setting->evolution_link_preview ?? true)>
                    Tentar prévia dos links
                </label>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                O SGML envia texto simples para a Evolution API contendo título, resumo e link do sistema. O destinatário deve possuir telefone válido cadastrado no SGML.
            </div>
        </section>

        <section id="provider-meta" class="space-y-4 rounded-xl border border-slate-200 p-4">
            <div>
                <h2 class="font-black">Meta WhatsApp Cloud API</h2>
                <p class="text-sm text-slate-500">Mantém a integração oficial já existente no SGML.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="sgml-label">Versão Graph API</label><input class="sgml-input" name="graph_version" value="{{ $setting->graph_version ?: 'v26.0' }}"></div>
                <div><label class="sgml-label">Phone Number ID</label><input class="sgml-input" name="phone_number_id" value="{{ $setting->phone_number_id }}"></div>
                <div class="sm:col-span-2"><label class="sgml-label">Access Token <span class="font-normal text-slate-400">(vazio mantém o atual)</span></label><input class="sgml-input" type="password" name="access_token" autocomplete="new-password"></div>
                <div><label class="sgml-label">Template aprovado</label><input class="sgml-input" name="template_name" value="{{ $setting->template_name ?: 'sgml_aviso' }}"></div>
                <div><label class="sgml-label">Idioma do template</label><input class="sgml-input" name="language_code" value="{{ $setting->language_code ?: 'pt_BR' }}"></div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                O template deve possuir três variáveis no corpo: <strong>título</strong>, <strong>resumo</strong> e <strong>link do SGML</strong>.
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button class="sgml-btn-primary">Salvar configuração</button>
        </div>
    </form>

    <div class="space-y-4">
        <div class="sgml-card p-6">
            <h2 class="font-black">Diagnóstico</h2>
            <p class="mt-1 text-sm text-slate-500">Salve a configuração antes de testar.</p>
            <form method="POST" action="{{ route('admin.whatsapp.check') }}" class="mt-4">
                @csrf
                <button class="sgml-btn-secondary w-full">Testar conexão</button>
            </form>
        </div>

        <div class="sgml-card p-6">
            <h2 class="font-black">Enviar mensagem de teste</h2>
            <form method="POST" action="{{ route('admin.whatsapp.test') }}" class="mt-4 space-y-3">
                @csrf
                <div><label class="sgml-label">Telefone</label><input class="sgml-input" name="phone" placeholder="21999999999" required></div>
                <p class="text-xs text-slate-500">Para número brasileiro, pode informar DDD + telefone. O SGML acrescenta o DDI 55.</p>
                <button class="sgml-btn-secondary w-full">Enviar teste</button>
            </form>
        </div>
    </div>
</div>

<div class="sgml-card mt-5 overflow-x-auto p-0">
    <div class="border-b border-slate-200 p-5"><h2 class="font-black">Últimos envios</h2></div>
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th class="p-3">Data</th><th class="p-3">Telefone</th><th class="p-3">Provedor</th><th class="p-3">Aviso</th><th class="p-3">Status</th><th class="p-3">Erro</th></tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr class="border-t border-slate-100">
                <td class="p-3">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                <td class="p-3">{{ $log->phone }}</td>
                <td class="p-3">{{ ($log->provider ?? $setting->provider) === 'evolution' ? 'Evolution' : 'Meta' }}</td>
                <td class="p-3">{{ $log->title }}</td>
                <td class="p-3 font-semibold">{{ strtoupper($log->status) }}</td>
                <td class="max-w-lg p-3 text-xs text-rose-700">{{ $log->error }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-8 text-center text-slate-500">Nenhum envio registrado.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>
(function () {
    const provider = document.getElementById('whatsapp-provider');
    const evolution = document.getElementById('provider-evolution');
    const meta = document.getElementById('provider-meta');
    if (!provider || !evolution || !meta) return;

    function syncProviderPanels() {
        const isEvolution = provider.value === 'evolution';
        evolution.hidden = !isEvolution;
        meta.hidden = isEvolution;
    }

    provider.addEventListener('change', syncProviderPanels);
    syncProviderPanels();
})();
</script>
@endsection
