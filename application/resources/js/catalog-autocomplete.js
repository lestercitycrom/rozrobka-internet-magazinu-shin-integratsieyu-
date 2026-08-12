const delay = (callback, wait) => {
    let timer;
    return (...args) => { clearTimeout(timer); timer = window.setTimeout(() => callback(...args), wait); };
};

const initialized = new WeakSet();
const desktop = window.matchMedia('(min-width: 1101px)');

export const initializeCatalogAutocomplete = (scope = document) => {
    scope.querySelectorAll('[data-catalog-autocomplete]').forEach((input) => {
        if (initialized.has(input)) return;
        initialized.add(input);

        const listId = input.getAttribute('aria-controls');
        const endpoint = input.dataset.catalogAutocomplete;
        if (!listId || !endpoint) return;
        const list = scope.querySelector(`#${CSS.escape(listId)}`);
        if (!list) return;
        let controller;
        let previous = '';

        const search = delay(async () => {
            const term = input.value.trim();
            if (term.length < 2) {
                controller?.abort();
                previous = '';
                list.replaceChildren();
                return;
            }
            if (term === previous) return;
            previous = term;
            controller?.abort();
            controller = new AbortController();
            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Autocomplete request failed with HTTP ${response.status}`);
                const { suggestions } = await response.json();
                if (!Array.isArray(suggestions)) throw new Error('Autocomplete response is malformed');
                list.replaceChildren(...suggestions.map((item) => {
                    const option = document.createElement('option');
                    option.value = item.sku;
                    option.label = `${item.brand} - ${item.title}`;
                    return option;
                }));
            } catch (error) {
                if (error.name !== 'AbortError') {
                    if (input.value.trim() === term) previous = '';
                    list.replaceChildren();
                }
            }
        }, 250);

        input.addEventListener('input', search);
    });
};

export const syncCatalogMobileFilters = (scope = document, preserveOpen = null) => {
    scope.querySelectorAll('[data-mobile-filter]').forEach((filters) => {
        filters.open = desktop.matches || preserveOpen === true;
        filters.dataset.filterReady = 'true';
    });
};

desktop.addEventListener('change', () => syncCatalogMobileFilters());
