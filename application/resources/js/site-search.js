const searchRoots = document.querySelectorAll('[data-site-search]');

searchRoots.forEach((root) => {
    const toggle = root.querySelector('[data-site-search-toggle]');
    const panel = root.querySelector('[data-site-search-panel]');
    const input = panel?.querySelector('input[type="search"]');
    if (!toggle || !panel) return;

    let focusTimer;
    const setOpen = (open, restoreFocus = false) => {
        window.clearTimeout(focusTimer);
        root.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        panel.setAttribute('aria-hidden', String(!open));

        if (open) {
            focusTimer = window.setTimeout(() => input?.focus(), 160);
        } else if (restoreFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(!root.classList.contains('is-open'));
    });
    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !root.classList.contains('is-open')) return;
        event.preventDefault();
        setOpen(false, true);
    });
    document.addEventListener('pointerdown', (event) => {
        if (event.target instanceof Node && !root.contains(event.target)) setOpen(false);
    });

    root.setAttribute('data-site-search-ready', '');
});
