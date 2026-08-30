@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-black">Sincronizar agenda</h1>
        <p class="text-sm text-slate-500">Assine uma vez e mantenha no celular a agenda publicada do OurCloud Louvor. Não é necessária conta Google.</p>
    </div>
    @if(auth()->user()->hasPermission('calendar.view'))
        <a class="sgml-btn-secondary" href="{{ route('calendar.index') }}">Abrir Agenda do Ministério</a>
    @endif
</div>

@if($assistedPerson)
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Assistência ao ministro ativa.</strong> Os links abaixo pertencem ao seu usuário real, não ao ministro assistido. Isso evita vincular por engano a agenda pessoal de outra pessoa.
    </div>
@endif

<div id="calendar-subscribe-status" class="mb-5 hidden rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900" role="status" aria-live="polite"></div>

<div class="grid gap-5 lg:grid-cols-2">
    <section class="sgml-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="sgml-badge bg-indigo-50 text-indigo-700">TODOS OS USUÁRIOS</span>
                <h2 class="mt-3 text-lg font-black">Agenda do Ministério</h2>
                <p class="mt-1 text-sm text-slate-500">Contém todos os cultos da escala publicada do ministério e os programações criadas na Agenda do Ministério, com ministro, horário, repertório, tons, notas e observações quando aplicável.</p>
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800">
            O endereço permanece o mesmo. Publicação, troca de ministro, alteração de repertório, cancelamento ou nova escala passam a valer no mesmo feed automaticamente.
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="sgml-btn-primary" data-calendar-subscribe data-webcal-url="{{ $teamWebcalUrl }}" data-https-url="{{ $teamFeedUrl }}">Assinar agenda do sistema</button>
            <a class="sgml-btn-secondary" href="{{ $teamFeedUrl }}">Abrir ICS</a>
            <button type="button" class="sgml-btn-secondary" data-copy-calendar-url="{{ $teamFeedUrl }}">Copiar link HTTPS</button>
            <noscript><a class="sgml-btn-primary" href="{{ $teamWebcalUrl }}">Assinar agenda do sistema</a></noscript>
        </div>
        <div class="mt-3 break-all rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $teamFeedUrl }}</div>
    </section>

    @if($isMinister && ! $assistedPerson)
        <section class="sgml-card p-5">
            <div>
                <span class="sgml-badge bg-violet-50 text-violet-700">MINISTRO</span>
                <h2 class="mt-3 text-lg font-black">Minha agenda ministerial</h2>
                <p class="mt-1 text-sm text-slate-500">Mostra somente os cultos em que você está escalado como ministro principal ou adicional.</p>
            </div>
            <div class="mt-4 rounded-xl border border-violet-100 bg-violet-50/60 p-3 text-sm text-violet-800">
                Você pode manter as duas assinaturas ao mesmo tempo: sua agenda pessoal ministerial e a agenda completa do sistema.
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="sgml-btn-primary" data-calendar-subscribe data-webcal-url="{{ $personalWebcalUrl }}" data-https-url="{{ $personalFeedUrl }}">Assinar minha agenda</button>
                <a class="sgml-btn-secondary" href="{{ $personalFeedUrl }}">Abrir ICS</a>
                <button type="button" class="sgml-btn-secondary" data-copy-calendar-url="{{ $personalFeedUrl }}">Copiar link HTTPS</button>
                <noscript><a class="sgml-btn-primary" href="{{ $personalWebcalUrl }}">Assinar minha agenda</a></noscript>
            </div>
            <div class="mt-3 break-all rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $personalFeedUrl }}</div>
        </section>
    @endif
</div>

<section class="mt-5 sgml-card p-5">
    <h2 class="text-lg font-black">Como funciona a atualização</h2>
    <div class="mt-3 grid gap-3 text-sm text-slate-600 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 p-4"><strong class="text-slate-900">1. Assine uma única vez</strong><p class="mt-1">O botão tenta abrir a assinatura WebCal. Se o aparelho não tiver um aplicativo compatível, o SGML tenta o link HTTPS como alternativa.</p></div>
        <div class="rounded-xl border border-slate-200 p-4"><strong class="text-slate-900">2. O SGML atualiza o feed</strong><p class="mt-1">As alterações passam a constar imediatamente no endereço da assinatura, sem gerar um novo link a cada escala.</p></div>
        <div class="rounded-xl border border-slate-200 p-4"><strong class="text-slate-900">3. O celular sincroniza</strong><p class="mt-1">Para receber alterações futuras automaticamente, o aplicativo escolhido precisa oferecer assinatura de calendário por URL/WebCal. Apenas importar um arquivo ICS cria uma cópia estática.</p></div>
    </div>

    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Sem Google também funciona:</strong> o feed é padrão ICS/WebCal do próprio SGML. A conta Google não é obrigatória. O suporte à atualização automática depende apenas de o aplicativo de calendário do aparelho aceitar calendários assinados por URL.
    </div>

    <details class="mt-5 rounded-xl border border-slate-200 p-4">
        <summary class="cursor-pointer font-bold text-slate-700">Segurança: regenerar links de assinatura</summary>
        <p class="mt-2 text-sm text-slate-500">Use somente se um link tiver sido compartilhado indevidamente. As assinaturas anteriores deixam de funcionar e precisarão ser adicionadas novamente.</p>
        <form method="POST" action="{{ route('calendar.feed.regenerate') }}" class="mt-3" data-confirm-message="Regenerar os links? As assinaturas antigas deixarão de funcionar.">
            @csrf
            <button class="sgml-btn-danger">Regenerar links</button>
        </form>
    </details>
</section>

<script>
(() => {
    const statusBox = document.getElementById('calendar-subscribe-status');

    const showStatus = (message, tone = 'info') => {
        if (!statusBox) return;
        statusBox.classList.remove('hidden', 'border-indigo-200', 'bg-indigo-50', 'text-indigo-900', 'border-amber-200', 'bg-amber-50', 'text-amber-900');
        if (tone === 'warning') {
            statusBox.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-900');
        } else {
            statusBox.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-900');
        }
        statusBox.textContent = message;
        statusBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const startSubscription = (button) => {
        const webcalUrl = button.dataset.webcalUrl || '';
        const httpsUrl = button.dataset.httpsUrl || '';
        if (!webcalUrl || !httpsUrl) {
            showStatus('Não foi possível montar o endereço da agenda. Atualize a página e tente novamente.', 'warning');
            return;
        }

        let handedOff = false;
        const markHandedOff = () => { handedOff = true; };
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) markHandedOff();
        }, { once: true });
        window.addEventListener('pagehide', markHandedOff, { once: true });
        window.addEventListener('blur', markHandedOff, { once: true });

        showStatus('Abrindo o aplicativo de calendário para assinar a agenda…');

        try {
            window.location.href = webcalUrl;
        } catch (_) {
            handedOff = false;
        }

        window.setTimeout(() => {
            if (handedOff || document.hidden) return;
            showStatus('O aparelho não respondeu ao WebCal. Abrindo o link ICS pelo HTTPS como alternativa. Se o aplicativo oferecer “assinar por URL”, escolha essa opção para manter a atualização automática.', 'warning');
            window.location.href = httpsUrl;
        }, 1400);
    };

    document.addEventListener('click', async (event) => {
        const subscribeButton = event.target.closest('[data-calendar-subscribe]');
        if (subscribeButton) {
            event.preventDefault();
            startSubscription(subscribeButton);
            return;
        }

        const copyButton = event.target.closest('[data-copy-calendar-url]');
        if (!copyButton) return;
        const value = copyButton.dataset.copyCalendarUrl || '';
        try {
            await navigator.clipboard.writeText(value);
            const original = copyButton.textContent;
            copyButton.textContent = 'Link copiado';
            window.setTimeout(() => { copyButton.textContent = original; }, 1600);
        } catch (_) {
            const area = document.createElement('textarea');
            area.value = value;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            const original = copyButton.textContent;
            copyButton.textContent = 'Link copiado';
            window.setTimeout(() => { copyButton.textContent = original; }, 1600);
        }
    });
})();
</script>
@endsection
