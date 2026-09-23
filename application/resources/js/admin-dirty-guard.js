const root = document.querySelector('[data-admin-dirty-root]');

if (root) {
    const dirtyForms = new Set();
    let navigating = false;

    root.querySelectorAll('form[data-conflict-draft]').forEach((form) => {
        dirtyForms.add(form);
    });

    const guardedForm = (target) => {
        if (!(target instanceof Element)) {
            return null;
        }

        const form = target.closest('form');

        if (!(form instanceof HTMLFormElement) || !root.contains(form)) {
            return null;
        }

        return form.method.toLowerCase() === 'post' ? form : null;
    };

    const markDirty = (event) => {
        const form = guardedForm(event.target);

        if (form) {
            dirtyForms.add(form);
        }
    };

    root.addEventListener('input', markDirty);
    root.addEventListener('change', markDirty);
    window.addEventListener('submit', (event) => {
        const form = guardedForm(event.target);

        if (!form || event.defaultPrevented) {
            return;
        }

        const otherDirtyForms = Array.from(dirtyForms).filter((dirtyForm) => dirtyForm !== form && dirtyForm.isConnected);

        if (otherDirtyForms.length > 0 && !window.confirm(root.dataset.adminUnsavedMessage ?? '')) {
            event.preventDefault();
            event.stopImmediatePropagation();

            return;
        }

        dirtyForms.clear();
        navigating = true;
    });

    root.adminDirtyGuard = {
        allowNavigation() {
            dirtyForms.clear();
            navigating = true;
        },
        hasDirtyForms() {
            return dirtyForms.size > 0;
        },
    };

    document.querySelectorAll('[data-admin-locale-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (dirtyForms.size === 0) {
                return;
            }

            const message = root.dataset.adminUnsavedMessage ?? '';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();

                return;
            }

            dirtyForms.clear();
            navigating = true;
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirtyForms.size === 0 || navigating) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}
