import { expect, test } from '@playwright/test';
import { ensureCatalogFiltersVisible, expectNoGlobalOverflow, isMobileProject } from './helpers';

async function radiusInPixels(locator: import('@playwright/test').Locator): Promise<number> {
    return locator.evaluate((element) => Number.parseFloat(getComputedStyle(element).borderTopLeftRadius));
}

test('storefront style is consistent across home, catalog and mobile controls', async ({ page }, testInfo) => {
    await page.goto('/');
    await expect(page.locator('body.storefront')).toHaveAttribute('data-storefront-style', 'sharp');
    await expectNoGlobalOverflow(page);

    const booking = page.getByTestId('header-booking');
    if (await booking.isVisible()) {
        expect(await radiusInPixels(booking)).toBe(2);
    }

    const logistics = page.getByTestId('home-dekkhotell');
    await expect(logistics).toBeVisible();
    expect(await radiusInPixels(logistics.locator('.et-v3-logistics-card').first())).toBe(0);
    await expect(page.getByTestId('catalog-foundation')).toHaveCount(0);

    await page.goto('/dekk');
    const filters = await ensureCatalogFiltersVisible(page);
    await expect(filters).toBeVisible();
    const filterPanel = page.locator('.et-production-filter__panel');
    expect(await radiusInPixels(filterPanel)).toBe(2);

    const firstCard = page.getByTestId('catalog-product').first();
    await expect(firstCard).toBeVisible();
    expect(await radiusInPixels(firstCard)).toBe(2);
    await expectNoGlobalOverflow(page);

    if (isMobileProject(testInfo.project.name)) {
        await page.getByTestId('catalog-filter-close').click();
        await expect(page.getByTestId('catalog-filter-drawer')).not.toHaveAttribute('open', '');
    }
});

test('the Russian QA route remains private and uses the approved style', async ({ page }) => {
    await page.goto('/ru');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.locator('body.storefront')).toHaveAttribute('data-storefront-style', 'sharp');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expectNoGlobalOverflow(page);
});

test('mobile footer links remain comfortable touch targets', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo.project.name), 'Mobile touch-target contract');

    await page.goto('/dekk');
    const footerToggles = page.locator('[data-footer-toggle]');
    await expect(footerToggles).toHaveCount(3);
    for (const toggle of await footerToggles.all()) {
        await expect(toggle).toBeVisible();
        await toggle.click();
    }

    const footerLinkHeights = await page.locator('.et-footer li > a:visible').evaluateAll((links) =>
        links.map((link) => Math.round(link.getBoundingClientRect().height))
    );

    expect(footerLinkHeights.length).toBeGreaterThan(0);
    expect(Math.min(...footerLinkHeights)).toBeGreaterThanOrEqual(44);
    await expectNoGlobalOverflow(page);
});

test('desktop catalog uses the DS-1 container, filter and product grid', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'Desktop geometry contract');

    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/dekk');

    const geometry = await page.evaluate(() => {
        const layout = document.querySelector<HTMLElement>('.et-catalog-layout');
        const sidebar = document.querySelector<HTMLElement>('.et-catalog-layout > aside');
        const results = document.querySelector<HTMLElement>('.et-catalog-results');
        const search = document.querySelector<HTMLElement>('.et-filter-search');
        const grid = document.querySelector<HTMLElement>('.et-product-grid');
        const firstCard = document.querySelector<HTMLElement>('.et-product-card');

        if (!layout || !sidebar || !results || !search || !grid || !firstCard) {
            throw new Error('Catalog geometry targets are missing');
        }

        const layoutRect = layout.getBoundingClientRect();

        return {
            viewportWidth: window.innerWidth,
            layoutWidth: layoutRect.width,
            sideGutter: layoutRect.left,
            sidebarWidth: sidebar.getBoundingClientRect().width,
            resultsWidth: results.getBoundingClientRect().width,
            searchWidth: search.getBoundingClientRect().width,
            firstCardWidth: firstCard.getBoundingClientRect().width,
            cardColumns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
        };
    });

    expect(geometry.layoutWidth).toBeCloseTo(1280, 0);
    expect(geometry.sideGutter).toBeCloseTo((geometry.viewportWidth - 1280) / 2, 0);
    expect(geometry.sidebarWidth).toBeCloseTo(280, 0);
    expect(geometry.resultsWidth).toBeGreaterThanOrEqual(970);
    expect(geometry.searchWidth).toBeGreaterThanOrEqual(230);
    expect(geometry.firstCardWidth).toBeGreaterThanOrEqual(300);
    expect(geometry.cardColumns).toBe(3);
    await expectNoGlobalOverflow(page);

    for (const viewport of [
        { width: 1366, columns: 3 },
        { width: 1280, columns: 3 },
    ]) {
        await page.setViewportSize({ width: viewport.width, height: 900 });
        const responsiveGeometry = await page.evaluate(() => {
            const layout = document.querySelector<HTMLElement>('.et-catalog-layout')!;
            const sidebar = document.querySelector<HTMLElement>('.et-catalog-layout > aside')!;
            const grid = document.querySelector<HTMLElement>('.et-product-grid')!;
            const card = document.querySelector<HTMLElement>('.et-product-card')!;

            return {
                layoutWidth: layout.getBoundingClientRect().width,
                sidebarWidth: sidebar.getBoundingClientRect().width,
                cardWidth: card.getBoundingClientRect().width,
                columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
            };
        });

        const expectedWidth = Math.min(viewport.width - (viewport.width >= 1200 ? 64 : 48), 1280);
        expect(responsiveGeometry.layoutWidth, `catalog width at ${viewport.width}px`).toBeCloseTo(expectedWidth, 0);
        expect(responsiveGeometry.sidebarWidth, `filter width at ${viewport.width}px`).toBeCloseTo(280, 0);
        expect(responsiveGeometry.cardWidth, `card width at ${viewport.width}px`).toBeGreaterThanOrEqual(280);
        expect(responsiveGeometry.columns, `column count at ${viewport.width}px`).toBe(viewport.columns);
        await expectNoGlobalOverflow(page);
    }

    await page.setViewportSize({ width: 1024, height: 900 });
    await expect(page.getByTestId('catalog-filter-toggle')).toBeVisible();
    const tabletGeometry = await page.evaluate(() => {
        const layout = document.querySelector<HTMLElement>('.et-catalog-layout')!;
        const results = document.querySelector<HTMLElement>('.et-catalog-results')!;
        const grid = document.querySelector<HTMLElement>('.et-product-grid')!;
        const card = document.querySelector<HTMLElement>('.et-product-card')!;
        const toggle = document.querySelector<HTMLElement>('[data-testid="catalog-filter-toggle"]')!;

        return {
            layoutWidth: layout.getBoundingClientRect().width,
            resultsWidth: results.getBoundingClientRect().width,
            cardWidth: card.getBoundingClientRect().width,
            columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
            toggleDisplay: getComputedStyle(toggle).display,
        };
    });

    expect(tabletGeometry.layoutWidth / 1024).toBeGreaterThanOrEqual(.9);
    expect(tabletGeometry.resultsWidth / tabletGeometry.layoutWidth).toBeGreaterThanOrEqual(.99);
    expect(tabletGeometry.cardWidth).toBeGreaterThanOrEqual(400);
    expect(tabletGeometry.columns).toBe(2);
    expect(tabletGeometry.toggleDisplay).toBe('flex');
    await expect(page.getByTestId('catalog-filter-drawer')).not.toHaveAttribute('open', '');
    await expectNoGlobalOverflow(page);
});
