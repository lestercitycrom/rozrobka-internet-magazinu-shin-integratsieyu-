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
        if (open) one('summary, a', menu)?.focus();
    });
    menu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) close({ restoreFocus: true });
    });
};

const initStickyHeader = () => {
    const header = one('[data-site-header]');
    if (!header) return;

    let scheduled = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 12);
        scheduled = false;
    };

    window.addEventListener('scroll', () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    }, { passive: true });
    update();
};

const initFooterGroups = () => {
    const footer = one('[data-footer-groups]');
    if (!footer) return;

    const mobile = window.matchMedia('(max-width: 640px)');
    const groups = all('[data-footer-group]', footer);

    const setExpanded = (group, expanded) => {
        const button = one('[data-footer-toggle]', group);
        const links = one('[data-footer-links]', group);
        if (!button || !links) return;
        button.setAttribute('aria-expanded', String(expanded));
        links.hidden = mobile.matches ? !expanded : false;
    };

    groups.forEach((group) => {
        one('[data-footer-toggle]', group)?.addEventListener('click', () => {
            const button = one('[data-footer-toggle]', group);
            setExpanded(group, button?.getAttribute('aria-expanded') !== 'true');
        });
    });

    const sync = () => {
        footer.classList.add('is-enhanced');
        groups.forEach((group) => setExpanded(group, !mobile.matches));
    };

    mobile.addEventListener('change', sync);
    sync();
};

const initProductionFilters = () => {
    const desktop = window.matchMedia('(min-width: 1101px)');

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

        const availability = one('[data-booking-availability]', root);
        if (availability) {
            const slotContainer = one('[data-booking-slots]', availability);
            const manualContainer = one('[data-booking-manual]', availability);
            const slotSelect = one('[data-booking-slot-select]', availability);
            const manualInput = one('[name="preferred_window"]', manualContainer || availability);
            const dateInput = one('[name="preferred_date"]', root);
            const status = one('[data-booking-availability-status]', availability);
            const timePanel = availability.closest('[data-booking-panel]');
            const timeNext = one('[data-booking-next]', timePanel);
            let fetchSequence = 0;

            const setMode = (configured) => {
                if (slotContainer) slotContainer.hidden = !configured;
                if (manualContainer) manualContainer.hidden = configured;
                if (slotSelect) {
                    slotSelect.disabled = !configured;
                    slotSelect.required = configured;
                }
                if (manualInput) {
                    manualInput.disabled = configured;
                    manualInput.required = !configured;
                }
            };
            const resetSlots = (message) => {
                if (!slotSelect) return;
                slotSelect.replaceChildren(new Option(availability.dataset.placeholder || '', ''));
                if (status) status.textContent = message;
            };
            const loadAvailability = async () => {
                const date = dateInput?.value;
                const fulfilment = one('[name="fulfilment"]:checked', root)?.value;
                if (!date || !fulfilment || !slotSelect) {
                    resetSlots(availability.dataset.selectDate || '');
                    return;
                }

                const sequence = ++fetchSequence;
                timeNext?.setAttribute('disabled', '');
                availability.setAttribute('aria-busy', 'true');
                resetSlots(availability.dataset.loading || '');
                try {
                    const url = new URL(availability.dataset.endpoint, window.location.origin);
                    url.searchParams.set('date', date);
                    url.searchParams.set('fulfilment', fulfilment);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) throw new Error(`Availability ${response.status}`);
                    const payload = await response.json();
                    if (sequence !== fetchSequence) return;

                    const configured = payload.configured === true;
                    setMode(configured);
                    if (!configured) return;

                    const oldSlot = slotSelect.dataset.oldSlot || slotSelect.value;
                    resetSlots((payload.slots || []).length ? '' : availability.dataset.empty || '');
                    (payload.slots || []).forEach((slot) => {
                        const option = new Option(slot.label, slot.key);
                        option.selected = oldSlot === slot.key;
                        slotSelect.add(option);
                    });
                    slotSelect.dataset.oldSlot = '';
                } catch {
                    if (sequence !== fetchSequence) return;
                    // Fail closed. If a schedule exists, the server will never
                    // accept a free-text value in place of a real slot.
                    setMode(true);
                    resetSlots(availability.dataset.loadError || '');
                } finally {
                    if (sequence === fetchSequence) {
                        timeNext?.removeAttribute('disabled');
                        availability.removeAttribute('aria-busy');
                    }
                }
            };

            setMode(availability.dataset.initialConfigured === 'true');
            dateInput?.addEventListener('change', loadAvailability);
            all('[name="fulfilment"]', root).forEach((field) => field.addEventListener('change', loadAvailability));
            if (dateInput?.value) loadAvailability();
        }

        let seasonalPrices = {};
        try { seasonalPrices = JSON.parse(root.dataset.seasonalPrices || '{}'); } catch { seasonalPrices = {}; }
        const secondVehicle = one('[data-seasonal-vehicle="2"]', root);
        const carCount = one('[data-car-count]', root);
        const syncVehicleTypeOptions = () => {
            const workshop = one('[name="fulfilment"]:checked', root)?.value === 'workshop';
            ['vehicle_type', 'vehicle_type_2'].forEach((name) => {
                const select = one(`[name="${name}"]`, root);
                if (!(select instanceof HTMLSelectElement)) return;
                all('option', select).forEach((option) => {
                    option.disabled = workshop && option.value !== 'passenger';
                });
                if (workshop && select.value !== 'passenger') select.value = 'passenger';
            });
        };
        const toggleSecondVehicle = () => {
            if (!secondVehicle || !carCount) return;
            const enabled = carCount.value === '2';
            secondVehicle.hidden = !enabled;
            all('input, select, textarea', secondVehicle).forEach((field) => {
                field.disabled = !enabled;
                if (field.name === 'vehicle_2') field.required = enabled;
            });
        };
        const priceRange = (suffix = '') => {
            const value = (name, fallback) => one(`[name="${name}${suffix}"]`, root)?.value || fallback;
            const fulfilment = one('[name="fulfilment"]:checked', root)?.value || 'workshop';
            const vehicleType = value('vehicle_type', 'passenger');
            const rimBand = value('rim_band', 'unknown');
            const storagePlan = value('storage_plan', 'none');
            let baseCodes;
            if (vehicleType !== 'passenger') {
                if (fulfilment !== 'mobile') return null;
                baseCodes = [vehicleType === 'large_vehicle' ? 'mobile-large-vehicle' : 'mobile-six-wheels'];
            } else {
                const rims = rimBand === 'unknown' ? ['r15', 'r16-r18', 'r19'] : [rimBand];
                baseCodes = rims.map((rim) => `${fulfilment}-${rim}`);
            }
            const storageCodes = storagePlan === 'none'
                ? []
                : (rimBand === 'unknown' ? ['r15', 'r16-r18', 'r19'] : [rimBand]).map((rim) => `hotel-${rim}`);
            const base = baseCodes.map((code) => Number(seasonalPrices[code])).filter(Number.isFinite);
            const storage = storageCodes.map((code) => Number(seasonalPrices[code])).filter(Number.isFinite);
            if (base.length !== baseCodes.length || storage.length !== storageCodes.length) return null;
            return [Math.min(...base) + (storage.length ? Math.min(...storage) : 0), Math.max(...base) + (storage.length ? Math.max(...storage) : 0)];
        };
        const updateSeasonalQuote = () => {
            const quote = one('[data-seasonal-quote]', root);
            const quoteText = one('[data-seasonal-quote-text]', root);
            const summary = one('[data-seasonal-summary-price]', root);
            if (!quote || !quoteText || !summary) return;
            const ranges = [priceRange('')];
            if (carCount?.value === '2') ranges.push(priceRange('_2'));
            let text = quote.dataset.unavailableTemplate || '—';
            if (ranges.every(Boolean)) {
                const minimum = ranges.reduce((sum, range) => sum + range[0], 0) / 100;
                const maximum = ranges.reduce((sum, range) => sum + range[1], 0) / 100;
                const formattedMin = new Intl.NumberFormat(document.documentElement.lang || 'nb-NO').format(minimum);
                const formattedMax = new Intl.NumberFormat(document.documentElement.lang || 'nb-NO').format(maximum);
                const template = minimum === maximum ? quote.dataset.exactTemplate : quote.dataset.rangeTemplate;
                text = template.replace(':min', formattedMin).replace(':max', formattedMax);
            }
            quoteText.textContent = text;
            summary.textContent = text;
        };

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
            toggleSecondVehicle();
            syncVehicleTypeOptions();
            updateSeasonalQuote();
        };
        const show = (next, userInitiated = false) => {
            index = Math.max(0, Math.min(panels.length - 1, next));
            panels.forEach((panel, i) => panel.classList.toggle('is-active', i === index));
            steps.forEach((step, i) => {
                step.classList.toggle('is-active', i === index);
                step.classList.toggle('is-complete', i < index);
                step.setAttribute('aria-current', i === index ? 'step' : 'false');
            });
            updateSummary();
            if (userInitiated) {
                one('h2', panels[index])?.focus({ preventScroll: true });
                root.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            }
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
            if (panelIsValid()) show(index + 1, true);
        }));
        all('[data-booking-back]', root).forEach((button) => button.addEventListener('click', () => show(index - 1, true)));
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

const initRequestErrorFocus = () => {
    all('form[data-testid="generic-service-request"], form[data-testid="checkout-form"]').forEach((form) => {
        if (!one('[data-testid="request-errors"]', form)) return;
        const firstInvalidField = one('[aria-invalid="true"]', form);
        if (!(firstInvalidField instanceof HTMLElement)) return;
        window.requestAnimationFrame(() => firstInvalidField.focus());
    });
};

const initAdminSidebar = () => {
    const sidebar = one('[data-admin-sidebar]');
    const overlay = one('[data-admin-overlay]');
    const button = one('[data-admin-menu]');
    if (!sidebar || !overlay || !button) return;
    const mobile = window.matchMedia('(max-width: 900px)');
    const syncInertState = () => {
        sidebar.inert = mobile.matches && !sidebar.classList.contains('is-open');
    };
    const close = ({ focus = false } = {}) => {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        setExpanded(button, false);
        document.body.style.overflow = '';
        syncInertState();
        if (focus) button.focus();
    };
    button.addEventListener('click', () => {
        const open = !sidebar.classList.contains('is-open');
        sidebar.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        setExpanded(button, open);
        document.body.style.overflow = open ? 'hidden' : '';
        syncInertState();
        if (open) one('a', sidebar)?.focus();
    });
    overlay.addEventListener('click', () => close({ focus: true }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) close({ focus: true });
    });
    mobile.addEventListener('change', syncInertState);
    syncInertState();
};

initMobileMenu();
initStickyHeader();
initFooterGroups();
initProductionFilters();
initTabs();
initBookingFlow();
initConditionalAddress();
initRequestErrorFocus();
initAdminSidebar();
