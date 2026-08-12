import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

test('mobile catalog keeps filters, sorting and product actions usable at 390 and 320 pixels', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('mobile'), 'Mobile geometry contract');

    const viewport = testInfo.project.name.includes('320')
        ? { width: 320, height: 720 }
        : { width: 390, height: 844 };
    await page.setViewportSize(viewport);
    await page.goto('/dekk');

    const initialGeometry = await page.evaluate(() => {
        const rect = (selector: string) => {
            const element = document.querySelector<HTMLElement>(selector);
            if (!element) throw new Error(`Missing ${selector}`);
            return element.getBoundingClientRect();
        };

        return {
            viewport: { width: window.innerWidth, height: window.innerHeight },
            layout: rect('.et-catalog-layout'),
            filter: rect('[data-testid="catalog-filter-toggle"]'),
            toolbar: rect('.et-toolbar'),
            card: rect('[data-testid="catalog-product"]'),
            action: rect('[data-testid="catalog-product"] .et-product-card__buy, [data-testid="catalog-product"] .et-product-card__more'),
            price: rect('[data-testid="catalog-product"] .et-price'),
            priceLineHeight: Number.parseFloat(getComputedStyle(document.querySelector<HTMLElement>('[data-testid="catalog-product"] .et-price')!).lineHeight),
        };
    });

    expect(initialGeometry.layout.width).toBeGreaterThanOrEqual(initialGeometry.viewport.width - 34);
    expect(initialGeometry.filter.width).toBeGreaterThanOrEqual(initialGeometry.viewport.width - 34);
    expect(initialGeometry.toolbar.height).toBeLessThanOrEqual(160);
    expect(initialGeometry.card.width).toBeGreaterThanOrEqual(initialGeometry.viewport.width - 34);
    expect(initialGeometry.card.height).toBeGreaterThanOrEqual(210);
    expect(initialGeometry.action.width).toBeGreaterThanOrEqual(44);
    expect(initialGeometry.action.height).toBeGreaterThanOrEqual(44);
    expect(initialGeometry.price.height).toBeLessThanOrEqual(initialGeometry.priceLineHeight * 1.25);
    expect(initialGeometry.action.width).toBeGreaterThanOrEqual(initialGeometry.viewport.width === 320 ? 140 : 175);
    await expectNoGlobalOverflow(page);

    await page.getByTestId('catalog-filter-toggle').click();
    const drawer = page.getByTestId('catalog-filter-drawer');
    const form = page.getByTestId('catalog-filters');
    await expect(drawer).toHaveAttribute('open', '');
    await expect(form).toBeVisible();
    await expect(page.getByTestId('catalog-filter-close')).toBeVisible();

    const drawerGeometry = await page.evaluate(() => {
        const panel = document.querySelector<HTMLElement>('.et-production-filter__panel');
        const summary = document.querySelector<HTMLElement>('[data-testid="catalog-filter-toggle"]');
        const dimensions = [...document.querySelectorAll<HTMLElement>('.et-size-builder select')]
            .map((select) => select.getBoundingClientRect().width);
        if (!panel || !summary) throw new Error('Missing drawer controls');

        return {
            viewport: { width: window.innerWidth, height: window.innerHeight },
            panel: panel.getBoundingClientRect(),
            summaryDisplay: getComputedStyle(summary).display,
            dimensions,
        };
    });

    expect(drawerGeometry.panel.width).toBeGreaterThanOrEqual(Math.min(300, drawerGeometry.viewport.width));
    expect(drawerGeometry.panel.height).toBeLessThanOrEqual(drawerGeometry.viewport.height);
    expect(drawerGeometry.summaryDisplay).toBe('none');
    expect(drawerGeometry.dimensions.every((width) => width >= 120)).toBe(true);
    await expectNoGlobalOverflow(page);

    await page.getByTestId('catalog-filter-close').click();
    await expect(drawer).not.toHaveAttribute('open', '');

    await page.getByTestId('catalog-filter-toggle').click();
    await expect(form).toBeVisible();
    await form.getByTestId('catalog-filter-apply').click();
    await expect(drawer).not.toHaveAttribute('open', '');
    await expect(page.locator('.et-result-count')).toBeFocused();
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('');
    await expectNoGlobalOverflow(page);

    await page.goto('/felger');
    const fallback = page.getByTestId('product-visual-fallback').first();
    await expect(fallback).toBeVisible();
    await expect(fallback).toHaveAttribute('data-product-fallback-kind', 'wheel');
    await expect(fallback.getByTestId('product-fallback-blueprint')).toBeVisible();
    await expect(fallback.locator('.et-fallback-meta__context')).toHaveCSS('display', 'none');
    const fallbackGeometry = await fallback.evaluate((element) => {
        const media = element.closest<HTMLElement>('.et-product-card__media');
        const status = element.querySelector<HTMLElement>('.et-fallback-meta__status');
        if (!media || !status) throw new Error('Missing fallback geometry');
        const outer = media.getBoundingClientRect();
        const inner = status.getBoundingClientRect();
        return {
            contained: inner.left >= outer.left && inner.right <= outer.right && inner.top >= outer.top && inner.bottom <= outer.bottom,
            mediaWidth: outer.width,
            statusWidth: inner.width,
        };
    });
    expect(fallbackGeometry.contained).toBe(true);
    expect(fallbackGeometry.mediaWidth).toBeGreaterThanOrEqual(110);
    expect(fallbackGeometry.statusWidth).toBeGreaterThan(30);
    await expectNoGlobalOverflow(page);
});
