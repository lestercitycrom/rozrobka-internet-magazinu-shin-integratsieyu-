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

    const chooser = page.getByTestId('catalog-foundation');
    await expect(chooser).toBeVisible();
    const chooserRadius = await radiusInPixels(chooser.locator('.et-v3-store').first());
    expect(chooserRadius).toBe(2);

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
