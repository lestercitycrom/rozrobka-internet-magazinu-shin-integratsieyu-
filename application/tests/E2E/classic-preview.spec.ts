import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

const sku = 'NDI-TEST-4765205551649102';
const evidenceRoot = path.resolve(process.cwd(), 'tmp/classic-preview/evidence');

type PreviewMetadata = {
    designProfile: 'modern' | 'classic';
    locale: 'nb' | 'ru';
    remoteReadOnly: boolean;
};

const metadata = (info: TestInfo): PreviewMetadata => info.project.metadata as PreviewMetadata;
const prefix = (info: TestInfo): string => metadata(info).locale === 'ru' ? '/ru' : '';
const isMobile = (page: Page): boolean => page.viewportSize()!.width < 768;

async function capture(locator: Locator | Page, info: TestInfo, label: string, fullPage = false): Promise<void> {
    await mkdir(evidenceRoot, { recursive: true });
    const target = path.join(evidenceRoot, `${info.project.name}-${label}.png`);
    if (fullPage) await (locator as Page).screenshot({ path: target, animations: 'disabled', fullPage: true });
    else await (locator as Locator).screenshot({ path: target, animations: 'disabled' });
}

async function expectContainedFields(page: Page): Promise<void> {
    const failures = await page.locator('input:visible, select:visible, textarea:visible, button:visible').evaluateAll(nodes => nodes
        .map(node => {
            const rect = node.getBoundingClientRect();
            return { tag: node.tagName, name: node.getAttribute('name'), left: rect.left, right: rect.right, width: rect.width, height: rect.height };
        })
        .filter(rect => rect.left < -1 || rect.right > document.documentElement.clientWidth + 1 || rect.width < 1 || rect.height < 1));
    expect(failures, 'visible controls must remain inside the viewport').toEqual([]);
}

test.beforeEach(async ({ page, baseURL }, info) => {
    const origin = new URL(baseURL!).origin;
    const remoteReadOnly = metadata(info).remoteReadOnly;
    await page.route('**/*', route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== origin) return route.abort('blockedbyclient');
        if (remoteReadOnly && !['GET', 'HEAD'].includes(request.method())) return route.abort('blockedbyclient');
        return route.continue();
    });
});

test('profile marker, assets, canonical host and same-host navigation stay isolated', async ({ page, baseURL }, info) => {
    const profile = metadata(info).designProfile;
    const localePrefix = prefix(info);
    await page.goto(localePrefix || '/');
    await page.evaluate(() => document.fonts.ready);

    const stylesheets = await page.locator('link[rel="stylesheet"]').evaluateAll(nodes => nodes.map(node => (node as HTMLLinkElement).href));
    const classicIndex = stylesheets.findIndex(href => /storefront-classic[^/]*\.css(?:\?|$)/.test(href));
    const designMarker = await page.locator('body').getAttribute('data-storefront-design');
    if (metadata(info).remoteReadOnly && profile === 'modern' && designMarker === null) {
        expect(classicIndex).toBe(-1);
    } else {
        expect(designMarker).toBe(profile);
    }
    await expect(page.locator('body')).toHaveAttribute('data-storefront-style', 'sharp');
    if (profile === 'classic') {
        expect(classicIndex).toBeGreaterThan(stylesheets.findIndex(href => /app[^/]*\.css(?:\?|$)/.test(href)));
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
        const canonicalPath = localePrefix ? '/' : new URL(page.url()).pathname;
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', `https://staging-easytire.bethub.fun${canonicalPath === '/' ? '' : canonicalPath}`);
        expect(await page.locator('h1').evaluate(node => getComputedStyle(node).fontFamily)).toContain('Barlow Condensed');
        expect(await page.locator('body').evaluate(node => getComputedStyle(node).fontFamily)).toContain('Ubuntu');
    } else {
        expect(classicIndex).toBe(-1);
        expect(new URL(await page.locator('link[rel="canonical"]').getAttribute('href')!).origin).toBe(new URL(baseURL!).origin);
    }

    const internalNavigation = page.locator('[data-testid="desktop-navigation"] a[href], [data-testid="mobile-navigation"] a[href]');
    for (const href of await internalNavigation.evaluateAll(nodes => nodes.map(node => (node as HTMLAnchorElement).href).filter(href => /^https?:/.test(href)))) {
        expect(new URL(href).origin).toBe(new URL(baseURL!).origin);
    }
    await expectNoGlobalOverflow(page);
});

test('home keeps the boxed finder, numbered cards, hotel dialog and portrait media usable', async ({ page }, info) => {
    test.skip(metadata(info).remoteReadOnly && metadata(info).designProfile === 'modern', 'Legacy primary smoke is limited to stable GET contracts.');
    test.skip(metadata(info).locale === 'ru', 'NB pass captures the shared homepage composition; RU is covered by route/profile checks.');
    const profile = metadata(info).designProfile;
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);

    const finder = page.getByTestId('quick-finder').first();
    await expect(finder).toBeVisible();
    if (profile === 'classic') {
        expect(parseFloat(await finder.evaluate(node => getComputedStyle(node).borderTopWidth))).toBeGreaterThanOrEqual(1);
        await expect(page.locator('.et-v3-service__number').first()).toHaveText('01');
    }

    const hotel = page.getByTestId('home-dekkhotell');
    const videoRoot = hotel.locator('[data-homepage-video]');
    const video = videoRoot.locator('[data-homepage-video-element]');
    await expect(video).toHaveAttribute('preload', 'none');
    await expect(video).not.toHaveAttribute('autoplay', '');
    const poster = videoRoot.locator('.et-v3-logistics-media__poster');
    await expect(poster).toHaveAttribute('width', '720');
    await expect(poster).toHaveAttribute('height', '1280');

    const opener = page.locator('[data-dekkhotell-dialog-open]');
    await opener.click();
    const dialog = page.locator('[data-dekkhotell-dialog]');
    await expect(dialog).toHaveJSProperty('open', true);
    await expect(dialog.locator('[data-dekkhotell-dialog-close]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(opener).toBeFocused();
    await expectNoGlobalOverflow(page);

    if (profile === 'classic' || page.viewportSize()!.width === 1440) {
        await capture(page, info, 'home', true);
    }
    if (profile === 'classic') {
        await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
        await capture(finder, info, 'finder');
        await capture(page.locator('.et-home-service-collection').first(), info, 'services');
        await capture(hotel, info, 'hotel');
    }
    if (profile === 'classic' && page.viewportSize()!.width === 1440) {
        for (const width of [901, 1024]) {
            await page.setViewportSize({ width, height: 900 });
            await page.reload();
            const hero = page.getByTestId('home-hero');
            await expect(hero.getByTestId('quick-finder')).toBeVisible();
            await expectNoGlobalOverflow(page);
            await capture(hero, info, `hero-${width}`);
        }
    }
});

test('catalog and the canonical empty-filter state fit every acceptance width', async ({ page }, info) => {
    const localePrefix = prefix(info);
    await page.goto(`${localePrefix}/dekk`);
    await expect(page.getByTestId('catalog-results')).toBeVisible();
    await expectNoGlobalOverflow(page);

    await page.goto(`${localePrefix}/dekk?width=125&aspect=35&rim=13`);
    const empty = page.getByTestId('catalog-empty');
    await expect(empty).toBeVisible();
    await expect(empty).toHaveAttribute('data-empty-reason', 'no-match');
    await expect(page.getByTestId('active-filters')).toContainText(/125|35|13/);
    await expectContainedFields(page);
    await expectNoGlobalOverflow(page);
    if (metadata(info).designProfile === 'classic' && page.viewportSize()!.width === 320) await capture(page, info, 'catalog-empty', true);
});

test('fixed quantity product can reach cart and checkout without submitting an order', async ({ page }, info) => {
    const localePrefix = prefix(info);
    await page.goto(`${localePrefix}/produkter/${sku}`);
    if (metadata(info).remoteReadOnly && metadata(info).designProfile === 'modern') {
        await expect(page.locator('h1')).toBeVisible();
        await expectNoGlobalOverflow(page);
        return;
    }
    const quantity = page.getByTestId('product-quantity');
    await expect(quantity).toHaveValue('2');
    await expect(quantity).toHaveAttribute('min', '2');
    await expect(quantity).toHaveAttribute('max', '2');
    await expect(quantity).toHaveAttribute('readonly', '');
    await expectContainedFields(page);
    await expectNoGlobalOverflow(page);
    if (metadata(info).designProfile === 'classic' && page.viewportSize()!.width === 320) await capture(page, info, 'product', true);

    if (metadata(info).remoteReadOnly) return;
    await page.getByTestId('product-add-to-cart').click();
    await page.goto(`${localePrefix}/handlekurv`);
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-line-quantity')).toHaveValue('2');
    await expectNoGlobalOverflow(page);
    await page.getByTestId('cart-checkout').click();
    await expect(page.getByTestId('checkout-form')).toBeVisible();
    await expect(page.getByTestId('checkout-submit')).toBeVisible();
    await expectContainedFields(page);
    await expectNoGlobalOverflow(page);
});

test('mobile current navigation underlines only its label and keeps keyboard focus visible', async ({ page }, info) => {
    test.skip(!isMobile(page), 'Mobile navigation contract.');
    test.skip(metadata(info).remoteReadOnly && metadata(info).designProfile === 'modern', 'Legacy primary predates the label-span contract.');
    const localePrefix = prefix(info);
    await page.goto(`${localePrefix}/priser`);
    await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();
    const current = page.getByTestId('mobile-navigation').locator('[aria-current="page"]');
    const label = current.locator(':scope > .et-mobile-menu__label');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveCSS('text-decoration-line', 'none');
    await expect(label).toHaveCSS('text-decoration-line', 'underline');
    await page.keyboard.press('Tab');
    await current.focus();
    await expect(current).toBeFocused();
    await expect(current).toHaveCSS('outline-style', 'solid');
    expect((await current.boundingBox())!.height).toBeGreaterThanOrEqual(44);
    await expectNoGlobalOverflow(page);
});

test('mobile booking steps preserve values and contain fields without final submission', async ({ page }, info) => {
    test.skip(!isMobile(page), 'Mobile form contract.');
    test.skip(metadata(info).remoteReadOnly && metadata(info).designProfile === 'modern', 'Legacy primary smoke is limited to stable GET contracts.');
    const localePrefix = prefix(info);
    await page.goto(`${localePrefix}/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile`);
    const address = page.locator('#booking-address');
    const district = page.locator('#booking-district');
    await address.fill('Classic QA address');
    await district.fill('Tromsø');
    await expectContainedFields(page);
    await page.getByTestId('seasonal-step-location').locator('[data-booking-next]').click();
    const vehicle = page.getByTestId('seasonal-step-vehicle-storage');
    await expect(vehicle).toBeVisible();
    await page.locator('[name="vehicles[0][vehicle]"]').fill('Volvo XC60');
    await page.locator('[name="vehicles[0][rim_band]"]').selectOption('r16-r18');
    await expectContainedFields(page);
    await vehicle.locator('[data-booking-back]').click();
    await expect(address).toHaveValue('Classic QA address');
    await expect(district).toHaveValue('Tromsø');
    await expectNoGlobalOverflow(page);
    if (metadata(info).designProfile === 'classic' && page.viewportSize()!.width === 320) await capture(page, info, 'booking-form', true);
});

test('desktop video exposes working play/pause control without changing form state', async ({ page }, info) => {
    test.skip(page.viewportSize()!.width !== 1440 || metadata(info).locale !== 'nb', 'One media pass per profile is sufficient.');
    test.skip(metadata(info).remoteReadOnly && metadata(info).designProfile === 'modern', 'Legacy primary smoke is limited to stable GET contracts.');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    const control = root.locator('[data-homepage-video-control]');
    await root.scrollIntoViewIfNeeded();
    await expect(control).toBeVisible();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBe(true);
    await control.click();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBe(false);
    await control.click();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBe(true);
});
