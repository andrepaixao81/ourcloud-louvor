import axios from 'axios';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Alpine = Alpine;
Livewire.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

window.addEventListener('load', () => {
    window.setTimeout(() => {
        const warning = document.querySelector('[data-repertoire-livewire-warning]');
        if (warning && !window.Livewire) warning.classList.remove('hidden');
    }, 1200);
});

const formatBrazilianPhone = (value) => {
    let digits = String(value || '').replace(/\D/g, '');
    if ((digits.length === 12 || digits.length === 13) && digits.startsWith('55')) digits = digits.slice(2);
    digits = digits.slice(0, 11);
    if (digits.length <= 2) return digits;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
};

document.addEventListener('input', (event) => {
    const input = event.target.closest?.('[data-br-phone]');
    if (!input) return;
    input.value = formatBrazilianPhone(input.value);
});

const pageLoader = document.querySelector('[data-page-loader]');
let loaderTimer = null;
const startPageLoader = () => {
    window.clearTimeout(loaderTimer);
    loaderTimer = window.setTimeout(() => pageLoader?.classList.add('active'), 180);
};
const stopPageLoader = () => {
    window.clearTimeout(loaderTimer);
    pageLoader?.classList.remove('active');
};
window.addEventListener('pageshow', stopPageLoader);

const isModifiedClick = (event) => event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (!link || isModifiedClick(event)) return;
    if (link.target === '_blank' || link.hasAttribute('download') || link.href.startsWith('mailto:') || link.href.startsWith('tel:')) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || url.hash && url.pathname === window.location.pathname && url.search === window.location.search) return;
    startPageLoader();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    const submitter = event.submitter;
    if (submitter instanceof HTMLButtonElement) {
        const label = submitter.dataset.loadingLabel || 'Processando...';
        submitter.dataset.originalLabel = submitter.innerHTML;
        submitter.disabled = true;
        submitter.textContent = label;
    }
    startPageLoader();
});

// Modal consistente para confirmações importantes. Use data-confirm-title/message no form ou botão.
const dialog = document.querySelector('[data-confirm-dialog]');
const dialogTitle = dialog?.querySelector('[data-confirm-dialog-title]');
const dialogMessage = dialog?.querySelector('[data-confirm-dialog-message]');
const dialogConfirm = dialog?.querySelector('[data-confirm-dialog-confirm]');
const dialogCancel = dialog?.querySelector('[data-confirm-dialog-cancel]');
const dialogReasonWrap = dialog?.querySelector('[data-confirm-dialog-reason-wrap]');
const dialogReason = dialog?.querySelector('[data-confirm-dialog-reason]');
const dialogReasonLabel = dialog?.querySelector('[data-confirm-dialog-reason-label]');
const dialogReasonHelp = dialog?.querySelector('[data-confirm-dialog-reason-help]');
let pendingConfirmation = null;
let pendingReasonMin = 0;

const closeConfirmDialog = () => {
    dialog?.classList.add('hidden');
    if (dialogReason) dialogReason.value = '';
    dialogReasonWrap?.classList.add('hidden');
    pendingConfirmation = null;
    pendingReasonMin = 0;
};

const openConfirmDialog = ({ title, message, confirmLabel = 'Confirmar', danger = false, reasonLabel = '', reasonMin = 0, callback }) => {
    if (!dialog || !dialogTitle || !dialogMessage || !dialogConfirm) {
        if (window.confirm(message)) callback();
        return;
    }
    dialogTitle.textContent = title || 'Confirmar ação';
    dialogMessage.textContent = message || 'Deseja continuar?';
    dialogConfirm.textContent = confirmLabel;
    dialogConfirm.className = danger ? 'sgml-btn-danger' : 'sgml-btn-primary';
    pendingConfirmation = callback;
    pendingReasonMin = Number(reasonMin || 0);
    if (dialogReasonWrap && dialogReason) {
        dialogReasonWrap.classList.toggle('hidden', !reasonLabel);
        if (dialogReasonLabel) dialogReasonLabel.textContent = reasonLabel || 'Motivo';
        if (dialogReasonHelp) dialogReasonHelp.textContent = pendingReasonMin > 0 ? `Informe pelo menos ${pendingReasonMin} caracteres.` : '';
    }
    dialog.classList.remove('hidden');
    window.setTimeout(() => (reasonLabel && dialogReason ? dialogReason : dialogConfirm).focus(), 20);
};

dialogCancel?.addEventListener('click', closeConfirmDialog);
dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) closeConfirmDialog();
});
dialogConfirm?.addEventListener('click', () => {
    const callback = pendingConfirmation;
    const reason = dialogReason?.value.trim() || '';
    if (pendingReasonMin > 0 && reason.length < pendingReasonMin) {
        dialogReason?.focus();
        dialogReason?.classList.add('ring-2', 'ring-rose-300');
        window.setTimeout(() => dialogReason?.classList.remove('ring-2', 'ring-rose-300'), 900);
        return;
    }
    closeConfirmDialog();
    callback?.(reason);
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && dialog && !dialog.classList.contains('hidden')) closeConfirmDialog();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') return;
    const message = form.dataset.confirmMessage;
    if (!message) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openConfirmDialog({
        title: form.dataset.confirmTitle || 'Confirmar ação',
        message,
        confirmLabel: form.dataset.confirmLabel || 'Confirmar',
        danger: form.dataset.confirmDanger === '1',
        reasonLabel: form.dataset.confirmReasonLabel || '',
        reasonMin: form.dataset.confirmReasonMin || 0,
        callback: (reason) => {
            const reasonFieldName = form.dataset.confirmReasonField || 'reason';
            if (form.dataset.confirmReasonLabel) {
                const reasonField = form.elements.namedItem(reasonFieldName);
                if (reasonField) reasonField.value = reason;
            }
            form.dataset.confirmed = '1';
            form.requestSubmit();
        },
    });
}, true);

// Toasts somem sozinhos, mas podem ser fechados manualmente.
document.querySelectorAll('[data-toast]').forEach((toast) => {
    const dismiss = () => {
        toast.classList.add('opacity-0', 'translate-y-2');
        window.setTimeout(() => toast.remove(), 220);
    };
    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', dismiss);
    window.setTimeout(dismiss, 5200);
});

// Copiar repertório ou outro conteúdo operacional com feedback no próprio botão.
document.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-copy-text]');
    if (!button) return;
    const text = button.dataset.copyText || '';
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        const original = button.textContent;
        button.textContent = 'Copiado ✓';
        window.setTimeout(() => { button.textContent = original; }, 1800);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
});

// No celular, Agenda abre em lista quando o usuário ainda não escolheu uma visão.
document.querySelectorAll('[data-calendar-auto-view]').forEach((element) => {
    if (window.innerWidth > 640 || element.dataset.explicitView === '1' || element.dataset.view === 'list') return;
    const target = element.dataset.listUrl;
    if (target) window.location.replace(target);
});

// Filtro visual auxiliar de cards/tabelas quando uma tela fornece data-client-search-item.
document.addEventListener('input', (event) => {
    const search = event.target.closest?.('[data-client-search]');
    if (!search) return;
    const targetSelector = search.dataset.clientSearch;
    const query = search.value.trim().toLocaleLowerCase('pt-BR');
    document.querySelectorAll(targetSelector).forEach((item) => {
        const haystack = (item.dataset.searchText || item.textContent || '').toLocaleLowerCase('pt-BR');
        item.classList.toggle('hidden', query !== '' && !haystack.includes(query));
    });
});

const mobileMenu = document.querySelector('[data-mobile-menu]');
const setMobileMenu = (open) => {
    if (!mobileMenu) return;
    mobileMenu.classList.toggle('hidden', !open);
    document.body.classList.toggle('overflow-hidden', open);
};
document.querySelectorAll('[data-mobile-menu-open]').forEach((button) => button.addEventListener('click', () => setMobileMenu(true)));
document.querySelectorAll('[data-mobile-menu-close]').forEach((button) => button.addEventListener('click', () => setMobileMenu(false)));
mobileMenu?.addEventListener('click', (event) => { if (event.target === mobileMenu) setMobileMenu(false); });
