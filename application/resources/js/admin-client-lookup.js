function initClientLookup(root) {
    const input = root.querySelector('[data-client-lookup-input]');
    const select = root.querySelector('[data-client-select]');
    const status = root.querySelector('[data-client-lookup-status]');
    const loadMore = root.querySelector('[data-client-load-more]');
    const endpoint = root.dataset.clientLookupUrl;

    if (!input || !select || !status || !loadMore || !endpoint) return;

    let debounceTimer;
    let controller;
    let currentQuery = '';
    let currentPage = 0;

    const message = (key) => root.dataset[`${key}Message`] || '';

    const removeTransientOptions = () => {
        const selectedValue = select.value;
        Array.from(select.options).forEach((option) => {
            if (option.value !== '' && option.value !== selectedValue) option.remove();
        });
    };

    const appendItems = (items) => {
        const existing = new Set(Array.from(select.options).map((option) => option.value));
        items.forEach((item) => {
            const value = String(item.id);
            if (existing.has(value)) return;
            select.add(new Option(String(item.label), value, false, false));
            existing.add(value);
        });
    };

    const run = async (query, page) => {
        controller?.abort();
        controller = new AbortController();
        status.textContent = message('searching');
        loadMore.hidden = true;
        loadMore.disabled = true;

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('page', String(page));
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('lookup_failed');

            const payload = await response.json();
            const items = Array.isArray(payload.items) ? payload.items : [];
            if (page === 1) removeTransientOptions();
            appendItems(items);
            currentPage = Number(payload.pagination?.page || page);
            const hasMore = payload.pagination?.has_more === true;
            status.textContent = items.length === 0 && page === 1 ? message('empty') : message('results');
            loadMore.hidden = !hasMore;
            loadMore.disabled = !hasMore;
        } catch (error) {
            if (error?.name === 'AbortError') return;
            status.textContent = message('error');
            loadMore.hidden = true;
            loadMore.disabled = true;
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        const query = input.value.trim();
        currentQuery = query;
        currentPage = 0;
        controller?.abort();
        loadMore.hidden = true;

        if (query.length < 2) {
            status.textContent = message('minimum');
            return;
        }

        debounceTimer = window.setTimeout(() => run(query, 1), 250);
    });

    loadMore.addEventListener('click', () => {
        if (currentQuery.length >= 2) run(currentQuery, currentPage + 1);
    });
}

document.querySelectorAll('[data-client-lookup]').forEach(initClientLookup);
