import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { cartLine, expectNoGlobalOverflow, productCard } from './helpers';

const evidenceRoot = path.resolve(process.cwd(), 'tmp/customer-shared/evidence');
const ordinarySku = '000205550016';
const fixedSku = 'NDI-TEST-4765205551649102';

type CustomerSharedMetadata = {
    designProfile: 'modern' | 'classic';
    locale: 'nb' | 'ru';
    catalogMode: 'live' | 'snapshot';
    remoteReadOnly: boolean;
};

const diagnostics = new WeakMap<Page, string[]>();
const metadata = (info: TestInfo): CustomerSharedMetadata => info.project.metadata as CustomerSharedMetadata;
const prefix = (info: TestInfo): string => metadata(info).locale === 'ru' ? '/ru' : '';
const mobile = (page: Page): boolean => page.viewportSize()!.width < 768;

async function capture(target: Locator | Page, info: TestInfo, label: string, fullPage = false): Promise<void> {
    await mkdir(evidenceRoot, { recursive: true });
    const destination = path.join(evidenceRoot, `${info.project.name}-${label}.png`);
    if (fullPage) await (target as Page).screenshot({ path: destination, animations: 'disabled', fullPage: true });
    else await (target as Locator).screenshot({ path: destination, animations: 'disabled' });
}

function installDiagnostics(page: Page, baseURL: string): void {
    const failures: string[] = [];
    const origin = new URL(baseURL).origin;
    diagnostics.set(page, failures);
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('console', message => {
        if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) {
            failures.push(`console: ${message.text()}`);
        }
    });
    page.on('response', response => {
        const request = response.request();
        const url = new URL(response.url());
        if (url.origin === origin
            && response.status() >= 400
            && ['document', 'stylesheet', 'script', 'image', 'font', 'media'].includes(request.resourceType())) {
            failures.push(`${response.status()} ${request.resourceType()}: ${url.pathname}`);
        }
    });
}

async function blockUnexpectedTraffic(page: Page, baseURL: string, readOnly: boolean): Promise<void> {
    const origin = new URL(baseURL).origin;
    await page.route('**/*', route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== origin) return route.abort('blockedbyclient');
        if (readOnly && !['GET', 'HEAD'].includes(request.method())) return route.abort('blockedbyclient');
        return route.continue();
    });
}

async function expectExactOverlayBounds(root: Locator, overlay: Locator): Promise<void> {
    const [rootBox, overlayBox] = await Promise.all([root.boundingBox(), overlay.boundingBox()]);
    expect(rootBox).not.toBeNull();
    expect(overlayBox).not.toBeNull();
    expect(overlayBox!.x).toBeGreaterThanOrEqual(rootBox!.x);
    expect(overlayBox!.y).toBeGreaterThanOrEqual(rootBox!.y);
    expect(overlayBox!.x + overlayBox!.width).toBeLessThanOrEqual(rootBox!.x + rootBox!.width);
    expect(overlayBox!.y + overlayBox!.height).toBeLessThanOrEqual(rootBox!.y + rootBox!.height);
    for (const key of ['x', 'y', 'width', 'height'] as const) {
        expect(Math.abs(rootBox![key] - overlayBox![key]), `${key} must match the owning card inside its border`).toBeLessThanOrEqual(2);
    }
}

async function expectFocusCycle(page: Page, overlay: Locator): Promise<void> {
    const focusable = overlay.locator('a[href]:visible, button:not([disabled]):visible, input:not([disabled]):visible, select:not([disabled]):visible, textarea:not([disabled]):visible, [tabindex]:not([tabindex="-1"]):visible');
    const count = await focusable.count();
    expect(count).toBeGreaterThanOrEqual(2);
    await focusable.last().focus();
    await page.keyboard.press('Tab');
    await expect(focusable.first()).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(focusable.last()).toBeFocused();
}

async function makeOverlayScrollable(overlay: Locator): Promise<void> {
    const surface = overlay.locator('.et-card-overlay__surface');
    await surface.evaluate(element => {
        const paragraph = document.createElement('p');
        paragraph.dataset.testLongOverlayContent = '';
        paragraph.textContent = 'Readable long content. '.repeat(240);
        element.append(paragraph);
    });
    await expect.poll(() => surface.evaluate(element => element.scrollHeight > element.clientHeight)).toBe(true);
    await surface.evaluate(element => { element.scrollTop = element.scrollHeight; });
    expect(await surface.evaluate(element => element.scrollTop)).toBeGreaterThan(0);
}

test.beforeEach(async ({ page, baseURL }, info) => {
    installDiagnostics(page, baseURL!);
    await blockUnexpectedTraffic(page, baseURL!, metadata(info).remoteReadOnly);
});

test.afterEach(async ({ page }) => {
    expect(diagnostics.get(page) ?? [], 'browser errors and same-origin asset failures').toEqual([]);
});

test('header, contact language, partner logos and pending Snapchat remain coherent', async ({ page }, info) => {
    const localePrefix = prefix(info);
    await page.goto(localePrefix || '/');
    await page.evaluate(() => document.fonts.ready);

    const expectedContact = metadata(info).locale === 'ru' ? 'Связаться с нами' : 'Kontakt Oss';
    if (mobile(page)) {
        await page.locator('[data-menu-toggle]').click();
        const navigation = page.getByTestId('mobile-navigation');
        await expect(navigation.locator('.et-mobile-menu__label').filter({ hasText: expectedContact })).toHaveText(expectedContact);
        const search = navigation.locator('.et-site-search-form--mobile');
        await expect(search).toBeVisible();
        await expect(search).toHaveAttribute('method', /get/i);
        expect(new URL((await search.getAttribute('action'))!, page.url()).pathname).toBe(`${localePrefix}/sok`);
        const tyreSearchLink = search.locator('.et-site-search__store-link');
        await expect(tyreSearchLink.locator('svg')).toHaveCount(1);
        await expect.poll(() => tyreSearchLink.evaluate(element => getComputedStyle(element, '::after').content)).toBe('none');
        await search.scrollIntoViewIfNeeded();
        await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
        await capture(search, info, 'mobile-header-search');
        await page.locator('[data-menu-toggle]').press('Escape');
    } else {
        const contactLink = page.getByTestId('desktop-navigation').locator('a').filter({ hasText: expectedContact });
        await expect(contactLink).toBeVisible();
        await expect(contactLink).toContainText(expectedContact);
        const searchRoot = page.locator('[data-site-search]');
        const opener = searchRoot.locator('[data-site-search-toggle]');
        const panel = searchRoot.locator('[data-site-search-panel]');
        expect(new URL((await opener.getAttribute('href'))!, page.url()).pathname).toBe(`${localePrefix}/sok`);
        await opener.click();
        await expect(panel).toHaveAttribute('aria-hidden', 'false');
        await expect(panel.locator('input[name="q"]')).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(panel).toHaveAttribute('aria-hidden', 'true');
        await expect(opener).toBeFocused();

        if (metadata(info).locale === 'ru') {
            await page.setViewportSize({ width: 1280, height: 800 });
            await page.reload();
            await page.evaluate(() => document.fonts.ready);
            const navigation = page.getByTestId('desktop-navigation');
            const actions = page.locator('.et-header__actions');
            await expect(navigation).toBeVisible();
            await expect(actions).toBeVisible();
            const [navigationBox, actionsBox] = await Promise.all([navigation.boundingBox(), actions.boundingBox()]);
            expect(navigationBox).not.toBeNull();
            expect(actionsBox).not.toBeNull();
            expect(navigationBox!.x + navigationBox!.width, 'RU desktop navigation must end before header actions begin').toBeLessThanOrEqual(actionsBox!.x);
            expect(actionsBox!.x + actionsBox!.width, 'RU desktop header actions must remain inside the viewport').toBeLessThanOrEqual(1280);
            await expectNoGlobalOverflow(page);
            await capture(page.locator('.et-header'), info, 'header-1280');
        }
    }

    const pendingSnapchat = page.getByTestId('social-link-pending').filter({ hasText: 'Snapchat' });
    await expect(pendingSnapchat).toBeVisible();
    const partnerGrid = page.getByTestId('partner-logo-grid');
    await expect(partnerGrid).toBeVisible();
    await expect(partnerGrid.locator(':scope > a, :scope > span')).toHaveCount(4);
    const columns = await partnerGrid.evaluate(element => getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/));
    expect(columns).toHaveLength(2);
    await partnerGrid.scrollIntoViewIfNeeded();
    await expect.poll(() => partnerGrid.locator('img').evaluateAll(images => images.every(image => {
        const logo = image as HTMLImageElement;
        return logo.complete && logo.naturalWidth > 0 && logo.naturalHeight > 0;
    }))).toBe(true);
    await expectNoGlobalOverflow(page);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await capture(partnerGrid, info, 'partner-logos');
});

test('site search handles locale terms, real destinations, anchors and malformed queries', async ({ page }, info) => {
    const localePrefix = prefix(info);
    const terms = metadata(info).locale === 'ru'
        ? ['цена', 'замена масла', 'хранение колес']
        : ['pris', 'oljeskift', 'dekkhotell'];
    let followedAnchor = false;

    for (const term of terms) {
        await test.step(term, async () => {
            await page.goto(`${localePrefix}/sok?q=${encodeURIComponent(term)}`);
            const results = page.getByTestId('site-search-results');
            await expect(results).toBeVisible();
            await expect(results.locator('li')).not.toHaveCount(0);
            await expect(page.getByTestId('quick-finder')).toBeVisible();
            const links = results.locator('a');
            const hrefs = await links.evaluateAll(nodes => nodes.map(node => (node as HTMLAnchorElement).href));
            expect(hrefs.every(href => new URL(href).origin === new URL(page.url()).origin)).toBe(true);

            const anchoredHref = hrefs.find(href => new URL(href).hash !== '');
            const destination = anchoredHref ?? hrefs[0];
            await page.goto(destination);
            expect(new URL(page.url()).pathname).not.toBe(`${localePrefix}/sok`);
            const hash = new URL(destination).hash;
            if (hash) {
                followedAnchor = true;
                const anchor = page.locator(hash);
                await expect(anchor).toBeAttached();
                await expect(page).toHaveURL(new RegExp(`${hash.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
                await anchor.scrollIntoViewIfNeeded();
                await expect(anchor).toBeInViewport();
            }
            await expectNoGlobalOverflow(page);
        });
    }

    expect(followedAnchor, 'at least one requested search term must expose an addressable result anchor').toBe(true);
    await page.goto(`${localePrefix}/sok?q=${encodeURIComponent(terms[1])}`);
    await capture(page, info, 'search-results', true);

    for (const suffix of ['', '?q=%20%20%20', '?q%5B%5D=invalid']) {
        await page.goto(`${localePrefix}/sok${suffix}`);
        await expect(page.getByTestId('site-search-results')).toHaveCount(0);
        await expect(page.getByTestId('quick-finder')).toBeVisible();
    }
});

test('no-JavaScript search and card detail fallbacks remain usable', async ({ browser, baseURL }, info) => {
    test.skip(pageWidth(info) !== 1440, 'One no-JavaScript desktop pass per profile and locale is sufficient.');
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    installDiagnostics(page, baseURL!);
    await blockUnexpectedTraffic(page, baseURL!, metadata(info).remoteReadOnly);
    const localePrefix = prefix(info);

    await page.goto(localePrefix || '/');
    const searchLink = page.locator('[data-site-search-toggle]');
    await expect(searchLink).toBeVisible();
    expect(new URL((await searchLink.getAttribute('href'))!, page.url()).pathname).toBe(`${localePrefix}/sok`);
    await searchLink.click();
    await expect(page).toHaveURL(new RegExp(`${localePrefix || ''}/sok$`));
    await expect(page.locator('.et-site-search-form--page')).toBeVisible();

    await page.goto(localePrefix || '/');
    await expect(page.locator('[data-card-overlay-fallback] summary').first()).toBeVisible();
    await expect(page.locator('details.et-v3-logistics-noscript summary')).toBeVisible();
    expect(diagnostics.get(page) ?? [], 'no-JavaScript browser errors and asset failures').toEqual([]);
    await context.close();
});

test('dekkhotell and promotion overlays stay inside cards, trap focus and freeze media/carousel state', async ({ page }, info) => {
    const localePrefix = prefix(info);
    const exerciseVideo = metadata(info).locale === 'nb' && page.viewportSize()!.width === 1440;
    if (exerciseVideo) await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(localePrefix || '/');

    const hotelRoot = page.locator('[data-card-overlay-root][data-card-overlay-kind="dekkhotell"]');
    const hotelOpener = hotelRoot.locator('[data-card-overlay-open]');
    const hotelOverlay = hotelRoot.locator('[data-card-overlay]');
    await hotelRoot.scrollIntoViewIfNeeded();

    let videoWasPlaying = false;
    const video = hotelRoot.locator('[data-homepage-video-element]');
    const videoControl = hotelRoot.locator('[data-homepage-video-control]');
    if (exerciseVideo && await video.count() > 0) {
        await videoControl.click();
        await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);
        videoWasPlaying = true;
    }

    await hotelOpener.click();
    await expect(hotelOverlay).toHaveJSProperty('open', true);
    await expect(hotelOverlay.locator('[data-card-overlay-close]')).toBeFocused();
    await expectExactOverlayBounds(hotelRoot, hotelOverlay);
    await expectFocusCycle(page, hotelOverlay);
    await hotelOverlay.locator('.et-card-overlay__surface').evaluate(element => { element.scrollTop = 0; });
    await capture(hotelRoot, info, 'dekkhotell-overlay');
    await makeOverlayScrollable(hotelOverlay);
    if (videoWasPlaying) await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await page.keyboard.press('Escape');
    await expect(hotelOverlay).toHaveJSProperty('open', false);
    await expect(hotelOpener).toBeFocused();
    if (videoWasPlaying) await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);

    const promotionRoots = page.locator('[data-card-overlay-root][data-card-overlay-kind="promotion"]');
    expect(await promotionRoots.count()).toBeGreaterThan(0);
    const firstPromotion = promotionRoots.first();
    const promotionOpener = firstPromotion.locator('[data-card-overlay-open]');
    const promotionOverlay = firstPromotion.locator('[data-card-overlay]');
    await firstPromotion.scrollIntoViewIfNeeded();
    await promotionOpener.click();
    await expect(promotionOverlay).toHaveJSProperty('open', true);
    await expectExactOverlayBounds(firstPromotion, promotionOverlay);
    await expectFocusCycle(page, promotionOverlay);
    await promotionOverlay.locator('.et-card-overlay__surface').evaluate(element => { element.scrollTop = 0; });
    await capture(firstPromotion, info, 'promotion-overlay');
    await makeOverlayScrollable(promotionOverlay);

    const track = page.locator('[data-promotion-track]');
    const transformBefore = await track.evaluate(element => getComputedStyle(element).transform);
    const next = page.locator('[data-promotion-next]');
    if (await next.count() > 0) {
        await next.evaluate((button: HTMLButtonElement) => button.click());
        expect(await track.evaluate(element => getComputedStyle(element).transform)).toBe(transformBefore);
    }

    const secondIsUserReachable = await promotionRoots.count() > 1
        && await promotionRoots.nth(1).getAttribute('inert') === null;
    if (secondIsUserReachable) {
        const secondPromotion = promotionRoots.nth(1);
        const secondOverlay = secondPromotion.locator('[data-card-overlay]');
        await secondPromotion.locator('[data-card-overlay-open]').evaluate((button: HTMLButtonElement) => button.click());
        await expect(promotionOverlay).toHaveJSProperty('open', false);
        await expect(secondOverlay).toHaveJSProperty('open', true);
        await expect(secondOverlay.locator('[data-card-overlay-close]')).toBeFocused();
        if (await next.count() > 0) {
            const switchedTransform = await track.evaluate(element => getComputedStyle(element).transform);
            await next.evaluate((button: HTMLButtonElement) => button.click());
            expect(await track.evaluate(element => getComputedStyle(element).transform)).toBe(switchedTransform);
        }
        await page.keyboard.press('Escape');
        await expect(secondOverlay).toHaveJSProperty('open', false);
        const secondOpener = secondPromotion.locator('[data-card-overlay-open]');
        await expect(secondOpener).toHaveAttribute('aria-expanded', 'false');
        await expect(secondOpener).toBeFocused();
    } else {
        await page.keyboard.press('Escape');
        await expect(promotionOpener).toBeFocused();
    }
    await expectNoGlobalOverflow(page);
});

test('ordinary catalogue quantity reaches cart while the NDI test item remains fixed at two', async ({ page }, info) => {
    const localePrefix = prefix(info);
    await page.goto(`${localePrefix}/dekk`);

    if (metadata(info).catalogMode !== 'live') {
        const ordinaryCards = page.locator(`[data-testid="catalog-product"]:not([data-product-sku="${fixedSku}"])`);
        await expect(ordinaryCards).not.toHaveCount(0);
        await expect(ordinaryCards.getByTestId('snapshot-price-note').first()).toBeVisible();
        await expect(ordinaryCards.getByTestId('catalog-product-quantity')).toHaveCount(0);
        await expect(ordinaryCards.getByTestId('catalog-add-to-cart')).toHaveCount(0);

        await page.goto(`${localePrefix}/dekk?q=${fixedSku}`);
        const fixed = productCard(page, fixedSku);
        const fixedQuantity = fixed.getByTestId('catalog-product-quantity');
        await expect(fixed).toBeVisible();
        await expect(fixedQuantity).toHaveValue('2');
        await expect(fixedQuantity).toHaveAttribute('min', '2');
        await expect(fixedQuantity).toHaveAttribute('max', '2');
        await expect(fixedQuantity).toHaveAttribute('readonly', '');
        await expect(fixed.getByTestId('catalog-integration-test-quantity-note')).toContainText('2');
        return;
    }

    const ordinary = productCard(page, ordinarySku);
    await expect(ordinary).toBeVisible();
    const ordinaryQuantity = ordinary.getByTestId('catalog-product-quantity');
    await expect(ordinaryQuantity).toHaveValue('1');
    await expect(ordinaryQuantity).not.toHaveAttribute('readonly', '');
    await ordinaryQuantity.fill('3');
    await ordinary.getByTestId('catalog-add-to-cart').click();
    await page.goto(`${localePrefix}/handlekurv`);
    await expect(cartLine(page, ordinarySku).getByTestId('cart-line-quantity')).toHaveValue('3');

    await page.goto(`${localePrefix}/dekk?q=${fixedSku}`);
    const fixed = productCard(page, fixedSku);
    await expect(fixed).toBeVisible();
    const fixedQuantity = fixed.getByTestId('catalog-product-quantity');
    await expect(fixedQuantity).toHaveValue('2');
    await expect(fixedQuantity).toHaveAttribute('min', '2');
    await expect(fixedQuantity).toHaveAttribute('max', '2');
    await expect(fixedQuantity).toHaveAttribute('readonly', '');
    await expect(fixed.getByTestId('catalog-integration-test-quantity-note')).toContainText('2');
    await fixed.getByTestId('catalog-add-to-cart').click();
    await page.goto(`${localePrefix}/handlekurv`);
    const fixedCartLine = cartLine(page, fixedSku);
    await expect(fixedCartLine.getByTestId('cart-line-quantity')).toHaveValue('2');
    await expect(fixedCartLine.getByTestId('cart-line-quantity')).toHaveAttribute('readonly', '');

    await page.goto(`${localePrefix}/produkter/${fixedSku}`);
    const detailQuantity = page.getByTestId('product-quantity');
    await expect(detailQuantity).toHaveValue('2');
    await expect(detailQuantity).toHaveAttribute('min', '2');
    await expect(detailQuantity).toHaveAttribute('max', '2');
    await expect(detailQuantity).toHaveAttribute('readonly', '');
    await expect(page.getByTestId('integration-test-quantity-note')).toContainText('2');
    await expectNoGlobalOverflow(page);
    await capture(page, info, 'fixed-quantity-product', true);
});

function pageWidth(info: TestInfo): number {
    return (info.project.use.viewport as { width: number } | undefined)?.width ?? 0;
}
