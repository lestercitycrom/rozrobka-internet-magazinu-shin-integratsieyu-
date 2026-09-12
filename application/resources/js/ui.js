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

const initDesktopMegaMenu = () => {
    const desktop = window.matchMedia('(min-width: 1181px)');

    all('[data-mega-menu]').forEach((root) => {
        const toggle = one('[data-mega-menu-toggle]', root);
        const panel = one('[data-mega-menu-panel]', root);
        if (!toggle || !panel) return;
        let pinnedOpen = false;

        const setOpen = (open, { restoreFocus = false } = {}) => {
            if (restoreFocus) toggle.focus();
            root.classList.toggle('is-open', open);
            setExpanded(toggle, open);
            panel.setAttribute('aria-hidden', String(!open));
            panel.toggleAttribute('inert', !open);
        };

        toggle.addEventListener('click', () => {
            pinnedOpen = !pinnedOpen;
            setOpen(pinnedOpen);
        });
        root.addEventListener('mouseenter', () => { if (desktop.matches) setOpen(true); });
        root.addEventListener('mouseleave', () => {
            if (desktop.matches && !pinnedOpen && !root.matches(':focus-within')) setOpen(false);
        });
        root.addEventListener('focusin', () => { if (desktop.matches) setOpen(true); });
        root.addEventListener('focusout', () => window.requestAnimationFrame(() => {
            if (!pinnedOpen && !root.matches(':focus-within')) setOpen(false);
        }));
        root.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !root.classList.contains('is-open')) return;
            event.preventDefault();
            pinnedOpen = false;
            setOpen(false, { restoreFocus: true });
        });
        document.addEventListener('pointerdown', (event) => {
            if (event.target instanceof Node && !root.contains(event.target)) {
                pinnedOpen = false;
                setOpen(false);
            }
        });
        desktop.addEventListener('change', () => {
            pinnedOpen = false;
            setOpen(false);
        });
        root.setAttribute('data-mega-menu-ready', '');
        setOpen(false);
    });
};

const initHeaderCatalogSearch = () => {
    all('[data-catalog-search]').forEach((root) => {
        const toggle = one('[data-catalog-search-toggle]', root);
        const panel = one('[data-catalog-search-panel]', root);
        if (!toggle || !panel) return;
        let focusTimer;

        const setOpen = (open, { restoreFocus = false } = {}) => {
            window.clearTimeout(focusTimer);
            root.classList.toggle('is-open', open);
            setExpanded(toggle, open);
            panel.setAttribute('aria-hidden', String(!open));
            if (open) {
                focusTimer = window.setTimeout(() => {
                    if (root.classList.contains('is-open') && !panel.matches(':focus-within')) one('input', panel)?.focus();
                }, 170);
            }
            if (restoreFocus) toggle.focus();
        };

        toggle.addEventListener('click', () => setOpen(!root.classList.contains('is-open')));
        root.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !root.classList.contains('is-open')) return;
            event.preventDefault();
            setOpen(false, { restoreFocus: true });
        });
        document.addEventListener('pointerdown', (event) => {
            if (event.target instanceof Node && !root.contains(event.target)) setOpen(false);
        });
    });
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

const initPromotionCarousels = () => {
    all('[data-promotion-carousel]').forEach((root) => {
        const track = one('[data-promotion-track]', root);
        const cards = track ? all('[data-testid="homepage-promotion"]', track) : [];
        const section = root.closest('[data-testid="homepage-promotions"]');
        const previous = section ? one('[data-promotion-previous]', section) : null;
        const next = section ? one('[data-promotion-next]', section) : null;
        const status = one('[data-promotion-status]', root);
        if (!track || cards.length <= 3 || !previous || !next) return;

        let firstVisible = 0;
        let resizeFrame = 0;
        const visibleCount = () => {
            if (window.matchMedia('(min-width: 1121px)').matches) return 3;
            if (window.matchMedia('(min-width: 768px)').matches) return 2;
            return 1;
        };
        const render = () => {
            const visible = visibleCount();
            const maximum = Math.max(0, cards.length - visible);
            firstVisible = Math.min(Math.max(0, firstVisible), maximum);
            root.style.setProperty('--cards-visible', String(visible));

            const firstCard = cards[0];
            const computedTrack = window.getComputedStyle(track);
            const gap = Number.parseFloat(computedTrack.columnGap || computedTrack.gap || '0') || 0;
            const step = firstCard.getBoundingClientRect().width + gap;
            track.style.transform = `translate3d(-${firstVisible * step}px, 0, 0)`;
            previous.disabled = firstVisible === 0;
            next.disabled = firstVisible === maximum;

            cards.forEach((card, index) => {
                const visibleNow = index >= firstVisible && index < firstVisible + visible;
                card.toggleAttribute('inert', !visibleNow);
                card.setAttribute('aria-hidden', String(!visibleNow));
            });

            if (status) {
                const range = visible === 1
                    ? String(firstVisible + 1)
                    : `${firstVisible + 1}–${Math.min(firstVisible + visible, cards.length)}`;
                status.textContent = (status.dataset.statusTemplate || '')
                    .replace('__CURRENT__', range)
                    .replace('__TOTAL__', String(cards.length));
            }
        };

        previous.addEventListener('click', () => {
            firstVisible -= 1;
            render();
        });
        next.addEventListener('click', () => {
            firstVisible += 1;
            render();
        });
        window.addEventListener('resize', () => {
            window.cancelAnimationFrame(resizeFrame);
            resizeFrame = window.requestAnimationFrame(render);
        }, { passive: true });

        root.classList.add('is-enhanced');
        render();
    });
};

const initPromotionDisclosures = () => {
    all('[data-promotion-disclosure]').forEach((button) => {
        const panelId = button.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        const card = button.closest('[data-testid="homepage-promotion"]');
        if (!panel || !card) return;

        const hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 768px)');
        let hovering = false;
        let focused = false;
        let pinnedOpen = false;
        let ambientSuppressed = false;

        const setOpen = (open) => {
            button.setAttribute('aria-expanded', String(open));
            panel.setAttribute('aria-hidden', String(!open));
            panel.setAttribute('tabindex', open ? '0' : '-1');
            panel.classList.toggle('is-open', open);
            card.style.removeProperty('min-height');
            if (!open) panel.scrollTop = 0;
        };

        const render = () => setOpen(pinnedOpen || (!ambientSuppressed && (hovering || focused)));

        card.addEventListener('pointerenter', (event) => {
            if (!hoverCapable.matches || event.pointerType === 'touch') return;
            hovering = true;
            ambientSuppressed = false;
            render();
        });
        card.addEventListener('pointerleave', () => {
            hovering = false;
            if (!focused) ambientSuppressed = false;
            render();
        });
        card.addEventListener('focusin', () => {
            if (!focused && !hovering) ambientSuppressed = false;
            focused = true;
            render();
        });
        card.addEventListener('focusout', () => window.requestAnimationFrame(() => {
            focused = card.matches(':focus-within');
            if (!focused && !hovering) ambientSuppressed = false;
            render();
        }));
        button.addEventListener('click', () => {
            if (pinnedOpen) {
                pinnedOpen = false;
                ambientSuppressed = true;
            } else {
                pinnedOpen = true;
                ambientSuppressed = false;
            }
            render();
        });
        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || button.getAttribute('aria-expanded') !== 'true') return;
            event.preventDefault();
            pinnedOpen = false;
            ambientSuppressed = true;
            setOpen(false);
            button.focus();
        });
        hoverCapable.addEventListener('change', () => {
            hovering = false;
            if (!focused) ambientSuppressed = false;
            render();
        });
        setOpen(false);
    });
};

const initDekkhotellDialog = () => {
    const dialog = one('[data-dekkhotell-dialog]');
    const openers = all('[data-dekkhotell-dialog-open]');
    if (typeof HTMLDialogElement === 'undefined' || !(dialog instanceof HTMLDialogElement) || openers.length === 0) return;

    let returnFocus = null;
    const close = () => {
        if (dialog.open) dialog.close();
    };

    openers.forEach((button) => button.addEventListener('click', () => {
        returnFocus = button;
        dialog.showModal();
        one('[data-dekkhotell-dialog-close]', dialog)?.focus();
    }));
    all('[data-dekkhotell-dialog-close]', dialog).forEach((button) => button.addEventListener('click', close));
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const focusable = all('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', dialog)
            .filter((element) => element instanceof HTMLElement && !element.hidden);
        if (focusable.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('close', () => {
        if (returnFocus instanceof HTMLElement) returnFocus.focus();
    });
};

const initHomepageVideos = () => {
    all('[data-homepage-video]').forEach((root) => {
        const video = one('[data-homepage-video-element]', root);
        const control = one('[data-homepage-video-control]', root);
        if (!(video instanceof HTMLVideoElement) || !(control instanceof HTMLButtonElement)) return;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const render = () => {
            const paused = video.paused;
            const label = paused ? control.dataset.playLabel : control.dataset.pauseLabel;
            control.textContent = label || '';
            control.setAttribute('aria-label', label || '');
            control.setAttribute('aria-pressed', String(paused));
        };
        const failSafe = () => {
            video.hidden = true;
            control.hidden = true;
            root.classList.add('has-video-error');
        };
        const respectMotionPreference = () => {
            if (reducedMotion.matches) {
                video.pause();
                video.currentTime = 0;
            } else {
                video.play().catch(() => {
                    if (video.error) failSafe();
                    else render();
                });
            }
            render();
        };

        control.addEventListener('click', () => {
            if (video.paused) video.play().catch(failSafe);
            else video.pause();
        });
        video.addEventListener('play', render);
        video.addEventListener('pause', render);
        video.addEventListener('error', failSafe);
        reducedMotion.addEventListener('change', respectMotionPreference);
        if (video.error) {
            failSafe();
            return;
        }
        respectMotionPreference();
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
initDesktopMegaMenu();
initHeaderCatalogSearch();
initFooterGroups();
initProductionFilters();
initTabs();
initBookingFlow();
initConditionalAddress();
initRequestErrorFocus();
initPromotionCarousels();
initPromotionDisclosures();
initDekkhotellDialog();
initHomepageVideos();
initAdminSidebar();
