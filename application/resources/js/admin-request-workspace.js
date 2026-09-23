const drawer = document.querySelector('[data-request-drawer]');
const focusStorageKey = 'easytire-admin-request-focus';

document.querySelectorAll('[data-request-open]').forEach((link) => {
    link.addEventListener('click', () => {
        window.sessionStorage.setItem(focusStorageKey, link.dataset.requestOpen || '');
    });
});

if (drawer) {
    const panel = drawer.querySelector('[data-request-drawer-panel]');
    const closeUrl = drawer.dataset.closeUrl;
    const forms = Array.from(drawer.querySelectorAll('form'));

    const hasUnsavedInput = () => forms.some((form) => Array.from(form.elements).some((element) => {
        if (!(element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement)) return false;
        if (element.type === 'hidden' || element.type === 'submit') return false;
        if (element instanceof HTMLInputElement && ['checkbox', 'radio'].includes(element.type)) return element.checked !== element.defaultChecked;
        if (element instanceof HTMLSelectElement) return Array.from(element.options).some((option) => option.selected !== option.defaultSelected);
        return element.value !== element.defaultValue;
    }));

    const close = () => {
        if (!closeUrl) return;
        if (hasUnsavedInput() && !window.confirm(drawer.dataset.unsavedMessage || '')) return;
        window.location.assign(closeUrl);
    };

    drawer.querySelectorAll('[data-request-drawer-close]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!hasUnsavedInput()) return;
            if (!window.confirm(drawer.dataset.unsavedMessage || '')) event.preventDefault();
        });
    });
    drawer.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab' || !panel) return;

        const focusable = Array.from(panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter((element) => element instanceof HTMLElement && element.getClientRects().length > 0);
        if (focusable.length === 0) {
            event.preventDefault();
            panel.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    const initialFocus = panel?.querySelector('[data-request-drawer-close]');
    if (initialFocus instanceof HTMLElement) initialFocus.focus();
    else panel?.focus();
} else {
    const requestId = window.sessionStorage.getItem(focusStorageKey);
    if (requestId) {
        window.sessionStorage.removeItem(focusStorageKey);
        document.querySelector(`[data-request-open="${CSS.escape(requestId)}"]`)?.focus();
    }
}

document.querySelectorAll('[data-status-quick-form], [data-status-full-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const status = form.querySelector('[name="status"]')?.value;
        if (!['closed', 'cancelled'].includes(status) || status === form.dataset.initialStatus) return;
        const template = document.documentElement.lang === 'ru'
            ? 'Подтвердите изменение заявки :reference. Закрытая или отменённая заявка исчезнет из активных рабочих очередей.'
            : 'Bekreft endringen av :reference. En lukket eller kansellert forespørsel forsvinner fra aktive arbeidskøer.';
        if (!window.confirm(template.replace(':reference', form.dataset.reference || ''))) event.preventDefault();
    });
});
