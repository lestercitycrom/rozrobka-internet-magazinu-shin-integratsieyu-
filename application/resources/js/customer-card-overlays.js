const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const visibleFocusable = (overlay) => Array.from(overlay.querySelectorAll(focusableSelector)).filter((element) => {
    if (!(element instanceof HTMLElement) || element.hidden) return false;
    const bounds = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);
    return bounds.width > 0 && bounds.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
});

let activeController = null;

const initCardOverlay = (root) => {
    const opener = root.querySelector('[data-card-overlay-open]');
    const overlayId = opener?.getAttribute('aria-controls');
    const overlay = overlayId ? root.querySelector(`#${CSS.escape(overlayId)}`) : null;
    const closer = overlay?.querySelector('[data-card-overlay-close]');
    const fallback = root.querySelector('[data-card-overlay-fallback]');

    if (typeof HTMLDialogElement === 'undefined'
        || !(opener instanceof HTMLButtonElement)
        || !(overlay instanceof HTMLDialogElement)
        || !(closer instanceof HTMLButtonElement)) return;

    let returnFocus = null;
    let inertSnapshots = [];

    const notify = (open) => {
        window.dispatchEvent(new CustomEvent('et:card-overlay-statechange', {
            detail: { open, root },
        }));
    };

    const setUnderlyingContentInert = (inert) => {
        if (inert) {
            inertSnapshots = Array.from(root.children)
                .filter((child) => child !== overlay)
                .map((child) => ({ child, inert: child.inert }));
            inertSnapshots.forEach(({ child }) => { child.inert = true; });
            return;
        }

        inertSnapshots.forEach(({ child, inert: previous }) => {
            if (child.isConnected) child.inert = previous;
        });
        inertSnapshots = [];
    };

    const finishClose = () => {
        root.classList.remove('is-card-overlay-open');
        opener.setAttribute('aria-expanded', 'false');
        setUnderlyingContentInert(false);
        if (activeController?.overlay === overlay) activeController = null;
        notify(false);

        if (returnFocus instanceof HTMLElement && returnFocus.isConnected) returnFocus.focus();
        returnFocus = null;
    };

    const close = ({ restoreFocus = true } = {}) => {
        if (!overlay.open) return;
        if (!restoreFocus) returnFocus = null;
        overlay.close();
    };

    const open = () => {
        if (overlay.open) return;
        if (activeController?.overlay.open) activeController.close({ restoreFocus: false });

        returnFocus = opener;
        setUnderlyingContentInert(true);
        overlay.show();
        activeController = { overlay, close };
        root.classList.add('is-card-overlay-open');
        opener.setAttribute('aria-expanded', 'true');
        notify(true);
        closer.focus();
    };

    opener.addEventListener('click', open);
    closer.addEventListener('click', () => close());
    overlay.addEventListener('close', finishClose);
    overlay.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;
        const focusable = visibleFocusable(overlay);
        if (focusable.length === 0) {
            event.preventDefault();
            overlay.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === overlay)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    opener.hidden = false;
    opener.setAttribute('aria-expanded', 'false');
    if (fallback instanceof HTMLElement) fallback.hidden = true;
};

document.querySelectorAll('[data-card-overlay-root]').forEach(initCardOverlay);
