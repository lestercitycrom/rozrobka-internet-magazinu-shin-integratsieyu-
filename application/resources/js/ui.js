const one = (selector, root = document) => root.querySelector(selector);
const all = (selector, root = document) => [...root.querySelectorAll(selector)];

const setExpanded = (button, value) => button?.setAttribute('aria-expanded', String(value));

const initMobileMenu = () => {
    const button = one('[data-menu-toggle]');
    const menu = one('[data-mobile-menu]');
    if (!button || !menu) return;

    const close = ({ restoreFocus = false } = {}) => {
        menu.classList.remove('is-open');
        setExpanded(button, false);
        if (restoreFocus) button.focus();
    };

    button.addEventListener('click', () => {
        const open = !menu.classList.contains('is-open');
        menu.classList.toggle('is-open', open);
        setExpanded(button, open);
        if (open) one('a', menu)?.focus();
    });
    menu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) close({ restoreFocus: true });
    });
};

const initProductionFilters = () => {
    const desktop = window.matchMedia('(min-width: 981px)');

    const sync = () => {
        all('[data-mobile-filter]').forEach((details) => {
            if (!(details instanceof HTMLDetailsElement)) return;
            if (desktop.matches) details.open = true;
            details.dataset.filterReady = 'true';
        });
        if (desktop.matches || !one('[data-mobile-filter][open]')) document.body.style.overflow = '';
    };

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-filter-details-close]');
        if (!button) return;
        const details = button.closest('[data-mobile-filter]');
        if (!(details instanceof HTMLDetailsElement)) return;
        details.open = false;
        document.body.style.overflow = '';
        one('summary', details)?.focus();
    });

    document.addEventListener('toggle', (event) => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.matches('[data-mobile-filter]')) return;
        document.body.style.overflow = details.open && !desktop.matches ? 'hidden' : '';
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || desktop.matches) return;
        const details = one('[data-mobile-filter][open]');
        if (!(details instanceof HTMLDetailsElement)) return;
        details.open = false;
        document.body.style.overflow = '';
        one('summary', details)?.focus();
    });

    desktop.addEventListener('change', sync);
    sync();
};

const initTabs = () => {
    all('[data-tabs]').forEach((root) => {
        const tabs = all('[role="tab"]', root);
        const panels = all('[role="tabpanel"]', root);
        if (!tabs.length) return;
        const activate = (tab) => {
            tabs.forEach((item) => {
                const active = item === tab;
                item.setAttribute('aria-selected', String(active));
                item.tabIndex = active ? 0 : -1;
            });
            panels.forEach((panel) => { panel.hidden = panel.id !== tab.getAttribute('aria-controls'); });
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                let next = index;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                tabs[next].focus();
                activate(tabs[next]);
            });
        });
    });
};

const initBookingFlow = () => {
    all('[data-production-booking]').forEach((root) => {
        const panels = all('[data-booking-panel]', root);
        const steps = all('[data-booking-step]', root);
        if (panels.length < 2) return;
        // A server-side validation response always reopens the first production step.
        // All values remain in their original fields through Laravel old(), while the
        // global alert explains that the request needs attention.
        const hasServerErrors = Boolean(one('[data-testid="request-errors"]', root));
        let index = 0;
        if (hasServerErrors) root.dataset.serverErrors = 'true';

        const updateSummary = () => {
            all('[data-summary-field]', root).forEach((target) => {
                const name = target.dataset.summaryField;
                const checked = one(`[name="${CSS.escape(name)}"]:checked`, root);
                const field = checked || one(`[name="${CSS.escape(name)}"]`, root);
                if (!field) return;
                let value = field.value?.trim() || target.dataset.emptyLabel || '—';
                if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                    value = field.checked
                        ? field.closest('label')?.querySelector('strong')?.textContent?.trim() || field.value
                        : target.dataset.emptyLabel || '—';
                } else if (field instanceof HTMLSelectElement) {
                    value = field.selectedOptions[0]?.textContent?.trim() || value;
                } else if (checked) {
                    value = checked.closest('label')?.querySelector('strong')?.textContent?.trim() || value;
                }
                target.textContent = value;
            });
        };
        const show = (next) => {
            index = Math.max(0, Math.min(panels.length - 1, next));
            panels.forEach((panel, i) => panel.classList.toggle('is-active', i === index));
            steps.forEach((step, i) => {
                step.classList.toggle('is-active', i === index);
                step.classList.toggle('is-complete', i < index);
                step.setAttribute('aria-current', i === index ? 'step' : 'false');
            });
            updateSummary();
            one('h2', panels[index])?.focus({ preventScroll: true });
            root.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        };
        const panelIsValid = () => {
            const fields = all('input, select, textarea', panels[index]).filter((field) => !field.disabled && field.type !== 'hidden');
            const invalid = fields.find((field) => !field.checkValidity());
            if (!invalid) return true;
            invalid.reportValidity();
            invalid.focus();
            return false;
        };

        all('[data-booking-next]', root).forEach((button) => button.addEventListener('click', () => {
            if (panelIsValid()) show(index + 1);
        }));
        all('[data-booking-back]', root).forEach((button) => button.addEventListener('click', () => show(index - 1)));
        root.addEventListener('input', updateSummary);
        root.addEventListener('change', updateSummary);
        root.dataset.enhanced = 'true';
        show(index);
    });
};

const initConditionalAddress = () => {
    all('[data-fulfilment-controller]').forEach((controller) => {
        const form = controller.closest('form');
        const address = form?.querySelector('[data-mobile-address]');
        if (!form || !address) return;
        const update = () => {
            const checked = form.querySelector('[name="fulfilment"]:checked');
            const select = form.querySelector('select[name="fulfilment"]');
            const value = checked?.value || select?.value;
            const isMobile = value === 'mobile';
            const input = address.querySelector('[name="address"]');
            address.hidden = !isMobile;
            address.setAttribute('aria-hidden', String(!isMobile));
            if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
                input.required = isMobile;
            }
        };
        controller.addEventListener('change', update);
        update();
    });
};

const initMapConsent = () => {
    all('[data-map-consent]').forEach((button) => button.addEventListener('click', () => {
        const card = button.closest('[data-map-card]');
        if (!card) return;

        const embedUrl = card.dataset.mapEmbedUrl?.trim();
        const href = card.dataset.mapHref?.trim();
        if (!embedUrl) {
            one('[data-testid="map-external-only"], a[href]', card)?.focus();
            return;
        }

        card.innerHTML = '';
        const frame = document.createElement('iframe');
        frame.className = 'et-map__frame';
        frame.src = embedUrl;
        frame.title = card.dataset.mapReadyTitle || 'EasyTire kart';
        frame.loading = 'lazy';
        frame.referrerPolicy = 'no-referrer-when-downgrade';
        frame.allowFullscreen = true;
        frame.dataset.testid = 'map-iframe';

        const content = document.createElement('div');
        content.className = 'et-map__loaded';
        const title = document.createElement('h3');
        title.className = 'et-h3';
        title.textContent = card.dataset.mapReadyTitle || 'Kart er lastet';
        const text = document.createElement('p');
        text.textContent = card.dataset.mapReadyText || '';
        content.append(title, text);

        if (href) {
            const link = document.createElement('a');
            link.className = 'et-btn et-btn--primary';
            link.href = href;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = card.dataset.mapLinkLabel || 'Åpne i Google Maps';
            content.append(link);
        }

        card.append(frame, content);
        frame.focus({ preventScroll: true });
    }));
};

const initAdminSidebar = () => {
    const sidebar = one('[data-admin-sidebar]');
    const overlay = one('[data-admin-overlay]');
    const button = one('[data-admin-menu]');
    if (!sidebar || !overlay || !button) return;
    const close = ({ focus = false } = {}) => {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        setExpanded(button, false);
        document.body.style.overflow = '';
        if (focus) button.focus();
    };
    button.addEventListener('click', () => {
        const open = !sidebar.classList.contains('is-open');
        sidebar.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        setExpanded(button, open);
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) one('a', sidebar)?.focus();
    });
    overlay.addEventListener('click', () => close({ focus: true }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) close({ focus: true });
    });
};

initMobileMenu();
initProductionFilters();
initTabs();
initBookingFlow();
initConditionalAddress();
initMapConsent();
initAdminSidebar();
