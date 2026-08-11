import { expect, test } from '@playwright/test';
import { ensureCatalogFiltersVisible, expectNoGlobalOverflow, isMobileProject } from './helpers';

const isSharpProject = (projectName: string): boolean => projectName.includes('sharp');

async function radiusInPixels(locator: import('@playwright/test').Locator): Promise<number> {
    return locator.evaluate((element) => Number.parseFloat(getComputedStyle(element).borderTopLeftRadius));
}

test('storefront style is consistent across home, catalog and mobile controls', async ({ page }, testInfo) => {
    const sharp = isSharpProject(testInfo.project.name);
    const expectedStyle = sharp ? 'sharp' : 'soft';

    await page.goto('/');
    await expect(page.locator('body.storefront')).toHaveAttribute('data-storefront-style', expectedStyle);
    await expectNoGlobalOverflow(page);

    const booking = page.getByTestId('header-booking');
    if (await booking.isVisible()) {
        expect(await radiusInPixels(booking)).toBe(sharp ? 2 : 12);
    }

    const chooser = page.getByTestId('catalog-foundation');
    await expect(chooser).toBeVisible();
    const chooserRadius = await radiusInPixels(chooser.locator('.et-v3-store').first());
    expect(chooserRadius).toBe(sharp ? 2 : 28);

    await page.goto('/dekk');
    const filters = await ensureCatalogFiltersVisible(page);
    await expect(filters).toBeVisible();
    const filterPanel = page.locator('.et-production-filter__panel');
    const expectedFilterRadius = sharp ? 2 : (isMobileProject(testInfo.project.name) ? 24 : 16);
    expect(await radiusInPixels(filterPanel)).toBe(expectedFilterRadius);

    const firstCard = page.getByTestId('catalog-product').first();
    await expect(firstCard).toBeVisible();
    expect(await radiusInPixels(firstCard)).toBe(sharp ? 2 : 18);
    await expectNoGlobalOverflow(page);

    if (isMobileProject(testInfo.project.name)) {
        await page.getByTestId('catalog-filter-close').click();
        await expect(page.getByTestId('catalog-filter-drawer')).not.toHaveAttribute('open', '');
    }
});

test('sharp comparison remains private and includes the Russian QA route', async ({ page }, testInfo) => {
    test.skip(!isSharpProject(testInfo.project.name), 'Sharp-host privacy contract.');

    await page.goto('/ru');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.locator('body.storefront')).toHaveAttribute('data-storefront-style', 'sharp');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expectNoGlobalOverflow(page);
});
