import { initializeCatalogAutocomplete, syncCatalogMobileFilters } from './catalog-autocomplete';
import { initializeProductMedia } from './product-media';

const rootSelector = '[data-catalog-live-root]';
let requestController;
let searchTimer;

const currentRoot = () => document.querySelector(rootSelector);
const usesDeferredMobileFilters = () => window.matchMedia('(max-width: 1100px)').matches;

const formUrl = (form) => {
    const url = new URL(form.action, window.location.href);
    url.search = '';

    for (const [key, value] of new FormData(form).entries()) {
        const normalized = String(value).trim();
        if (normalized !== '') url.searchParams.append(key, normalized);
    }

    return url;
};

const setBusy = (root, busy) => {
    root.setAttribute('aria-busy', busy ? 'true' : 'false');
    root.querySelector('[data-catalog-live-results]')?.setAttribute('aria-busy', busy ? 'true' : 'false');
    root.querySelector('[data-catalog-loading]')?.setAttribute('aria-hidden', busy ? 'false' : 'true');

    const form = root.querySelector('[data-catalog-live-form]');
    form?.setAttribute('aria-busy', busy ? 'true' : 'false');

    const apply = root.querySelector('[data-testid="catalog-filter-apply"]');
    if (apply instanceof HTMLButtonElement) {
        apply.disabled = busy;
        apply.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    const label = root.querySelector('[data-catalog-apply-label]');
    const busyLabel = root.querySelector('[data-catalog-apply-busy]');
    if (label instanceof HTMLElement) label.hidden = busy;
    if (busyLabel instanceof HTMLElement) busyLabel.hidden = !busy;
};

const setFilterError = (root, visible) => {
    const error = root.querySelector('[data-catalog-filter-error]');
    if (!(error instanceof HTMLElement)) return;
    error.hidden = !visible;
};

const setDraftState = (form, dirty) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.dataset.catalogDraft = dirty ? 'true' : 'false';
};

const syncFilterState = (root, target) => {
    const form = root.querySelector('[data-catalog-live-form]');
    if (!(form instanceof HTMLFormElement)) return;

    form.querySelectorAll('[name]').forEach((control) => {
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement)) return;
        const value = target.searchParams.get(control.name) ?? '';

        if (control instanceof HTMLInputElement && control.type === 'checkbox') {
            control.checked = target.searchParams.getAll(control.name).includes(control.value);
            return;
        }

        control.value = value;
    });
};

const syncFilterCounter = (root, nextRoot) => {
    const currentSummary = root.querySelector('[data-testid="catalog-filter-toggle"]');
    const nextCounter = nextRoot.querySelector('.et-filter-counter');
    if (!currentSummary) return;

    currentSummary.querySelector('.et-filter-counter')?.remove();
    if (nextCounter) currentSummary.append(nextCounter.cloneNode(true));
};

const bindCatalog = (root, preserveMobileOpen = null) => {
    initializeCatalogAutocomplete(root);
    initializeProductMedia(root);
    syncCatalogMobileFilters(root, preserveMobileOpen);

    const filters = root.querySelector('[data-catalog-live-form]');
    if (filters && filters.dataset.catalogLiveBound !== 'true') {
        filters.dataset.catalogLiveBound = 'true';
        setDraftState(filters, false);
        filters.addEventListener('submit', (event) => {
            event.preventDefault();
            const mobileApply = usesDeferredMobileFilters();
            if (mobileApply && root.dataset.catalogApplyPending === 'true') return;
            if (mobileApply) root.dataset.catalogApplyPending = 'true';
            void loadCatalog(formUrl(filters), 'push', {
                closeMobileFilters: true,
                preserveDraftOnError: mobileApply,
            });
        });

        filters.addEventListener('change', (event) => {
            if (!(event.target instanceof HTMLSelectElement || event.target instanceof HTMLInputElement)) return;
            if (event.target.name === 'q') return;
            if (usesDeferredMobileFilters()) {
                setDraftState(filters, true);
                setFilterError(root, false);
                return;
            }
            void loadCatalog(formUrl(filters), 'push');
        });

        filters.querySelector('input[name="q"]')?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            if (usesDeferredMobileFilters()) {
                setDraftState(filters, true);
                setFilterError(root, false);
                return;
            }
            searchTimer = window.setTimeout(() => void loadCatalog(formUrl(filters), 'push'), 400);
        });

        const reset = filters.querySelector('[data-testid="catalog-filter-reset"]');
        reset?.addEventListener('click', (event) => {
            if (!usesDeferredMobileFilters()) return;
            event.preventDefault();
            syncFilterState(root, new URL(filters.action, window.location.href));
            filters.querySelector('datalist')?.replaceChildren();
            setDraftState(filters, true);
            setFilterError(root, false);
        });
    }

    const sort = root.querySelector('[data-catalog-live-sort]');
    if (sort && sort.dataset.catalogLiveBound !== 'true') {
        sort.dataset.catalogLiveBound = 'true';
        sort.addEventListener('change', () => void loadCatalog(formUrl(sort), 'push'));
    }

    root.querySelectorAll('[data-catalog-live-link]').forEach((link) => {
        if (link.dataset.catalogLiveBound === 'true') return;
        link.dataset.catalogLiveBound = 'true';
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented) return;
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            void loadCatalog(new URL(link.href, window.location.href), 'push');
        });
    });

    root.dataset.catalogLiveReady = 'true';
};

const loadCatalog = async (target, historyMode, { closeMobileFilters = false, preserveDraftOnError = false } = {}) => {
    const root = currentRoot();
    if (!root) {
        window.location.assign(target.href);
        return;
    }

    window.clearTimeout(searchTimer);
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const mobileWasOpen = root.querySelector('[data-mobile-filter]')?.open === true;
    setFilterError(root, false);
    setBusy(root, true);

    try {
        const response = await fetch(target.href, {
            signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error(`Catalog request failed with HTTP ${response.status}`);

        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextRoot = page.querySelector(rootSelector);
        if (!nextRoot) throw new Error('Catalog response is missing its live region');
        if (requestController !== controller) return;

        const results = root.querySelector('[data-catalog-live-results]');
        const nextResults = nextRoot.querySelector('[data-catalog-live-results]');
        if (!results || !nextResults) throw new Error('Catalog response is missing its results region');

        syncFilterCounter(root, nextRoot);
        syncFilterState(root, new URL(response.url));
        results.replaceChildren(...nextResults.childNodes);
        document.title = page.title || document.title;
        if (historyMode === 'push' && response.url !== window.location.href) history.pushState({ catalog: true }, '', response.url);
        setDraftState(root.querySelector('[data-catalog-live-form]'), false);
        root.dataset.catalogApplyPending = 'false';
        setBusy(root, false);

        const closeMobileSheet = closeMobileFilters && window.matchMedia('(max-width: 1100px)').matches;
        bindCatalog(root, closeMobileSheet ? false : mobileWasOpen);

        if (closeMobileSheet) {
            document.body.style.overflow = '';
            const resultCount = results.querySelector('.et-result-count');
            if (resultCount instanceof HTMLElement) {
                resultCount.tabIndex = -1;
                resultCount.focus({ preventScroll: true });
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') return;
        if (requestController === controller) {
            root.dataset.catalogApplyPending = 'false';
            setBusy(root, false);
            if (preserveDraftOnError && usesDeferredMobileFilters()) {
                setFilterError(root, true);
                return;
            }
            window.location.assign(target.href);
        }
    }
};

const root = currentRoot();
if (root) bindCatalog(root);

window.addEventListener('popstate', () => {
    if (currentRoot()) void loadCatalog(new URL(window.location.href), 'none');
});
