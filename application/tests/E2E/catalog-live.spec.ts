import { expect, test } from '@playwright/test';
import { ensureCatalogFiltersVisible, expectNoGlobalOverflow, localePrefix, productCard } from './helpers';

const tireSku = '000205550016';

test('live tire catalog preserves URL, history, controls and reset without a document reload', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/dekk`);
    const root = page.locator('[data-catalog-live-root]');
    await expect(root).toHaveAttribute('data-catalog-live-ready', 'true');
    const marker = await page.evaluate(() => {
        const value = crypto.randomUUID();
        (window as Window & { __catalogMarker?: string }).__catalogMarker = value;
        return value;
    });

    let form = await ensureCatalogFiltersVisible(page);
    const drawer = page.getByTestId('catalog-filter-drawer');
    const initiallyOpen = await drawer.getAttribute('open');
    await form.locator('select[name="width"]').selectOption('205');
    await expect(page).toHaveURL(/width=205/);
    await expect(root).toHaveAttribute('aria-busy', 'false');
    await expect(productCard(page, tireSku)).toBeVisible();
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogMarker?: string }).__catalogMarker)).toBe(marker);

    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('205');
    if (initiallyOpen !== null) await expect(drawer).toHaveAttribute('open', '');

    await page.goBack();
    await expect(page).not.toHaveURL(/width=205/);
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('');
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogMarker?: string }).__catalogMarker)).toBe(marker);

    await page.goForward();
    await expect(page).toHaveURL(/width=205/);
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('205');

    await form.getByTestId('catalog-filter-reset').click();
    await expect(page).toHaveURL(new RegExp(`${prefix.replace('/', '\\/')}\/dekk$`));
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('');
    await expectNoGlobalOverflow(page);
});

test('live wheel catalog combines dimensions and restores an empty state through history', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/felger`);
    let form = await ensureCatalogFiltersVisible(page);
    await form.locator('select[name="diameter"]').selectOption('18');
    await expect(page).toHaveURL(/diameter=18/);
    form = await ensureCatalogFiltersVisible(page);
    await form.locator('input[name="has_image"]').check();
    await expect(page).toHaveURL(/has_image=1/);
    await expect(page.getByTestId('catalog-empty')).toBeVisible();

    await page.goBack();
    await expect(page).not.toHaveURL(/has_image=1/);
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('input[name="has_image"]')).not.toBeChecked();
    await expect(productCard(page, 'DEMO-WHEEL-18112')).toBeVisible();
    await expectNoGlobalOverflow(page);
});
