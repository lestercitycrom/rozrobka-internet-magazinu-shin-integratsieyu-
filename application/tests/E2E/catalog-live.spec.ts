import { expect, test } from '@playwright/test';
import { ensureCatalogFiltersVisible, expectNoGlobalOverflow, isMobileProject, localePrefix, productCard } from './helpers';

const tireSku = '000205550016';

test('live tire catalog preserves URL, history, controls and reset without a document reload', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo.project.name), 'Desktop retains immediate live filtering');
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
    test.skip(isMobileProject(testInfo.project.name), 'Desktop retains immediate live filtering');
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

test('autocomplete remains available and focused after live search updates the results', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/dekk`);
    const form = await ensureCatalogFiltersVisible(page);
    const search = form.locator('input[name="q"]');

    await search.evaluate((element) => {
        (window as Window & { __catalogSearchNode?: Element }).__catalogSearchNode = element;
    });
    await search.fill('Demo');

    if (isMobileProject(testInfo.project.name)) {
        await expect(page).not.toHaveURL(/q=Demo/);
        await expect(form).toHaveAttribute('data-catalog-draft', 'true');
    } else {
        await expect(page).toHaveURL(/q=Demo/);
    }
    await expect(page.locator('#catalog-suggestions option')).not.toHaveCount(0);
    await expect(search).toBeFocused();
    await expect.poll(() => page.evaluate(() =>
        (window as Window & { __catalogSearchNode?: Element }).__catalogSearchNode === document.querySelector('input[name="q"]')
    )).toBe(true);
});

test('mobile filters remain draft-only until one explicit Apply request succeeds', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo.project.name) || testInfo.project.name.includes('cart-'), 'Mobile catalogue contract');

    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/dekk`);
    const form = await ensureCatalogFiltersVisible(page);
    const drawer = page.getByTestId('catalog-filter-drawer');
    const apply = page.getByTestId('catalog-filter-apply');
    const initialHistoryLength = await page.evaluate(() => history.length);
    let catalogRequests = 0;

    page.on('request', (request) => {
        if (request.resourceType() === 'fetch' && new URL(request.url()).pathname.endsWith('/dekk')) catalogRequests += 1;
    });

    await form.locator('select[name="width"]').selectOption('205');
    await form.locator('select[name="aspect"]').selectOption('55');
    await expect(form).toHaveAttribute('data-catalog-draft', 'true');
    await expect(page).not.toHaveURL(/width=205|aspect=55/);
    expect(await page.evaluate(() => history.length)).toBe(initialHistoryLength);
    expect(catalogRequests).toBe(0);

    await form.getByTestId('catalog-filter-reset').click();
    await expect(form.locator('select[name="width"]')).toHaveValue('');
    await expect(form.locator('select[name="aspect"]')).toHaveValue('');
    await expect(page).not.toHaveURL(/width=205|aspect=55/);
    expect(catalogRequests).toBe(0);

    await form.locator('select[name="width"]').selectOption('205');
    await form.locator('select[name="aspect"]').selectOption('55');
    await page.route('**/dekk?*', async (route) => {
        if (route.request().resourceType() !== 'fetch') return route.continue();
        await new Promise((resolve) => setTimeout(resolve, 300));
        await route.continue();
    });

    await apply.click();
    await form.evaluate((element) => (element as HTMLFormElement).requestSubmit());
    await expect(apply).toBeDisabled();
    await expect(apply).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-catalog-apply-busy]')).toBeVisible();
    await expect(page.locator('[data-catalog-loading]')).toHaveAttribute('aria-hidden', 'false');

    await expect(page).toHaveURL(/width=205/);
    await expect(page).toHaveURL(/aspect=55/);
    await expect(drawer).not.toHaveAttribute('open', '');
    await expect(page.locator('.et-result-count')).toBeFocused();
    await expect(apply).not.toBeDisabled();
    await expect(apply).toHaveAttribute('aria-busy', 'false');
    await expect(form).toHaveAttribute('data-catalog-draft', 'false');
    expect(catalogRequests).toBe(1);
    expect(await page.evaluate(() => history.length)).toBe(initialHistoryLength + 1);
    await expectNoGlobalOverflow(page);
});

test('mobile Apply failure preserves the draft and retry succeeds', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-mobile', 'One mobile failure/retry proof is sufficient');

    await page.goto('/dekk');
    const form = await ensureCatalogFiltersVisible(page);
    const drawer = page.getByTestId('catalog-filter-drawer');
    const apply = page.getByTestId('catalog-filter-apply');
    let shouldFail = true;
    let attempts = 0;

    await page.route('**/dekk?*', async (route) => {
        if (route.request().resourceType() !== 'fetch') return route.continue();
        attempts += 1;
        if (shouldFail) return route.fulfill({ status: 503, contentType: 'text/plain', body: 'Synthetic failure' });
        return route.continue();
    });

    await form.locator('select[name="width"]').selectOption('205');
    await apply.click();
    await expect(page.getByTestId('catalog-filter-error')).toBeVisible();
    await expect(drawer).toHaveAttribute('open', '');
    await expect(form.locator('select[name="width"]')).toHaveValue('205');
    await expect(page).not.toHaveURL(/width=205/);
    await expect(apply).toBeEnabled();

    shouldFail = false;
    await apply.click();
    await expect(page).toHaveURL(/width=205/);
    await expect(drawer).not.toHaveAttribute('open', '');
    await expect(page.getByTestId('catalog-filter-error')).toBeHidden();
    expect(attempts).toBe(2);
});

test('mobile history restores only applied filters and discards an unapplied draft', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-mobile', 'One mobile history proof is sufficient');

    await page.goto('/dekk');
    let form = await ensureCatalogFiltersVisible(page);
    await form.locator('select[name="width"]').selectOption('205');
    await form.getByTestId('catalog-filter-apply').click();
    await expect(page).toHaveURL(/width=205/);

    form = await ensureCatalogFiltersVisible(page);
    await form.locator('select[name="aspect"]').selectOption('55');
    await expect(page).not.toHaveURL(/aspect=55/);

    await page.goBack();
    await expect(page).not.toHaveURL(/width=205|aspect=55/);
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('');
    await expect(form.locator('select[name="aspect"]')).toHaveValue('');

    await page.goForward();
    await expect(page).toHaveURL(/width=205/);
    await expect(page).not.toHaveURL(/aspect=55/);
    form = await ensureCatalogFiltersVisible(page);
    await expect(form.locator('select[name="width"]')).toHaveValue('205');
    await expect(form.locator('select[name="aspect"]')).toHaveValue('');
});
