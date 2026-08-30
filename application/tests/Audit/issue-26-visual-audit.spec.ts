import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

type Surface = {
    id: string;
    path: string;
    screenshot?: boolean;
};

type BrowserProblem = {
    type: 'console' | 'pageerror' | 'requestfailed';
    url: string;
    message: string;
};

const surfaces: Surface[] = [
    { id: 'home', path: '/', screenshot: true },
    { id: 'services', path: '/tjenester', screenshot: true },
    { id: 'service-mobile', path: '/tjenester/mobilt-dekkskift' },
    { id: 'service-storage', path: '/tjenester/dekkhotell' },
    { id: 'service-workshop', path: '/tjenester/dekkverksted' },
    { id: 'service-tesla', path: '/tjenester/tesla-og-elbil' },
    { id: 'service-camper', path: '/tjenester/bobil-og-varebil' },
    { id: 'service-packages', path: '/tjenester/dekk-felg-og-hjulpakker' },
    { id: 'service-balancing', path: '/tjenester/omlegging-balansering' },
    { id: 'service-repair', path: '/tjenester/dekkreparasjon' },
    { id: 'service-tpms', path: '/tjenester/tpms-sensor' },
    { id: 'service-oil', path: '/tjenester/oljeskift' },
    { id: 'service-battery', path: '/tjenester/bilbatteri' },
    { id: 'service-diagnostics', path: '/tjenester/bildiagnose' },
    { id: 'service-wipers', path: '/tjenester/lyspaerer-viskerblad' },
    { id: 'seo-tyre-change', path: '/dekkskift-tromso' },
    { id: 'seo-storage', path: '/dekkhotell-tromso' },
    { id: 'seo-tesla', path: '/dekkskift-tesla-elbil-tromso' },
    { id: 'seo-balancing', path: '/omlegging-balansering-dekk-tromso' },
    { id: 'seo-repair', path: '/dekkreparasjon-tromso' },
    { id: 'seo-tpms', path: '/tpms-sensor-tromso' },
    { id: 'seo-oil', path: '/oljeskift-tromso' },
    { id: 'seo-battery', path: '/bilbatteri-tromso' },
    { id: 'seo-diagnostics', path: '/bildiagnose-tromso' },
    { id: 'seo-wipers', path: '/bytte-lyspaerer-viskerblad-tromso' },
    { id: 'fitment', path: '/biloppslag', screenshot: true },
    { id: 'request-seasonal-mobile', path: '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', screenshot: true },
    { id: 'request-seasonal-workshop', path: '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=workshop' },
    { id: 'request-generic', path: '/tjenester/bilbatteri/foresporsel', screenshot: true },
    { id: 'prices', path: '/priser', screenshot: true },
    { id: 'contact', path: '/kontakt', screenshot: true },
    { id: 'about', path: '/om-oss', screenshot: true },
    { id: 'store', path: '/dekk-og-felger', screenshot: true },
    { id: 'tyres', path: '/dekk', screenshot: true },
    { id: 'wheels', path: '/felger', screenshot: true },
    { id: 'product-test', path: '/produkter/NDI-TEST-4765205551649102', screenshot: true },
    { id: 'cart-empty', path: '/handlekurv', screenshot: true },
    { id: 'checkout-empty', path: '/kasse' },
    { id: 'privacy', path: '/personvern' },
    { id: 'terms', path: '/kjopsvilkar' },
    { id: 'not-found', path: '/issue-26-intentional-404', screenshot: true },
];

const russianParitySurfaces: Surface[] = [
    { id: 'ru-home', path: '/ru' },
    { id: 'ru-services', path: '/ru/tjenester' },
    { id: 'ru-service', path: '/ru/tjenester/mobilt-dekkskift' },
    { id: 'ru-prices', path: '/ru/priser' },
    { id: 'ru-contact', path: '/ru/kontakt' },
    { id: 'ru-about', path: '/ru/om-oss' },
    { id: 'ru-store', path: '/ru/dekk-og-felger' },
    { id: 'ru-tyres', path: '/ru/dekk' },
    { id: 'ru-wheels', path: '/ru/felger' },
    { id: 'ru-cart', path: '/ru/handlekurv' },
    { id: 'ru-fitment', path: '/ru/biloppslag' },
    { id: 'ru-request', path: '/ru/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile' },
    { id: 'ru-privacy', path: '/ru/personvern' },
    { id: 'ru-terms', path: '/ru/kjopsvilkar' },
];

function rawRoot(testInfo: TestInfo): string {
    return path.resolve(process.cwd(), '../tmp/issue-26/raw', testInfo.project.name);
}

async function settle(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        const visibleImages = Array.from(document.images).filter((image) => {
            const rect = image.getBoundingClientRect();
            return rect.bottom >= 0 && rect.top <= window.innerHeight * 2;
        });

        await Promise.all(visibleImages.map((image) => {
            if (image.complete) {
                return image.decode().catch(() => undefined);
            }

            return new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
                window.setTimeout(resolve, 2500);
            });
        }));
    });
    await page.waitForTimeout(250);
}

async function settleFullPageMedia(page: Page): Promise<void> {
    await page.evaluate(async () => {
        const images = Array.from(document.images);
        images.forEach((image) => image.loading = 'eager');

        await Promise.all(images.map((image) => {
            if (image.complete) {
                return image.decode().catch(() => undefined);
            }

            return new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
                window.setTimeout(resolve, 4000);
            });
        }));
    });
    await page.waitForTimeout(150);
}

async function computedState(locator: Locator): Promise<Record<string, string | number | boolean | null>> {
    return locator.evaluate((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return {
            tag: element.tagName.toLowerCase(),
            className: element.getAttribute('class'),
            color: style.color,
            backgroundColor: style.backgroundColor,
            borderColor: style.borderColor,
            borderRadius: style.borderRadius,
            boxShadow: style.boxShadow,
            outline: style.outline,
            focusVisible: element.matches(':focus-visible'),
            transform: style.transform,
            width: Math.round(rect.width),
            height: Math.round(rect.height),
        };
    });
}

async function screenshotState(page: Page, locator: Locator, outputPath: string): Promise<void> {
    const box = await locator.boundingBox();
    const viewport = page.viewportSize();
    if (!box || !viewport) return;

    const padding = 12;
    const x = Math.max(0, box.x - padding);
    const y = Math.max(0, box.y - padding);
    const width = Math.min(viewport.width - x, box.width + (padding * 2));
    const height = Math.min(viewport.height - y, box.height + (padding * 2));

    await page.screenshot({ path: outputPath, clip: { x, y, width, height } });
}

async function collectLayout(page: Page) {
    return page.evaluate(() => {
        const viewportWidth = document.documentElement.clientWidth;
        const overflow = Array.from(document.querySelectorAll<HTMLElement>('body *'))
            .map((element) => {
                const rect = element.getBoundingClientRect();
                return {
                    tag: element.tagName.toLowerCase(),
                    id: element.id,
                    className: element.className?.toString().slice(0, 180) ?? '',
                    left: Math.round(rect.left),
                    right: Math.round(rect.right),
                    width: Math.round(rect.width),
                };
            })
            .filter((rect) => rect.width > 0 && (rect.left < -1 || rect.right > viewportWidth + 1))
            .slice(0, 30);

        const tinyTargets = Array.from(document.querySelectorAll<HTMLElement>('a, button, input, select, textarea, summary'))
            .filter((element) => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return style.visibility !== 'hidden'
                    && style.display !== 'none'
                    && rect.width > 0
                    && rect.height > 0
                    && (rect.width < 44 || rect.height < 44);
            })
            .map((element) => {
                const rect = element.getBoundingClientRect();
                return {
                    tag: element.tagName.toLowerCase(),
                    text: (element.innerText || element.getAttribute('aria-label') || '').trim().slice(0, 100),
                    className: element.className?.toString().slice(0, 160) ?? '',
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                };
            })
            .slice(0, 40);

        return {
            title: document.title,
            lang: document.documentElement.lang,
            viewportWidth,
            scrollWidth: document.documentElement.scrollWidth,
            bodyHeight: document.body.scrollHeight,
            headerHeight: Math.round(document.querySelector('header')?.getBoundingClientRect().height ?? 0),
            footerHeight: Math.round(document.querySelector('footer')?.getBoundingClientRect().height ?? 0),
            productCards: document.querySelectorAll('[data-testid="catalog-product"]').length,
            productFallbacks: document.querySelectorAll('[data-testid="product-visual-fallback"]').length,
            disabledControls: document.querySelectorAll(':disabled').length,
            invalidControls: document.querySelectorAll('[aria-invalid="true"]').length,
            overflow,
            tinyTargets,
        };
    });
}

function watchProblems(page: Page): BrowserProblem[] {
    const problems: BrowserProblem[] = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            problems.push({ type: 'console', url: page.url(), message: message.text() });
        }
    });
    page.on('pageerror', (error) => {
        problems.push({ type: 'pageerror', url: page.url(), message: error.message });
    });
    page.on('requestfailed', (request) => {
        problems.push({
            type: 'requestfailed',
            url: request.url(),
            message: request.failure()?.errorText ?? 'request failed',
        });
    });

    return problems;
}

test.describe('Issue #26 public storefront visual audit', () => {
    test('captures the Bokmal public-surface matrix', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        const problems = watchProblems(page);
        const records = [];
        await mkdir(target, { recursive: true });

        for (const surface of surfaces) {
            const response = await page.goto(surface.path, { waitUntil: 'domcontentloaded' });
            await settle(page);
            const layout = await collectLayout(page);
            const status = response?.status() ?? null;
            records.push({ ...surface, finalUrl: page.url(), status, ...layout });

            const screenshot = surface.screenshot || ['desktop-1440x900', 'mobile-touch-390x844'].includes(testInfo.project.name);
            if (screenshot) {
                await settleFullPageMedia(page);
                await page.screenshot({ path: path.join(target, `${surface.id}.png`), fullPage: true });
            }

            if (surface.id !== 'not-found') {
                expect(status, `${surface.path} must not be a server error`).toBeLessThan(500);
            }
            expect(layout.scrollWidth, `${surface.path} has document-level horizontal overflow`).toBeLessThanOrEqual(layout.viewportWidth + 1);
        }

        await writeFile(path.join(target, 'bokmal-surfaces.json'), JSON.stringify({ records, problems }, null, 2));
    });

    test('captures interactive and sticky-header states without submitting data', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        const states: Record<string, unknown> = {};
        await mkdir(target, { recursive: true });
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const primary = page.getByTestId('header-booking');
        if (await primary.isVisible()) {
            states.headerBookingDefault = await computedState(primary);
            await screenshotState(page, primary, path.join(target, 'state-header-booking-default.png'));
            await primary.hover();
            states.headerBookingHover = await computedState(primary);
            await screenshotState(page, primary, path.join(target, 'state-header-booking-hover.png'));
            await page.keyboard.press('Tab');
            await primary.focus();
            states.headerBookingFocus = await computedState(primary);
            await screenshotState(page, primary, path.join(target, 'state-header-booking-focus.png'));
        }

        const quickFinder = page.getByTestId('quick-finder');
        if (await quickFinder.isVisible()) {
            const tabs = quickFinder.getByRole('tab');
            states.finderTabDefault = await computedState(tabs.first());
            await screenshotState(page, tabs.first(), path.join(target, 'state-finder-tab-default.png'));
            await tabs.last().click();
            states.finderTabSelected = await computedState(tabs.last());
            await screenshotState(page, tabs.last(), path.join(target, 'state-finder-tab-selected.png'));
            await page.keyboard.press('Tab');
            await tabs.last().focus();
            states.finderTabFocus = await computedState(tabs.last());
            await screenshotState(page, tabs.last(), path.join(target, 'state-finder-tab-focus.png'));
        }

        await page.evaluate(() => window.scrollTo({ top: Math.min(1100, document.body.scrollHeight / 3), behavior: 'instant' }));
        await page.waitForTimeout(200);
        const header = page.locator('.et-header').first();
        if (await header.isVisible()) {
            states.stickyHeader = await computedState(header);
            await page.screenshot({ path: path.join(target, 'home-sticky-header.png') });
        }

        const menuToggle = page.locator('[data-menu-toggle]');
        if (await menuToggle.isVisible()) {
            await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
            await menuToggle.click();
            states.menuExpanded = await menuToggle.getAttribute('aria-expanded');
            await page.screenshot({ path: path.join(target, 'home-mobile-menu-open.png') });
        }

        await writeFile(path.join(target, 'interactive-states.json'), JSON.stringify(states, null, 2));
    });

    test('captures catalog drawer, loading, empty and product-detail states', async ({ page }, testInfo) => {
        test.skip(!['desktop-1440x900', 'mobile-touch-390x844'].includes(testInfo.project.name));
        const target = rawRoot(testInfo);
        const states: Record<string, unknown> = {};
        await mkdir(target, { recursive: true });
        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const filterDrawer = page.getByTestId('catalog-filter-drawer');
        if (!await filterDrawer.evaluate((element) => (element as HTMLDetailsElement).open)) {
            await page.getByTestId('catalog-filter-toggle').click();
        }
        states.filterOpen = await filterDrawer.evaluate((element) => (element as HTMLDetailsElement).open);
        states.filterPanel = await computedState(filterDrawer.locator('.et-production-filter__panel'));
        await page.screenshot({ path: path.join(target, 'catalog-filter-open.png') });

        const activeCategory = page.locator('.et-catalog-category.is-active');
        if (await activeCategory.count()) {
            states.activeCategory = await computedState(activeCategory.first());
        }

        await page.route('**/dekk?**', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 1200));
            await route.continue();
        });
        const search = page.locator('input[name="q"]');
        await search.fill('NDI');
        await expect(page.locator('[data-catalog-live-root]')).toHaveAttribute('aria-busy', 'true');
        states.loadingRoot = await computedState(page.locator('[data-catalog-live-root]'));
        await page.screenshot({ path: path.join(target, 'catalog-loading.png') });
        await expect(page.locator('[data-catalog-live-root]')).toHaveAttribute('aria-busy', 'false');
        await page.unroute('**/dekk?**');

        await page.goto('/dekk?q=issue-26-no-product-can-match', { waitUntil: 'domcontentloaded' });
        await settle(page);
        states.emptyResultText = (await page.locator('[data-catalog-live-results]').innerText()).trim().slice(0, 500);
        await page.screenshot({ path: path.join(target, 'catalog-empty-results.png') });

        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const productLink = page.getByTestId('catalog-product-link').first();
        if (await productLink.count()) {
            const href = await productLink.getAttribute('href');
            states.productHref = href;
            await productLink.click();
            await settleFullPageMedia(page);
            states.productFallback = await page.getByTestId('product-visual-fallback').count();
            await page.screenshot({ path: path.join(target, 'product-detail.png'), fullPage: true });
        }

        await writeFile(path.join(target, 'catalog-states.json'), JSON.stringify(states, null, 2));
    });

    test('checks hidden Russian parity on the two representative audit viewports', async ({ page }, testInfo) => {
        test.skip(!['desktop-1440x900', 'mobile-touch-390x844'].includes(testInfo.project.name));
        const target = rawRoot(testInfo);
        const records = [];
        await mkdir(target, { recursive: true });

        for (const surface of russianParitySurfaces) {
            const response = await page.goto(surface.path, { waitUntil: 'domcontentloaded' });
            await settle(page);
            const layout = await collectLayout(page);
            records.push({ ...surface, finalUrl: page.url(), status: response?.status() ?? null, ...layout });
            expect(layout.lang).toBe('ru');
            expect(layout.scrollWidth, `${surface.path} has document-level horizontal overflow`).toBeLessThanOrEqual(layout.viewportWidth + 1);
        }

        await page.goto('/ru', { waitUntil: 'domcontentloaded' });
        await settle(page);
        await page.screenshot({ path: path.join(target, 'ru-home.png'), fullPage: true });
        await writeFile(path.join(target, 'russian-parity.json'), JSON.stringify(records, null, 2));
    });
});
