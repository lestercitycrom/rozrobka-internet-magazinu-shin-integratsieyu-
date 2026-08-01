const delay = (callback, wait) => {
    let timer;
    return (...args) => { clearTimeout(timer); timer = window.setTimeout(() => callback(...args), wait); };
};

document.querySelectorAll('[data-catalog-autocomplete]').forEach((input) => {
    const list = document.getElementById(input.getAttribute('aria-controls'));
    const endpoint = input.dataset.catalogAutocomplete;
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

document.querySelectorAll('[data-mobile-filter]').forEach((filters) => {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const sync = () => { filters.open = desktop.matches; };

    sync();
    desktop.addEventListener('change', sync);
});
