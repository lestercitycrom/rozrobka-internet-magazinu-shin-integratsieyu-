const root = document.querySelector('.et-admin-body');

if (root) {
    const overflowValues = new Set(['auto', 'scroll']);

    document.querySelectorAll('table').forEach((table, index) => {
        let region = table.parentElement;

        while (region && region !== document.body) {
            if (overflowValues.has(window.getComputedStyle(region).overflowX)) {
                break;
            }

            region = region.parentElement;
        }

        if (!region || region === document.body) {
            return;
        }

        if (!region.hasAttribute('tabindex')) {
            region.setAttribute('tabindex', '0');
        }

        if (!region.hasAttribute('role')) {
            region.setAttribute('role', 'region');
        }

        if (!region.hasAttribute('aria-label') && !region.hasAttribute('aria-labelledby')) {
            const section = region.closest('section, article, main');
            const heading = section?.querySelector('h1, h2, h3') ?? document.querySelector('#main-content h1');
            const label = heading?.textContent?.trim() || document.title || `Table ${index + 1}`;
            region.setAttribute('aria-label', label);
        }
    });
}
