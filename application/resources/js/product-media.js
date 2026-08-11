const brokenMediaSelector = '[data-product-media-image]';

const revealFallback = (image) => {
    if (!(image instanceof HTMLImageElement) || image.dataset.mediaFallbackShown === 'true') return;

    const fallback = image.parentElement?.querySelector('[data-product-media-fallback]');
    if (!(fallback instanceof HTMLElement)) return;

    image.dataset.mediaFallbackShown = 'true';
    image.hidden = true;
    fallback.hidden = false;

    const gallery = image.closest('.et-product-gallery');
    const note = gallery?.querySelector('[data-product-media-note]');
    if (note instanceof HTMLElement && note.dataset.fallbackText) {
        note.textContent = note.dataset.fallbackText;
    }
};

export const initializeProductMedia = (root = document) => {
    root.querySelectorAll(brokenMediaSelector).forEach((image) => {
        if (!(image instanceof HTMLImageElement) || image.dataset.mediaFallbackReady === 'true') return;

        image.dataset.mediaFallbackReady = 'true';
        image.addEventListener('error', () => revealFallback(image), { once: true });

        if (image.complete && image.naturalWidth === 0) revealFallback(image);
    });
};

initializeProductMedia();
