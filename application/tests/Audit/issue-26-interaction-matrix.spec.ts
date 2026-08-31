import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

type VisualState = {
    tag: string;
    className: string | null;
    text: string;
    color: string;
    backgroundColor: string;
    borderTopWidth: string;
    borderRightWidth: string;
    borderBottomWidth: string;
    borderLeftWidth: string;
    borderColor: string;
    borderRadius: string;
    boxShadow: string;
    outline: string;
    outlineOffset: string;
    opacity: string;
    transform: string;
    cursor: string;
    width: number;
    height: number;
    top: number;
    left: number;
    focusVisible: boolean;
    active: boolean;
    disabled: boolean;
    ariaCurrent: string | null;
    ariaSelected: string | null;
    ariaExpanded: string | null;
    ariaInvalid: string | null;
};

type MatrixRecord = {
    id: string;
    route: string;
    selector: string;
    present: boolean;
    states: Record<string, VisualState | string | boolean | null>;
};

const compositionSurfaces = [
    { id: 'home', route: '/' },
    { id: 'services-index', route: '/tjenester' },
    { id: 'service-detail', route: '/tjenester/mobilt-dekkskift' },
    { id: 'shop-entry', route: '/dekk-og-felger' },
    { id: 'catalog', route: '/dekk' },
    { id: 'product-detail', route: '/produkter/NDI-TEST-4765205551649102' },
    { id: 'request-form', route: '/tjenester/bilbatteri/foresporsel' },
    { id: 'empty-cart', route: '/handlekurv' },
    { id: 'prices', route: '/priser' },
    { id: 'about', route: '/om-oss' },
    { id: 'contact', route: '/kontakt' },
    { id: 'legal', route: '/personvern' },
    { id: 'not-found', route: '/issue-26-corrected-audit-404' },
];

function rawRoot(testInfo: TestInfo): string {
    return path.resolve(process.cwd(), '../tmp/issue-26/raw', testInfo.project.name);
}

async function settle(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(Array.from(document.images).map((image) => {
            if (image.complete) return image.decode().catch(() => undefined);

            return new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
                window.setTimeout(resolve, 2500);
            });
        }));
    });
    await page.waitForTimeout(180);
}

async function visualState(locator: Locator): Promise<VisualState> {
    return locator.evaluate((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return {
            tag: element.tagName.toLowerCase(),
            className: element.getAttribute('class'),
            text: (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 160),
            color: style.color,
            backgroundColor: style.backgroundColor,
            borderTopWidth: style.borderTopWidth,
            borderRightWidth: style.borderRightWidth,
            borderBottomWidth: style.borderBottomWidth,
            borderLeftWidth: style.borderLeftWidth,
            borderColor: style.borderColor,
            borderRadius: style.borderRadius,
            boxShadow: style.boxShadow,
            outline: style.outline,
            outlineOffset: style.outlineOffset,
            opacity: style.opacity,
            transform: style.transform,
            cursor: style.cursor,
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            top: Math.round(rect.top),
            left: Math.round(rect.left),
            focusVisible: element.matches(':focus-visible'),
            active: element.matches(':active'),
            disabled: (element as HTMLButtonElement).disabled === true,
            ariaCurrent: element.getAttribute('aria-current'),
            ariaSelected: element.getAttribute('aria-selected'),
            ariaExpanded: element.getAttribute('aria-expanded'),
            ariaInvalid: element.getAttribute('aria-invalid'),
        };
    });
}

async function screenshotLocator(page: Page, locator: Locator, outputPath: string, padding = 28): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
    const box = await locator.boundingBox();
    const viewport = page.viewportSize();
    if (!box || !viewport) return;

    const x = Math.max(0, Math.floor(box.x - padding));
    const y = Math.max(0, Math.floor(box.y - padding));
    const width = Math.min(viewport.width - x, Math.ceil(box.width + (padding * 2)));
    const height = Math.min(viewport.height - y, Math.ceil(box.height + (padding * 2)));
    await page.screenshot({ path: outputPath, clip: { x, y, width, height } });
}

async function keyboardTabTo(page: Page, locator: Locator, maximumTabs = 90): Promise<number> {
    await locator.scrollIntoViewIfNeeded();
    await page.evaluate(() => {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    });

    for (let index = 1; index <= maximumTabs; index += 1) {
        await page.keyboard.press('Tab');
        if (await locator.evaluate((element) => document.activeElement === element)) return index;
    }

    throw new Error(`Keyboard traversal did not reach ${await locator.evaluate((element) => element.outerHTML.slice(0, 240))}`);
}

async function activeState(page: Page, locator: Locator): Promise<VisualState> {
    await locator.scrollIntoViewIfNeeded();
    const box = await locator.boundingBox();
    if (!box) throw new Error('Cannot capture active state without a bounding box');

    await page.mouse.move(box.x + (box.width / 2), box.y + (box.height / 2));
    await locator.evaluate((element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();
        }, { once: true, capture: true });
    });
    await page.mouse.down();
    const state = await visualState(locator);
    await page.mouse.up();

    return state;
}

async function captureStandardStates(
    page: Page,
    target: string,
    id: string,
    locator: Locator,
    options: { keyboard?: boolean; active?: boolean; screenshots?: boolean; fullViewportEvidence?: boolean } = {},
): Promise<Record<string, VisualState | string | boolean | null>> {
    const states: Record<string, VisualState | string | boolean | null> = {};
    states.default = await visualState(locator);
    if (options.screenshots !== false) {
        await screenshotLocator(page, locator, path.join(target, `matrix-${id}-default.png`));
        if (options.fullViewportEvidence) await page.screenshot({ path: path.join(target, `critical-${id}-default.png`) });
    }

    await locator.hover();
    await page.waitForTimeout(80);
    states.hover = await visualState(locator);
    if (options.screenshots !== false) {
        await screenshotLocator(page, locator, path.join(target, `matrix-${id}-hover.png`));
        if (options.fullViewportEvidence) await page.screenshot({ path: path.join(target, `critical-${id}-hover.png`) });
    }

    if (options.keyboard !== false) {
        const tabs = await keyboardTabTo(page, locator);
        states.keyboardTabs = String(tabs);
        states.focus = await visualState(locator);
        expect((states.focus as VisualState).focusVisible, `${id} must be reached with real keyboard focus`).toBe(true);
        if (options.screenshots !== false) {
            await screenshotLocator(page, locator, path.join(target, `matrix-${id}-focus.png`));
            if (options.fullViewportEvidence) await page.screenshot({ path: path.join(target, `critical-${id}-focus.png`) });
        }
    }

    if (options.active !== false) {
        states.active = await activeState(page, locator);
    }

    return states;
}

async function recordCase(
    page: Page,
    target: string,
    records: MatrixRecord[],
    id: string,
    route: string,
    selector: string,
    prepare?: (page: Page) => Promise<void>,
    options: { keyboard?: boolean; active?: boolean; screenshots?: boolean; fullViewportEvidence?: boolean } = {},
): Promise<void> {
    await page.goto(route, { waitUntil: 'domcontentloaded' });
    await settle(page);
    if (prepare) await prepare(page);
    const locator = page.locator(selector).first();
    const present = await locator.isVisible().catch(() => false);
    const record: MatrixRecord = { id, route, selector, present, states: {} };
    if (present) record.states = await captureStandardStates(page, target, id, locator, options);
    records.push(record);
}

test.describe('Issue #26 corrected interaction and composition audit', () => {
    test('captures sticky-header before/after layers at every required viewport', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const header = page.locator('[data-site-header]');
        const before = await visualState(header);
        await page.screenshot({ path: path.join(target, 'sticky-header-before.png') });

        const targets = [
            { id: 'light', selector: '[data-testid="home-dekkhotell"] .et-h2' },
            { id: 'dark', selector: '[data-testid="services-section"] .et-h2' },
        ];
        const after: Record<string, unknown> = {};
        for (const scrollTarget of targets) {
            const locator = page.locator(scrollTarget.selector).first();
            if (!await locator.count()) continue;
            const absoluteTop = await locator.evaluate((element) => element.getBoundingClientRect().top + window.scrollY);
            await page.evaluate((top) => window.scrollTo({ top: Math.max(1, top - 24), behavior: 'instant' }), absoluteTop);
            await page.waitForTimeout(180);
            after[scrollTarget.id] = {
                scrollY: await page.evaluate(() => Math.round(window.scrollY)),
                state: await visualState(header),
                elementUnderHeader: await header.evaluate((element) => {
                    const rect = element.getBoundingClientRect();
                    const stack = document.elementsFromPoint(Math.round(rect.width / 2), Math.max(1, Math.round(rect.bottom - 5)));
                    return stack.slice(0, 6).map((item) => ({
                        tag: item.tagName.toLowerCase(),
                        className: item.getAttribute('class'),
                        text: (item.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 100),
                    }));
                }),
            };
            await page.screenshot({ path: path.join(target, `sticky-header-after-${scrollTarget.id}.png`) });
        }

        await writeFile(path.join(target, 'sticky-header-states.json'), JSON.stringify({ before, after }, null, 2));
    });

    test('measures hero, finder and logo geometry at every required viewport', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const geometry = await page.evaluate(() => {
            const selectors = {
                hero: '.et-v3-hero',
                finder: '[data-testid="quick-finder"]',
                logo: '.et-v3-hero__brand, .et-v3-hero__logo',
                vehicle: '.et-v3-hero__media, .et-v3-hero__photo, .et-v3-hero img',
            };
            const rect = (selector: string) => {
                const element = document.querySelector(selector);
                const box = element?.getBoundingClientRect();
                return box ? { x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width), height: Math.round(box.height), right: Math.round(box.right), bottom: Math.round(box.bottom) } : null;
            };
            const intersects = (a: ReturnType<typeof rect>, b: ReturnType<typeof rect>) => Boolean(a && b && a.x < b.right && a.right > b.x && a.y < b.bottom && a.bottom > b.y);
            const finder = rect(selectors.finder);
            const logo = rect(selectors.logo);
            const vehicle = rect(selectors.vehicle);

            return {
                viewport: { width: innerWidth, height: innerHeight },
                hero: rect(selectors.hero),
                finder,
                logo,
                vehicle,
                finderOverlapsLogo: intersects(finder, logo),
                finderOverlapsVehicle: intersects(finder, vehicle),
            };
        });
        await writeFile(path.join(target, 'hero-geometry.json'), JSON.stringify(geometry, null, 2));
        expect(geometry.finderOverlapsLogo).toBe(false);
    });

    test('records atomic page-family composition and rhythm metrics', async ({ page }, testInfo) => {
        test.setTimeout(420_000);
        const target = rawRoot(testInfo);
        const records: Record<string, unknown>[] = [];
        await mkdir(target, { recursive: true });

        for (const surface of compositionSurfaces) {
            const response = await page.goto(surface.route, { waitUntil: 'domcontentloaded' });
            await settle(page);
            const metrics = await page.evaluate(() => {
                const describe = (element: Element) => {
                    const rect = element.getBoundingClientRect();
                    const style = getComputedStyle(element);
                    return {
                        tag: element.tagName.toLowerCase(),
                        className: element.getAttribute('class'),
                        top: Math.round(rect.top + window.scrollY),
                        bottom: Math.round(rect.bottom + window.scrollY),
                        width: Math.round(rect.width),
                        height: Math.round(rect.height),
                        backgroundColor: style.backgroundColor,
                        border: `${style.borderTopWidth} ${style.borderTopStyle} ${style.borderTopColor}`,
                        borderRadius: style.borderRadius,
                        boxShadow: style.boxShadow,
                    };
                };
                const sections = Array.from(document.querySelectorAll<HTMLElement>('main > section, main > div > section'))
                    .filter((element, index, array) => array.indexOf(element) === index)
                    .map((element, index, array) => {
                        const current = describe(element);
                        const previous = index > 0 ? describe(array[index - 1]) : null;
                        return {
                            ...current,
                            id: element.id,
                            heading: element.querySelector('h1,h2')?.textContent?.trim().replace(/\s+/g, ' ').slice(0, 180) ?? null,
                            gapFromPrevious: previous ? current.top - previous.bottom : null,
                        };
                    });
                const headings = Array.from(document.querySelectorAll<HTMLElement>('main h1, main h2, main h3')).map((element) => {
                    const rect = element.getBoundingClientRect();
                    const style = getComputedStyle(element);
                    const lineHeight = Number.parseFloat(style.lineHeight) || 0;
                    return {
                        tag: element.tagName.toLowerCase(),
                        text: (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 220),
                        top: Math.round(rect.top + window.scrollY),
                        width: Math.round(rect.width),
                        height: Math.round(rect.height),
                        fontSize: style.fontSize,
                        lineHeight: style.lineHeight,
                        approximateLines: lineHeight > 0 ? Math.max(1, Math.round(rect.height / lineHeight)) : null,
                    };
                });
                const anchors = Array.from(document.querySelectorAll<HTMLAnchorElement>('main a[href]')).map((anchor) => ({
                    text: (anchor.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 120),
                    href: anchor.pathname + anchor.search + anchor.hash,
                    className: anchor.getAttribute('class'),
                }));
                const duplicateActions = Object.entries(anchors.reduce<Record<string, number>>((counts, anchor) => {
                    const key = `${anchor.text}|${anchor.href}`;
                    counts[key] = (counts[key] ?? 0) + 1;
                    return counts;
                }, {})).filter(([, count]) => count > 1).map(([key, count]) => ({ key, count }));
                const filterPanel = document.querySelector<HTMLElement>('.et-production-filter__panel');
                const filterShell = document.querySelector<HTMLElement>('.et-production-filter');
                const results = document.querySelector<HTMLElement>('[data-catalog-live-results]');
                const emptyState = document.querySelector<HTMLElement>('.et-empty-state');
                const nav = Array.from(document.querySelectorAll<HTMLElement>('[data-testid="desktop-navigation"] > a, [data-testid="desktop-navigation"] > .et-nav__item > a'))
                    .map((element) => ({ text: (element.textContent ?? '').trim(), current: element.getAttribute('aria-current') }));

                return {
                    viewport: { width: innerWidth, height: innerHeight },
                    document: { width: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight },
                    main: document.querySelector('main') ? describe(document.querySelector('main')!) : null,
                    sections,
                    headings,
                    navigation: nav,
                    duplicateActions,
                    componentCounts: {
                        buttons: document.querySelectorAll('main .et-btn').length,
                        interactiveCards: document.querySelectorAll('main .et-card--interactive').length,
                        legacyServiceCards: document.querySelectorAll('main .et-service-card').length,
                        serviceRows: document.querySelectorAll('main .et-v3-service-row').length,
                        productCards: document.querySelectorAll('main .et-product-card').length,
                        shopLinks: document.querySelectorAll('main .et-shop-link').length,
                        promotions: document.querySelectorAll('[data-testid="homepage-promotion"]').length,
                        serviceMediaImages: document.querySelectorAll('.et-v3-service-row__media img').length,
                        serviceSyntheticGraphics: document.querySelectorAll('.et-v3-service-row__graphic').length,
                        inlineStyles: document.querySelectorAll('main [style]').length,
                    },
                    catalogue: {
                        shell: filterShell ? describe(filterShell) : null,
                        panel: filterPanel ? { ...describe(filterPanel), clientHeight: filterPanel.clientHeight, scrollHeight: filterPanel.scrollHeight, overflowY: getComputedStyle(filterPanel).overflowY } : null,
                        results: results ? describe(results) : null,
                        categoryRail: document.querySelector('.et-catalog-categories') ? describe(document.querySelector('.et-catalog-categories')!) : null,
                    },
                    emptyState: emptyState ? describe(emptyState) : null,
                };
            });
            records.push({ id: surface.id, route: surface.route, finalUrl: page.url(), status: response?.status() ?? null, ...metrics });
        }

        await writeFile(path.join(target, 'page-composition.json'), JSON.stringify(records, null, 2));
    });

    test('inventories visible hover border, shadow and geometry changes', async ({ page }, testInfo) => {
        test.setTimeout(600_000);
        test.skip(testInfo.project.name !== 'desktop-1440x900');
        const target = rawRoot(testInfo);
        const routes = ['/', '/tjenester', '/tjenester/mobilt-dekkskift', '/dekk-og-felger', '/dekk', '/produkter/NDI-TEST-4765205551649102', '/tjenester/bilbatteri/foresporsel', '/handlekurv', '/priser', '/om-oss', '/kontakt'];
        const inventory: Record<string, unknown>[] = [];
        await mkdir(target, { recursive: true });

        for (const route of routes) {
            await page.goto(route, { waitUntil: 'domcontentloaded' });
            await settle(page);
            const candidates = page.locator([
                'a[href]',
                'button',
                'input:not([type="hidden"])',
                'select',
                'textarea',
                'summary',
                '[tabindex]:not([tabindex="-1"])',
                '.et-card--interactive',
                '.et-service-card',
                '.et-shop-link',
                '.et-product-card',
                '.et-v3-service-row',
                '.et-v3-promotion',
                '.et-v3-service',
            ].join(','));
            const count = Math.min(await candidates.count(), 140);

            for (let index = 0; index < count; index += 1) {
                const locator = candidates.nth(index);
                if (!await locator.isVisible().catch(() => false)) continue;
                const before = await visualState(locator);
                await locator.hover({ timeout: 5000 }).catch(() => undefined);
                await page.waitForTimeout(25);
                const hover = await visualState(locator);
                const changed = before.borderColor !== hover.borderColor
                    || before.borderTopWidth !== hover.borderTopWidth
                    || before.boxShadow !== hover.boxShadow
                    || before.transform !== hover.transform
                    || before.width !== hover.width
                    || before.height !== hover.height
                    || before.backgroundColor !== hover.backgroundColor
                    || before.color !== hover.color;
                if (!changed) continue;

                inventory.push({
                    route,
                    index,
                    identity: await locator.evaluate((element) => ({
                        tag: element.tagName.toLowerCase(),
                        id: element.id,
                        className: element.getAttribute('class'),
                        testId: element.getAttribute('data-testid'),
                        text: (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 140),
                    })),
                    before,
                    hover,
                    dimensionShift: { width: hover.width - before.width, height: hover.height - before.height },
                });
            }
        }

        await writeFile(path.join(target, 'hover-inventory.json'), JSON.stringify(inventory, null, 2));
    });

    test('records real keyboard focus order on principal page families', async ({ page }, testInfo) => {
        test.setTimeout(420_000);
        test.skip(testInfo.project.name !== 'desktop-1440x900');
        const target = rawRoot(testInfo);
        const routes = ['/', '/tjenester', '/tjenester/mobilt-dekkskift', '/dekk', '/tjenester/bilbatteri/foresporsel', '/handlekurv'];
        const records: Record<string, unknown>[] = [];
        await mkdir(target, { recursive: true });

        for (const route of routes) {
            await page.goto(route, { waitUntil: 'domcontentloaded' });
            await settle(page);
            await page.evaluate(() => {
                if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
            });
            const order: Record<string, unknown>[] = [];
            const seen = new Set<string>();

            for (let step = 1; step <= 140; step += 1) {
                await page.keyboard.press('Tab');
                const current = await page.evaluate(() => {
                    const element = document.activeElement;
                    if (!(element instanceof HTMLElement) || element === document.body) return null;
                    const style = getComputedStyle(element);
                    const rect = element.getBoundingClientRect();
                    return {
                        tag: element.tagName.toLowerCase(),
                        id: element.id,
                        className: element.getAttribute('class'),
                        testId: element.getAttribute('data-testid'),
                        href: element instanceof HTMLAnchorElement ? element.pathname + element.search + element.hash : null,
                        text: (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 140),
                        focusVisible: element.matches(':focus-visible'),
                        outline: style.outline,
                        outlineOffset: style.outlineOffset,
                        boxShadow: style.boxShadow,
                        borderColor: style.borderColor,
                        width: Math.round(rect.width),
                        height: Math.round(rect.height),
                    };
                });
                if (!current) continue;
                const key = `${current.tag}|${current.id}|${current.className}|${current.testId}|${current.href}|${current.text}`;
                if (seen.has(key)) break;
                seen.add(key);
                order.push({ step, ...current });
            }
            records.push({ route, order });
        }

        await writeFile(path.join(target, 'keyboard-focus-order.json'), JSON.stringify(records, null, 2));
    });

    test('captures the desktop reusable interaction-state matrix', async ({ page }, testInfo) => {
        test.setTimeout(900_000);
        test.skip(testInfo.project.name !== 'desktop-1440x900');
        const target = rawRoot(testInfo);
        const records: MatrixRecord[] = [];
        await mkdir(target, { recursive: true });

        await recordCase(page, target, records, 'button-primary', '/', '[data-testid="header-booking"]', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'button-outline', '/dekk', '[data-testid="catalog-filter-reset"]', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'button-ghost', '/tjenester/mobilt-dekkskift', '.et-btn--ghost', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'button-link', '/produkter/NDI-TEST-4765205551649102', '.et-btn--link', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'desktop-nav', '/', '[data-testid="desktop-navigation"] > a');
        await recordCase(page, target, records, 'cart-action', '/', '[data-testid="header-cart"]', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'generic-interactive-card', '/biloppslag', '.et-card--interactive', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'legacy-service-card', '/tjenester', '.et-service-card');
        await recordCase(page, target, records, 'service-row', '/tjenester', '.et-v3-service-row', undefined, { keyboard: false, active: false, fullViewportEvidence: true });
        await recordCase(page, target, records, 'service-row-link', '/tjenester', '.et-v3-service-row__content > a');
        await recordCase(page, target, records, 'shop-link', '/dekk-og-felger', '.et-shop-link');
        await recordCase(page, target, records, 'product-card', '/dekk', '.et-product-card', undefined, { keyboard: false, active: false, fullViewportEvidence: true });
        await recordCase(page, target, records, 'product-card-link', '/dekk', '[data-testid="catalog-product-link"]');
        await recordCase(page, target, records, 'category-tile', '/dekk', '.et-catalog-category:not(.is-active)', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'filter-summary', '/dekk', '.et-filter-section > summary');
        await recordCase(page, target, records, 'quantity-control', '/produkter/NDI-TEST-4765205551649102', '[data-testid="product-quantity"]');
        await recordCase(page, target, records, 'input', '/tjenester/bilbatteri/foresporsel', '#booking-registration-number', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'select', '/tjenester/bilbatteri/foresporsel', '#booking-fulfilment');
        await recordCase(page, target, records, 'date', '/tjenester/bilbatteri/foresporsel', '#booking-preferred-date');
        await recordCase(page, target, records, 'textarea', '/tjenester/bilbatteri/foresporsel', '#booking-note');
        await recordCase(page, target, records, 'checkbox', '/tjenester/bilbatteri/foresporsel', 'input[name="consent"]');
        await recordCase(page, target, records, 'footer-link', '/', '.et-footer__nav a');
        await recordCase(page, target, records, 'social-link', '/', '.et-v3-socials a');

        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const selectedCategory = page.locator('.et-catalog-category.is-active').first();
        records.push({
            id: 'category-selected',
            route: '/dekk',
            selector: '.et-catalog-category.is-active',
            present: await selectedCategory.isVisible(),
            states: { selected: await visualState(selectedCategory) },
        });

        await page.goto('/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const disabledDate = page.getByTestId('booking-slot-select');
        records.push({
            id: 'disabled-select',
            route: page.url(),
            selector: '[data-testid="booking-slot-select"]',
            present: await disabledDate.isVisible().catch(() => false),
            states: await disabledDate.isVisible().catch(() => false) ? { disabled: await visualState(disabledDate) } : {},
        });

        await writeFile(path.join(target, 'interaction-matrix.json'), JSON.stringify(records, null, 2));
    });

    test('captures desktop submenu hover and real keyboard-open states', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop-1440x900');
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const parent = page.locator('.et-nav__item > a').first();
        const submenu = page.locator('.et-nav__item').first().locator('.et-nav__submenu');
        const child = submenu.locator('a').first();
        const record: Record<string, unknown> = {
            route: '/',
            parentSelector: '.et-nav__item > a',
            submenuSelector: '.et-nav__submenu',
            childSelector: '.et-nav__submenu a',
            initialVisible: await submenu.isVisible(),
        };

        await parent.hover();
        await expect(submenu).toBeVisible();
        record.hoverParent = await visualState(parent);
        record.hoverChild = await visualState(child);
        record.hoverSubmenu = await visualState(submenu);
        await page.screenshot({ path: path.join(target, 'critical-desktop-submenu-hover-open.png') });

        await page.reload({ waitUntil: 'domcontentloaded' });
        await settle(page);
        const freshParent = page.locator('.et-nav__item > a').first();
        const freshSubmenu = page.locator('.et-nav__item').first().locator('.et-nav__submenu');
        const freshChild = freshSubmenu.locator('a').first();
        record.parentKeyboardTabs = await keyboardTabTo(page, freshParent);
        await expect(freshSubmenu).toBeVisible();
        record.focusParent = await visualState(freshParent);
        record.focusSubmenu = await visualState(freshSubmenu);
        await page.screenshot({ path: path.join(target, 'critical-desktop-submenu-keyboard-open.png') });

        await page.keyboard.press('Tab');
        expect(await freshChild.evaluate((element) => document.activeElement === element)).toBe(true);
        record.focusChild = await visualState(freshChild);
        await page.screenshot({ path: path.join(target, 'critical-desktop-submenu-child-focus.png') });

        await writeFile(path.join(target, 'desktop-submenu-states.json'), JSON.stringify(record, null, 2));
    });

    test('captures mobile navigation, filters and control states', async ({ page }, testInfo) => {
        test.skip(!['mobile-touch-390x844', 'mobile-narrow-320x800'].includes(testInfo.project.name));
        const target = rawRoot(testInfo);
        const records: MatrixRecord[] = [];
        await mkdir(target, { recursive: true });

        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const menuButton = page.locator('[data-menu-toggle]');
        const menuStates: Record<string, VisualState | string | boolean | null> = await captureStandardStates(page, target, 'mobile-menu-button', menuButton);
        await menuButton.click();
        menuStates.open = await visualState(menuButton);
        await page.screenshot({ path: path.join(target, 'matrix-mobile-menu-open.png') });
        records.push({ id: 'mobile-menu-button', route: '/', selector: '[data-menu-toggle]', present: true, states: menuStates });

        const mobileMenuLink = page.getByTestId('mobile-booking-link');
        records.push({
            id: 'mobile-menu-link',
            route: '/',
            selector: '[data-testid="mobile-booking-link"]',
            present: await mobileMenuLink.isVisible(),
            states: await captureStandardStates(page, target, 'mobile-menu-link', mobileMenuLink),
        });

        const mobileGroup = page.locator('.et-mobile-menu__group').first();
        const mobileGroupSummary = mobileGroup.locator('summary');
        const mobileGroupChild = mobileGroup.locator('.et-mobile-menu__children a').first();
        const mobileGroupStates = await captureStandardStates(page, target, 'mobile-menu-group-summary', mobileGroupSummary);
        mobileGroupStates.closedOpenAttribute = await mobileGroup.getAttribute('open');
        await mobileGroupSummary.click();
        await expect(mobileGroup).toHaveAttribute('open', '');
        mobileGroupStates.open = await visualState(mobileGroupSummary);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(100);
        await page.screenshot({ path: path.join(target, 'critical-mobile-menu-group-open.png') });
        const childTabs = await keyboardTabTo(page, mobileGroupChild);
        mobileGroupStates.childKeyboardTabs = String(childTabs);
        mobileGroupStates.childFocus = await visualState(mobileGroupChild);
        await page.screenshot({ path: path.join(target, 'critical-mobile-menu-group-child-focus.png') });
        records.push({
            id: 'mobile-menu-group',
            route: '/',
            selector: '.et-mobile-menu__group > summary',
            present: true,
            states: mobileGroupStates,
        });

        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const drawer = page.getByTestId('catalog-filter-drawer');
        if (!await drawer.evaluate((element) => (element as HTMLDetailsElement).open)) {
            await page.getByTestId('catalog-filter-toggle').click();
        }
        const filterApply = page.getByTestId('catalog-filter-apply');
        records.push({
            id: 'mobile-filter-apply',
            route: '/dekk',
            selector: '[data-testid="catalog-filter-apply"]',
            present: await filterApply.isVisible(),
            states: await captureStandardStates(page, target, 'mobile-filter-apply', filterApply),
        });

        const checkbox = page.locator('.et-filter-toggle input').first();
        records.push({
            id: 'mobile-filter-checkbox',
            route: '/dekk',
            selector: '.et-filter-toggle input',
            present: await checkbox.isVisible(),
            states: await captureStandardStates(page, target, 'mobile-filter-checkbox', checkbox),
        });

        await writeFile(path.join(target, 'mobile-interaction-matrix.json'), JSON.stringify(records, null, 2));
    });

    test('captures delayed contact-map rendering and frame state', async ({ page }, testInfo) => {
        test.skip(!['desktop-1440x900', 'mobile-touch-390x844'].includes(testInfo.project.name));
        test.setTimeout(90_000);
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        const mapResponses: Array<{ origin: string; pathname: string; status: number }> = [];
        const mapFailures: Array<{ origin: string; pathname: string; error: string | null }> = [];
        const normalizeUrl = (rawUrl: string): { origin: string; pathname: string } => {
            try {
                const url = new URL(rawUrl);
                return { origin: url.origin, pathname: url.pathname };
            } catch {
                return { origin: 'invalid', pathname: rawUrl.slice(0, 160) };
            }
        };

        page.on('response', (response) => {
            if (!/google\.|gstatic\.|maps\./i.test(response.url())) return;
            mapResponses.push({ ...normalizeUrl(response.url()), status: response.status() });
        });
        page.on('requestfailed', (request) => {
            if (!/google\.|gstatic\.|maps\./i.test(request.url())) return;
            mapFailures.push({ ...normalizeUrl(request.url()), error: request.failure()?.errorText ?? null });
        });

        await page.goto('/kontakt', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const iframe = page.getByTestId('map-iframe');
        await expect(iframe).toBeVisible();
        await page.waitForTimeout(8_000);
        await iframe.scrollIntoViewIfNeeded();
        await page.waitForTimeout(300);

        const source = await iframe.getAttribute('src');
        const frameUrls = page.frames().map((frame) => normalizeUrl(frame.url()));
        const state = {
            route: '/kontakt',
            source: source ? normalizeUrl(source) : null,
            iframe: await visualState(iframe),
            frameCount: page.frames().length,
            frameUrls,
            responseCount: mapResponses.length,
            responses: mapResponses,
            failureCount: mapFailures.length,
            failures: mapFailures,
        };
        await page.screenshot({ path: path.join(target, 'critical-contact-map-after-8s.png') });
        await writeFile(path.join(target, 'contact-map-state.json'), JSON.stringify(state, null, 2));
    });
});
