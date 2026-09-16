import { expect, test, type Locator, type Page } from '@playwright/test';
import { ensureCatalogFiltersVisible, expectNoGlobalOverflow, productCard } from './helpers';

const live = Boolean(process.env.FILTERS_LIVE_URL);
const prefixFor = (name: string) => name.includes('-ru-') ? '/ru' : '';
const isMobile = (page: Page) => (page.viewportSize()?.width ?? 1440) < 768;
const params = (page: Page) => new URL(page.url()).searchParams;

test.beforeEach(async ({ page, baseURL }) => {
    const origin = new URL(baseURL!).origin;
    await page.route('**/*', route => {
        const request = route.request();
        const url = new URL(request.url());
        if (!['GET', 'HEAD'].includes(request.method()) || (url.protocol.startsWith('http') && url.origin !== origin)
            || /^\/(?:ru\/)?betaling\//.test(url.pathname)) return route.abort('blockedbyclient');
        return route.continue();
    });
});

async function ready(page: Page): Promise<void> {
    await expect(page.locator('[data-catalog-live-root]')).toHaveAttribute('data-catalog-live-ready', 'true');
    await expect(page.locator('[data-catalog-live-root]')).toHaveAttribute('aria-busy', 'false');
}

async function selected(form: Locator, field: string, value: string): Promise<void> {
    const control = form.locator(`select[name="${field}"]`);
    await expect(control).toHaveValue(value);
    await expect(control.locator('option:checked')).toHaveText(value);
    await expect(control.locator('option:checked')).toBeEnabled();
}

async function applyDraft(page: Page): Promise<void> {
    if (isMobile(page)) await page.getByTestId('catalog-filter-apply').click();
    await ready(page);
}

async function selectFilter(page: Page, field: string, value: string): Promise<void> {
    const form = await ensureCatalogFiltersVisible(page);
    await form.locator(`select[name="${field}"]`).selectOption(value);
    await applyDraft(page);
    await expect.poll(() => params(page).get(field)).toBe(value);
}

async function chip(page: Page, key: string): Promise<Locator> {
    const chips = page.getByTestId('active-filters').locator('a.et-chip');
    const hrefs = await chips.evaluateAll(elements => elements.map(element => (element as HTMLAnchorElement).href));
    const index = hrefs.findIndex(href => !new URL(href).searchParams.has(key));
    expect(index, `Removal chip for ${key}`).toBeGreaterThanOrEqual(0);
    return chips.nth(index);
}

test('homepage absent width remains enabled and selected with an honest empty result', async ({ page }, info) => {
    test.skip(live, 'Hermetic fixture contract; live audit below is read-only navigation only.');
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/`);
    await page.locator('#home-finder-size-tab').click();
    await page.getByTestId('finder-width').selectOption('125');
    await page.getByTestId('finder-aspect').selectOption('60');
    await page.getByTestId('finder-rim').selectOption('16');
    await page.locator('#home-finder-size-panel button[type="submit"]').click();
    await ready(page);
    await expect(page.getByTestId('catalog-empty')).toBeVisible();
    await expect(page.getByTestId('catalog-product')).toHaveCount(0);
    const form = await ensureCatalogFiltersVisible(page);
    for (const [field, value] of Object.entries({ width: '125', aspect: '60', rim: '16' })) {
        await selected(form, field, value);
        expect(params(page).get(field)).toBe(value);
    }
    await expect(await chip(page, 'width')).toContainText('125');
    await expectNoGlobalOverflow(page);
    await page.screenshot({ path: info.outputPath('homepage-125-empty-selected.png'), fullPage: true });
});

test('integer and decimal tyre inputs share canonical controls and server-refreshed live state', async ({ page }, info) => {
    test.skip(live, 'Hermetic filter fixture only.');
    const prefix = prefixFor(info.project.name);
    for (const query of ['width=205&aspect=60&rim=16', 'width=205.00&aspect=60.00&rim=16.00']) {
        await page.goto(`${prefix}/dekk?${query}`);
        await ready(page);
        const form = await ensureCatalogFiltersVisible(page);
        for (const [field, value] of Object.entries({ width: '205', aspect: '60', rim: '16' })) await selected(form, field, value);
        await expect(productCard(page, '000205550016')).toBeVisible();
        const values = await form.locator('select[name="aspect"] option').evaluateAll(options => options.map(option => (option as HTMLOptionElement).value).filter(Boolean));
        expect(values).toContain('60');
        expect(values).not.toContain('60.00');
    }
    if (isMobile(page)) await page.getByTestId('catalog-filter-close').click();
    const marker = await page.evaluate(() => {
        const value = crypto.randomUUID();
        (window as Window & { __filterMarker?: string }).__filterMarker = value;
        return value;
    });
    await page.locator('[data-catalog-live-sort] select').selectOption('title');
    await expect.poll(() => params(page).get('sort')).toBe('title');
    await ready(page);
    const form = await ensureCatalogFiltersVisible(page);
    for (const [field, value] of Object.entries({ width: '205', aspect: '60', rim: '16' })) {
        await selected(form, field, value);
        expect(params(page).get(field)).toBe(value);
    }
    await expect(form.locator('input[name="sort"]')).toHaveValue('title');
    await expect.poll(() => page.evaluate(() => (window as Window & { __filterMarker?: string }).__filterMarker)).toBe(marker);
    await expectNoGlobalOverflow(page);
});

test('wheel decimal dimensions and zero or negative ET retain correct options and chips', async ({ page }, info) => {
    test.skip(live, 'Hermetic filter fixture only.');
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/felger?width=7.50&diameter=20.00&bolt_holes=5&pcd=120.00&center_bore=60.10&et=0.00`);
    await ready(page);
    let form = await ensureCatalogFiltersVisible(page);
    for (const [field, value] of Object.entries({ width: '7.5', diameter: '20', bolt_holes: '5', pcd: '120', center_bore: '60.1', et: '0' })) await selected(form, field, value);
    await expect(productCard(page, 'FILTER77-WHEEL-ZERO')).toBeVisible();
    await expect(page.getByTestId('catalog-product')).toHaveCount(1);
    if (isMobile(page)) await page.getByTestId('catalog-filter-close').click();
    await expect(await chip(page, 'et')).toContainText(/\(ET\):\s*0\b/);
    await (await chip(page, 'et')).click();
    await expect.poll(() => params(page).has('et')).toBe(false);
    await ready(page);
    await expect(page.getByTestId('catalog-product')).toHaveCount(2);
    await page.goBack();
    await ready(page);
    form = await ensureCatalogFiltersVisible(page);
    await selected(form, 'et', '0');
    await selectFilter(page, 'et', '-10');
    await expect(productCard(page, 'FILTER77-WHEEL-NEGATIVE')).toBeVisible();
    await expect(page.getByTestId('catalog-product')).toHaveCount(1);
    if (isMobile(page) && await page.getByTestId('catalog-filters').isVisible()) await page.getByTestId('catalog-filter-close').click();
    await expect(await chip(page, 'et')).toContainText('-10');
    await expectNoGlobalOverflow(page);
    await page.screenshot({ path: info.outputPath('wheel-negative-et-canonical.png'), fullPage: true });
});

test('sorting survives filter changes, chip removal and history; explicit reset clears it', async ({ page }, info) => {
    test.skip(live, 'Hermetic filter fixture only.');
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/dekk?width=125&aspect=60&rim=16`);
    await ready(page);
    await page.locator('[data-catalog-live-sort] select').selectOption('brand');
    await expect.poll(() => params(page).get('sort')).toBe('brand');
    await ready(page);
    await selectFilter(page, 'width', '205');
    expect(params(page).get('sort')).toBe('brand');
    await page.goBack();
    await expect.poll(() => params(page).get('width')).toBe('125');
    await ready(page);
    await selected(await ensureCatalogFiltersVisible(page), 'width', '125');
    await page.goForward();
    await expect.poll(() => params(page).get('width')).toBe('205');
    await ready(page);
    await selected(await ensureCatalogFiltersVisible(page), 'width', '205');
    if (isMobile(page)) await page.getByTestId('catalog-filter-close').click();
    await (await chip(page, 'width')).click();
    await expect.poll(() => params(page).has('width')).toBe(false);
    expect(params(page).get('sort')).toBe('brand');
    await ready(page);
    await page.getByTestId('catalog-filter-reset-all').click();
    await expect.poll(() => new URL(page.url()).search).toBe('');
    await ready(page);
    await expect(page.locator('[data-catalog-live-sort] select')).toHaveValue('');
    const form = await ensureCatalogFiltersVisible(page);
    for (const field of ['width', 'aspect', 'rim']) await expect(form.locator(`select[name="${field}"]`)).toHaveValue('');
    await expect(form.locator('input[name="sort"]')).toHaveValue('');
});

test('pagination, search and checkbox changes preserve canonical filters and reset page', async ({ page }, info) => {
    test.skip(live, 'Hermetic pagination and stock fixtures only.');
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/dekk?width=205&aspect=60&rim=16&sort=title`);
    await ready(page);
    await page.locator('.et-catalog-pagination a[href*="page=2"]').click();
    await expect.poll(() => params(page).get('page')).toBe('2');
    await ready(page);
    let form = await ensureCatalogFiltersVisible(page);
    await form.locator('input[name="q"]').fill('Filter77');
    await applyDraft(page);
    await expect.poll(() => params(page).get('q')).toBe('Filter77');
    expect(params(page).has('page')).toBe(false);
    expect(params(page).get('sort')).toBe('title');
    for (const [field, value] of Object.entries({ width: '205', aspect: '60', rim: '16' })) expect(params(page).get(field)).toBe(value);
    for (const field of ['in_stock', 'has_image']) {
        form = await ensureCatalogFiltersVisible(page);
        await form.locator(`input[name="${field}"]`).check();
        await applyDraft(page);
        await expect.poll(() => params(page).get(field)).toBe('1');
        await expect(page.getByTestId('catalog-empty')).toBeVisible();
        form = await ensureCatalogFiltersVisible(page);
        await expect(form.locator(`input[name="${field}"]`)).toBeChecked();
        await form.locator(`input[name="${field}"]`).uncheck();
        await applyDraft(page);
        await expect.poll(() => params(page).has(field)).toBe(false);
        await expect(page.getByTestId('catalog-product').first()).toBeVisible();
    }
    expect(params(page).get('q')).toBe('Filter77');
    expect(params(page).get('sort')).toBe('title');
    await expectNoGlobalOverflow(page);
});

test('mobile canonical draft waits for Apply, survives failure and retries once', async ({ page }, info) => {
    test.skip(live || !isMobile(page), 'Isolated mobile draft/error contract.');
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/dekk?sort=title`);
    await ready(page);
    const form = await ensureCatalogFiltersVisible(page);
    let attempts = 0;
    let failNext = true;
    await page.route(`**${prefix}/dekk?*`, route => {
        if (route.request().resourceType() !== 'fetch') return route.continue();
        attempts++;
        if (failNext) return route.fulfill({ status: 503, contentType: 'text/plain', body: 'Synthetic local filter failure' });
        return route.continue();
    });
    const historyLength = await page.evaluate(() => history.length);
    await form.locator('select[name="width"]').selectOption('205');
    await form.locator('select[name="aspect"]').selectOption('60');
    await expect(form).toHaveAttribute('data-catalog-draft', 'true');
    expect(params(page).has('width')).toBe(false);
    expect(attempts).toBe(0);
    expect(await page.evaluate(() => history.length)).toBe(historyLength);
    await form.getByTestId('catalog-filter-apply').click();
    await expect(page.getByTestId('catalog-filter-error')).toBeVisible();
    await expect(page.getByTestId('catalog-filter-drawer')).toHaveAttribute('open', '');
    await selected(form, 'width', '205');
    await selected(form, 'aspect', '60');
    expect(params(page).has('width')).toBe(false);
    failNext = false;
    await form.getByTestId('catalog-filter-apply').click();
    await expect.poll(() => params(page).get('width')).toBe('205');
    await ready(page);
    expect(params(page).get('aspect')).toBe('60');
    expect(params(page).get('sort')).toBe('title');
    expect(attempts).toBe(2);
    await expect(page.getByTestId('catalog-filter-error')).toBeHidden();
    await expect(page.getByTestId('catalog-filter-drawer')).not.toHaveAttribute('open', '');
    await expect(page.locator('.et-result-count')).toBeFocused();
});

test('adjacent pages read-only audit: localization, JS errors and responsive overflow', async ({ page }, info) => {
    test.setTimeout(180_000);
    const prefix = prefixFor(info.project.name);
    const observations: object[] = [];
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const route of ['/', '/dekk', '/felger', '/dekk-og-felger', '/tjenester', '/priser', '/kontakt', '/om-oss', '/handlekurv', '/admin/login']) {
        const path = route.startsWith('/admin') ? route : `${prefix}${route}`;
        const response = await page.goto(path);
        await expect(page.locator('body')).toBeVisible();
        const status = response?.status();
        expect.soft(status, path).toBeLessThan(400);
        if (!route.startsWith('/admin')) await expect.soft(page.locator('html')).toHaveAttribute('lang', prefix ? 'ru' : 'nb');
        const ui = await page.evaluate(() => {
            const text = document.body.innerText;
            const visibleControls = [...document.querySelectorAll<HTMLElement>('button, label, select')]
                .filter(element => element.getBoundingClientRect().width > 0 && element.getBoundingClientRect().height > 0)
                .map(element => element instanceof HTMLSelectElement ? element.selectedOptions[0]?.textContent?.trim() ?? '' : element.innerText.trim());
            return {
                overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                untranslatedKeys: text.match(/\b(?:storefront|pro|fitment|catalog_correction|auth)\.[a-z_]+(?:\.[a-z_]+)*/g) ?? [],
                norwegianControls: visibleControls.filter(text => /^(Alle|Søk|Velg|Vis produkter|Nullstill|Sorter|Vis filtre)$/.test(text)),
            };
        });
        observations.push({ path, status, ...ui });
        expect.soft(ui.overflow, `${path}: horizontal overflow`).toBeLessThanOrEqual(1);
        expect.soft(ui.untranslatedKeys, `${path}: untranslated translation keys`).toEqual([]);
        if (prefix && !route.startsWith('/admin')) expect.soft(ui.norwegianControls, `${path}: untranslated Norwegian controls`).toEqual([]);
        if (route === '/handlekurv') await expect.soft(page.getByTestId('cart-empty')).toBeVisible();
        if (route === '/dekk-og-felger') await expect.soft(page.locator('form input[name="registration"]').first()).toBeVisible();
        if (route === '/admin/login') await expect.soft(page.locator('input[name="password"]')).toBeVisible();
        await page.screenshot({ path: info.outputPath(`adjacent-${route === '/' ? 'home' : route.slice(1).replaceAll('/', '-')}.png`), fullPage: true });
    }
    await info.attach('adjacent-page-observations', { body: JSON.stringify(observations, null, 2), contentType: 'application/json' });
    expect(errors).toEqual([]);
});
