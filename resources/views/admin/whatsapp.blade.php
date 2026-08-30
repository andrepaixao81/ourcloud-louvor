@extends('layouts.app')
@section('content')
<div class="mb-6">
    <a class="text-sm font-semibold text-indigo-600" href="{{ route('admin.index') }}">← Administração</a>
    <h1 class="mt-2 text-2xl font-black">Avisos pelo WhatsApp</h1>
    <p class="text-sm text-slate-500">Escolha o provedor de envio. O SGML mantém os avisos internos mesmo quando o WhatsApp estiver indisponível.</p>
    <div class="mt-3 flex flex-wrap gap-2"><span class="sgml-badge {{ $setting->isConfigured() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $setting->isConfigured() ? 'Credenciais salvas' : 'Configuração incompleta' }}</span>@if(($setting->provider ?? null)==='evolution' && filled($setting->evolution_api_key))<span class="sgml-badge bg-sky-50 text-sky-700">API Key preservada</span>@endif</div>
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
                    <label class="sgml-label">Atraso da Evolution (ms)</label>
                    <input class="sgml-input" type="number" min="0" max="10000" step="100" name="evolution_delay_ms" value="{{ $setting->evolution_delay_ms ?? 500 }}"><p class="mt-1 text-xs text-slate-500">Em lotes, o SGML aplica no mínimo 2 segundos entre requisições para reduzir erros de conexão.</p>
                </div>
                <label class="flex items-center gap-2 self-end pb-3 font-semibold">
                    <input type="checkbox" name="evolution_link_preview" value="1" @checked($setting->evolution_link_preview ?? true)>
                    Tentar prévia dos links
                </label>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                O SGML envia texto simples para a Evolution API contendo título, resumo e link do sistema. A API Key já salva não precisa ser digitada novamente. Se aparecer <strong>Connection Closed</strong>, normalmente a instância perdeu a sessão ou recebeu um lote rápido demais: teste a conexão e, após reconectar a instância, escolha entre Reenviar falhas recentes ou Remover falhas/cancelados.
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
            <p class="mt-1 text-sm text-slate-500">Salve a configuração antes de testar. O teste é enviado imediatamente e não depende da fila.</p>
            <div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-xl bg-slate-50 p-3"><div class="text-xl font-black">{{ $queuedWhatsapp ?? 0 }}</div><div class="text-xs text-slate-500">avisos pendentes</div></div><div class="rounded-xl bg-slate-50 p-3"><div class="text-xl font-black">{{ $queueJobs ?? 0 }}</div><div class="text-xs text-slate-500">jobs WhatsApp</div></div></div>
            @if($queueMayBeStopped ?? false)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><strong>A fila parece parada.</strong> Existe aviso aguardando há mais de 2 minutos. Limpe a fila para cancelar pendências e, no servidor, confirme se <code>sgml-queue.service</code> está ativo.</div>
            @endif
            <form method="POST" action="{{ route('admin.whatsapp.check') }}" class="mt-4">
                @csrf
                <button class="sgml-btn-secondary w-full">Testar conexão</button>
            </form>
            @if(($failedRecent ?? 0) > 0)
            <form method="POST" action="{{ route('admin.whatsapp.retry-failed') }}" class="mt-3" data-confirm-title="Reenviar falhas recentes?" data-confirm-message="Os envios com falha das últimas 24 horas voltarão para a fila com espaçamento seguro." data-confirm-label="Reenviar">
                @csrf
                <button class="sgml-btn-secondary w-full">Reenviar {{ $failedRecent }} falha(s) recente(s)</button>
            </form>
            @endif
            @if(($discardableUnsent ?? 0) > 0)
            <form method="POST" action="{{ route('admin.whatsapp.purge-unsent') }}" class="mt-3" data-confirm-title="Remover falhas e cancelados?" data-confirm-message="Os registros que não foram entregues serão removidos do histórico sem qualquer reenvio. Mensagens ENVIADAS serão preservadas." data-confirm-danger="1" data-confirm-label="Remover registros">
                @csrf
                @method('DELETE')
                <input type="hidden" name="confirmation" value="REMOVER NAO ENVIADOS">
                <button class="sgml-btn-danger w-full">Remover {{ $discardableUnsent }} falha(s)/cancelado(s)</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">Use esta opção quando não quiser reenviar. Ela limpa apenas falhas, cancelados e ignorados; o histórico ENVIADO permanece.</p>
            @endif
            @if(($queuedWhatsapp ?? 0) > 0 || ($queueJobs ?? 0) > 0)
            <form method="POST" action="{{ route('admin.whatsapp.clear-queue') }}" class="mt-3" data-confirm-title="Limpar fila do WhatsApp?" data-confirm-message="Os avisos ainda pendentes serão cancelados. Mensagens já enviadas e o histórico de falhas serão preservados." data-confirm-danger="1" data-confirm-label="Limpar fila">
                @csrf
                <input type="hidden" name="confirmation" value="LIMPAR FILA">
                <button class="sgml-btn-danger w-full">Limpar fila pendente</button>
            </form>
            @endif
        </div>

        <div class="sgml-card p-6">
            <h2 class="font-black">Enviar mensagem de teste</h2>
            <p class="mt-1 text-xs text-slate-500">O teste é síncrono: você recebe sucesso ou erro do provedor na mesma tela, sem aguardar o worker.</p>
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
                <td class="p-3 font-semibold">@php($statusLabel = ['queued'=>'NA FILA','sending'=>'ENVIANDO','retrying'=>'TENTANDO NOVAMENTE','sent'=>'ENVIADO','failed'=>'FALHOU','skipped'=>'IGNORADO','cancelled'=>'CANCELADO'][$log->status] ?? strtoupper($log->status)){{ $statusLabel }}</td>
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
