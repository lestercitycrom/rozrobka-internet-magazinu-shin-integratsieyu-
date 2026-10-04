import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const auditProductRoute = process.env.PLAYWRIGHT_AUDIT_PRODUCT_ROUTE ?? '/produkter/000205550016';

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
    visible: boolean;
    requiredVisible: boolean;
    verification: 'required-visible' | 'dom-state';
    states: Record<string, VisualState | string | boolean | null>;
};

const compositionSurfaces = [
    { id: 'home', route: '/' },
    { id: 'services-index', route: '/tjenester' },
    { id: 'service-detail', route: '/tjenester/mobilt-dekkskift' },
    { id: 'shop-entry', route: '/dekk-og-felger' },
    { id: 'catalog', route: '/dekk' },
    { id: 'product-detail', route: auditProductRoute },
    { id: 'request-form', route: '/tjenester/bilbatteri/foresporsel' },
    { id: 'empty-cart', route: '/handlekurv' },
    { id: 'prices', route: '/priser' },
    { id: 'about', route: '/om-oss' },
    { id: 'contact', route: '/kontakt' },
    { id: 'legal', route: '/personvern' },
    { id: 'not-found', route: '/issue-26-corrected-audit-404' },
];

function rawRoot(testInfo: TestInfo): string {
    const root = process.env.PLAYWRIGHT_AUDIT_OUTPUT_ROOT
        ? path.resolve(process.env.PLAYWRIGHT_AUDIT_OUTPUT_ROOT)
        : path.resolve(process.cwd(), '../tmp/issue-26/raw');

    return path.join(root, testInfo.project.name);
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
    const present = await locator.count() > 0;
    const visible = present && await locator.isVisible();
    expect(present, `${id} must resolve to a current component on ${route}`).toBe(true);
    expect(visible, `${id} must have a visible representative on ${route}`).toBe(true);
    const record: MatrixRecord = {
        id,
        route,
        selector,
        present,
        visible,
        requiredVisible: true,
        verification: 'required-visible',
        states: await captureStandardStates(page, target, id, locator, options),
    };
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
            await expect(locator, `${scrollTarget.id} sticky-header target must exist`).toBeVisible();
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

    test('fails closed and measures hero finder against approved media focal regions', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);

        const geometry = await page.evaluate(() => {
            const selectors = {
                hero: '.et-v3-hero',
                finder: '[data-testid="quick-finder"]',
                media: '.et-v3-hero > picture > .et-v3-hero__photo',
            };
            const rect = (selector: string) => {
                const element = document.querySelector(selector);
                const box = element?.getBoundingClientRect();
                return box ? { x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width), height: Math.round(box.height), right: Math.round(box.right), bottom: Math.round(box.bottom) } : null;
            };
            const intersects = (a: ReturnType<typeof rect>, b: ReturnType<typeof rect>) => Boolean(a && b && a.x < b.right && a.right > b.x && a.y < b.bottom && a.bottom > b.y);
            const finder = rect(selectors.finder);
            const mediaElement = document.querySelector<HTMLImageElement>(selectors.media);
            const media = rect(selectors.media);
            const currentPath = mediaElement ? new URL(mediaElement.currentSrc || mediaElement.src, location.href).pathname : null;
            const focalContract = currentPath?.endsWith('/brand/hero-service-mobile.webp')
                ? {
                    asset: '/brand/hero-service-mobile.webp',
                    brand: { left: 0, top: .19, right: .37, bottom: .38 },
                    vehicle: { left: .23, top: .18, right: 1, bottom: .82 },
                }
                : currentPath?.endsWith('/brand/hero-service-desktop.webp')
                    ? {
                        asset: '/brand/hero-service-desktop.webp',
                        brand: { left: .66, top: .21, right: .89, bottom: .42 },
                        vehicle: { left: .21, top: .29, right: .59, bottom: .71 },
                    }
                    : null;
            const projectFocalRegion = (region: { left: number; top: number; right: number; bottom: number } | undefined) => {
                if (!mediaElement || !media || !region || mediaElement.naturalWidth <= 0 || mediaElement.naturalHeight <= 0) return null;
                const style = getComputedStyle(mediaElement);
                if (style.objectFit !== 'cover') return null;
                const position = style.objectPosition.split(/\s+/);
                const percentage = (value: string | undefined, fallback: number) => value?.endsWith('%') ? Number.parseFloat(value) / 100 : fallback;
                const positionX = percentage(position[0], .5);
                const positionY = percentage(position[1], .5);
                const scale = Math.max(media.width / mediaElement.naturalWidth, media.height / mediaElement.naturalHeight);
                const renderedWidth = mediaElement.naturalWidth * scale;
                const renderedHeight = mediaElement.naturalHeight * scale;
                const offsetX = media.x + ((media.width - renderedWidth) * positionX);
                const offsetY = media.y + ((media.height - renderedHeight) * positionY);
                const x = offsetX + (region.left * renderedWidth);
                const y = offsetY + (region.top * renderedHeight);
                const right = offsetX + (region.right * renderedWidth);
                const bottom = offsetY + (region.bottom * renderedHeight);

                return {
                    x: Math.round(x),
                    y: Math.round(y),
                    width: Math.round(right - x),
                    height: Math.round(bottom - y),
                    right: Math.round(right),
                    bottom: Math.round(bottom),
                };
            };
            const brand = projectFocalRegion(focalContract?.brand);
            const vehicle = projectFocalRegion(focalContract?.vehicle);

            return {
                viewport: { width: innerWidth, height: innerHeight },
                hero: rect(selectors.hero),
                finder,
                media,
                mediaAsset: currentPath,
                mediaNaturalSize: mediaElement ? { width: mediaElement.naturalWidth, height: mediaElement.naturalHeight } : null,
                focalContract: focalContract?.asset ?? null,
                brand,
                vehicle,
                finderOverlapsBrand: intersects(finder, brand),
                finderOverlapsVehicle: intersects(finder, vehicle),
            };
        });
        await writeFile(path.join(target, 'hero-geometry.json'), JSON.stringify(geometry, null, 2));
        expect(geometry.hero, 'hero must exist').not.toBeNull();
        expect(geometry.finder, 'finder must exist').not.toBeNull();
        expect(geometry.media, 'the explicit hero media selector must exist').not.toBeNull();
        expect(geometry.mediaNaturalSize?.width, 'hero media must be loaded').toBeGreaterThan(0);
        expect(geometry.focalContract, 'the published hero asset must have an audited focal contract').not.toBeNull();
        expect(geometry.brand, 'the EasyTire van brand focal region must project into the viewport').not.toBeNull();
        expect(geometry.vehicle, 'the customer-relevant vehicle/work focal region must project into the viewport').not.toBeNull();
        expect(geometry.finderOverlapsVehicle, 'finder must not cover the vehicle/work focal region').toBeFalsy();
        expect(geometry.finderOverlapsBrand, 'finder must not cover the EasyTire van brand focal region').toBeFalsy();
        await page.evaluate((snapshot) => {
            const layer = document.createElement('div');
            layer.dataset.auditHeroOverlay = 'true';
            const addRegion = (
                rect: { x: number; y: number; width: number; height: number } | null,
                label: string,
                color: string,
            ) => {
                if (!rect) return;
                const region = document.createElement('div');
                region.style.cssText = [
                    'position:absolute',
                    `left:${rect.x}px`,
                    `top:${rect.y + window.scrollY}px`,
                    `width:${rect.width}px`,
                    `height:${rect.height}px`,
                    `border:4px solid ${color}`,
                    `background:${color}22`,
                    'z-index:2147483646',
                    'pointer-events:none',
                    'box-sizing:border-box',
                ].join(';');
                const caption = document.createElement('span');
                caption.textContent = label;
                caption.style.cssText = `position:absolute;left:0;top:0;padding:4px 7px;background:${color};color:#fff;font:700 13px/1.2 sans-serif;letter-spacing:.02em;white-space:nowrap`;
                region.append(caption);
                layer.append(region);
            };
            addRegion(snapshot.finder, 'Finder', '#1769ff');
            addRegion(snapshot.brand, 'EasyTire brand focal region', '#df0a0a');
            addRegion(snapshot.vehicle, 'Vehicle/work focal region', '#b26a00');
            document.body.append(layer);
        }, geometry);
        await page.screenshot({ path: path.join(target, 'hero-focal-geometry.png') });
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
                        serviceCards: document.querySelectorAll('main .et-service-offer').length,
                        productCards: document.querySelectorAll('main .et-product-card').length,
                        shopLinks: document.querySelectorAll('main .et-shop-link').length,
                        promotions: document.querySelectorAll('[data-testid="homepage-promotion"]').length,
                        serviceMediaImages: document.querySelectorAll('.et-service-card__media > img').length,
                        serviceHonestFallbacks: document.querySelectorAll('.et-service-card__fallback').length,
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
        const routes = ['/', '/tjenester', '/tjenester/mobilt-dekkskift', '/dekk-og-felger', '/dekk', auditProductRoute, '/tjenester/bilbatteri/foresporsel', '/handlekurv', '/priser', '/om-oss', '/kontakt'];
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
                '.et-shop-categories a',
                '.et-product-card',
                '.et-service-offer',
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
        await recordCase(page, target, records, 'button-link', auditProductRoute, '.et-btn--link', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'desktop-nav', '/', '[data-testid="desktop-navigation"] > a');
        await recordCase(page, target, records, 'cart-action', '/', '[data-testid="header-cart"]', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'generic-interactive-card', '/dekk-og-felger', '.et-card--interactive', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'service-card', '/tjenester', '.et-service-offer', undefined, { keyboard: false, active: false, fullViewportEvidence: true });
        await recordCase(page, target, records, 'service-card-link', '/tjenester', '.et-service-card__content h2 > a');
        await recordCase(page, target, records, 'shop-category-link', '/', '[data-testid="quick-finder"] .et-shop-categories a');
        await recordCase(page, target, records, 'product-card', '/dekk', '.et-product-card', undefined, { keyboard: false, active: false, fullViewportEvidence: true });
        await recordCase(page, target, records, 'product-card-link', '/dekk', '[data-testid="catalog-product-link"]');
        await recordCase(page, target, records, 'category-tile', '/dekk', '.et-catalog-category:not(.is-active)', undefined, { fullViewportEvidence: true });
        await recordCase(page, target, records, 'filter-summary', '/dekk', '.et-filter-section > summary');
        await recordCase(page, target, records, 'quantity-control', auditProductRoute, '[data-testid="product-quantity"]');
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
        await expect(selectedCategory, 'selected catalogue category must be visible').toBeVisible();
        records.push({
            id: 'category-selected',
            route: '/dekk',
            selector: '.et-catalog-category.is-active',
            present: true,
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: { selected: await visualState(selectedCategory) },
        });

        await page.goto('/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const locationStep = page.getByTestId('seasonal-step-location');
        await locationStep.locator('input[name="fulfilment"][value="workshop"]').check();
        await locationStep.locator('[data-booking-next]').click();
        const vehicleStep = page.getByTestId('seasonal-step-vehicle-storage');
        await expect(vehicleStep).toBeVisible();
        await vehicleStep.locator('[name="vehicle"]').fill('Audit vehicle');
        await vehicleStep.locator('[data-booking-next]').click();
        await expect(page.getByTestId('seasonal-step-time')).toBeVisible();
        const slotSelect = page.getByTestId('booking-slot-select');
        const manualWindow = page.getByTestId('booking-manual-window');
        const scheduleConfigured = await slotSelect.isVisible();
        const enabledControl = scheduleConfigured ? slotSelect : manualWindow;
        const disabledControl = scheduleConfigured ? manualWindow : slotSelect;
        const enabledSelector = scheduleConfigured ? '[data-testid="booking-slot-select"]' : '[data-testid="booking-manual-window"]';
        const disabledSelector = scheduleConfigured ? '[data-testid="booking-manual-window"]' : '[data-testid="booking-slot-select"]';
        await expect(enabledControl, 'the active timing mode must expose a visible enabled control').toBeVisible();
        await expect(enabledControl).toBeEnabled();
        await expect(disabledControl, 'the inactive timing mode must remain in the DOM').toHaveCount(1);
        await expect(disabledControl).toBeDisabled();
        records.push({
            id: scheduleConfigured ? 'enabled-slot-select' : 'enabled-manual-window',
            route: page.url(),
            selector: enabledSelector,
            present: true,
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: { enabled: await visualState(enabledControl) },
        });
        records.push({
            id: scheduleConfigured ? 'disabled-manual-window-contract' : 'disabled-slot-select-contract',
            route: page.url(),
            selector: disabledSelector,
            present: true,
            visible: await disabledControl.isVisible(),
            requiredVisible: false,
            verification: 'dom-state',
            states: { disabled: await visualState(disabledControl) },
        });

        const required = records.filter((record) => record.requiredVisible);
        const missingRequiredCaseIds = required.filter((record) => !record.present || !record.visible).map((record) => record.id);
        const summary = {
            requiredCaseCount: required.length,
            verifiedRequiredCaseCount: required.length - missingRequiredCaseIds.length,
            missingRequiredCaseIds,
            allRequiredCasesPresent: missingRequiredCaseIds.length === 0,
            domStateCaseCount: records.filter((record) => record.verification === 'dom-state').length,
            bookingTimingMode: scheduleConfigured ? 'configured-slots' : 'manual-window',
        };
        await writeFile(path.join(target, 'interaction-matrix.json'), JSON.stringify(records, null, 2));
        await writeFile(path.join(target, 'interaction-matrix-summary.json'), JSON.stringify(summary, null, 2));
        expect(summary.missingRequiredCaseIds, 'required interaction matrix cannot contain silent gaps').toEqual([]);
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
        await expect(menuButton).toBeVisible();
        const menuStates: Record<string, VisualState | string | boolean | null> = await captureStandardStates(page, target, 'mobile-menu-button', menuButton);
        await menuButton.click();
        menuStates.open = await visualState(menuButton);
        await page.screenshot({ path: path.join(target, 'matrix-mobile-menu-open.png') });
        records.push({ id: 'mobile-menu-button', route: '/', selector: '[data-menu-toggle]', present: true, visible: true, requiredVisible: true, verification: 'required-visible', states: menuStates });

        const mobileMenuLink = page.getByTestId('mobile-booking-link');
        await expect(mobileMenuLink).toBeVisible();
        records.push({
            id: 'mobile-menu-link',
            route: '/',
            selector: '[data-testid="mobile-booking-link"]',
            present: true,
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: await captureStandardStates(page, target, 'mobile-menu-link', mobileMenuLink),
        });

        const mobileGroup = page.locator('.et-mobile-menu__group').first();
        const mobileGroupSummary = mobileGroup.locator('summary');
        const mobileGroupChild = mobileGroup.locator('.et-mobile-menu__children a').first();
        await expect(mobileGroupSummary).toBeVisible();
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
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: mobileGroupStates,
        });

        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const drawer = page.getByTestId('catalog-filter-drawer');
        if (!await drawer.evaluate((element) => (element as HTMLDetailsElement).open)) {
            await page.getByTestId('catalog-filter-toggle').click();
        }
        const filterApply = page.getByTestId('catalog-filter-apply');
        await expect(filterApply).toBeVisible();
        records.push({
            id: 'mobile-filter-apply',
            route: '/dekk',
            selector: '[data-testid="catalog-filter-apply"]',
            present: true,
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: await captureStandardStates(page, target, 'mobile-filter-apply', filterApply),
        });

        const checkbox = page.locator('.et-filter-toggle input').first();
        await expect(checkbox).toBeVisible();
        records.push({
            id: 'mobile-filter-checkbox',
            route: '/dekk',
            selector: '.et-filter-toggle input',
            present: true,
            visible: true,
            requiredVisible: true,
            verification: 'required-visible',
            states: await captureStandardStates(page, target, 'mobile-filter-checkbox', checkbox),
        });

        const missingRequiredCaseIds = records.filter((record) => record.requiredVisible && (!record.present || !record.visible)).map((record) => record.id);
        await writeFile(path.join(target, 'mobile-interaction-matrix.json'), JSON.stringify(records, null, 2));
        await writeFile(path.join(target, 'mobile-interaction-matrix-summary.json'), JSON.stringify({
            requiredCaseCount: records.filter((record) => record.requiredVisible).length,
            missingRequiredCaseIds,
            allRequiredCasesPresent: missingRequiredCaseIds.length === 0,
        }, null, 2));
        expect(missingRequiredCaseIds, 'mobile interaction matrix cannot contain silent gaps').toEqual([]);
    });

    test('records explicit navigation, CTA, process and singleton-promotion classifications', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop-1440x900');
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });

        await page.goto('/dekk', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const storeNavigation = page.getByTestId('catalog-category-rail').locator('a[aria-current="page"]').first();
        const storeNavigationLabel = storeNavigation.locator('.et-catalog-category__copy strong');
        const catalogueHeading = page.locator('main h1').first();
        await expect(storeNavigation).toBeVisible();
        await expect(storeNavigationLabel).toBeVisible();
        await expect(catalogueHeading).toBeVisible();
        const navigation = {
            classification: 'MATCH_CONTEXTUAL_HIERARCHY',
            catalogueNavigationText: (await storeNavigationLabel.innerText()).trim(),
            catalogueNavigationHref: await storeNavigation.getAttribute('href'),
            catalogueNavigationCurrent: await storeNavigation.getAttribute('aria-current'),
            pageHeading: (await catalogueHeading.innerText()).trim(),
            landingNavigationText: '',
            landingNavigationHref: '',
            rationale: 'Dekk og felger names the broad landing; Dekk names and links to the selected catalogue category while it is current.',
        };
        expect(navigation.catalogueNavigationText).toBe('Dekk');
        expect(navigation.catalogueNavigationHref).toMatch(/\/dekk$/);
        expect(navigation.catalogueNavigationCurrent).toBe('page');
        expect(navigation.pageHeading).toBe('Dekk');
        await page.screenshot({ path: path.join(target, 'critical-navigation-context.png') });

        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const broadStoreNavigation = page.locator('[data-testid="desktop-navigation"] a[href$="/dekk-og-felger"]').first();
        await expect(broadStoreNavigation).toBeVisible();
        navigation.landingNavigationText = (await broadStoreNavigation.innerText()).trim();
        navigation.landingNavigationHref = await broadStoreNavigation.getAttribute('href') ?? '';
        expect(navigation.landingNavigationText).toBe('Dekk og felger');

        await page.goto('/bilbatteri-tromso', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const batteryCtas = page.locator('main a[href$="/tjenester/bilbatteri/foresporsel"]');
        const batteryCtaCount = await batteryCtas.count();
        const stickyDuplicateCount = await page.locator('.et-service-anchor a[href$="/tjenester/bilbatteri/foresporsel"]').count();
        const primaryActions = page.locator('main a.et-btn--primary[href$="/tjenester/bilbatteri/foresporsel"]');
        const primaryPositions = await primaryActions.evaluateAll((elements) => elements.map((element) => {
            const rect = element.getBoundingClientRect();

            return Math.round(rect.y + window.scrollY);
        }));
        expect(stickyDuplicateCount, 'the sticky navigation must no longer duplicate the battery request action').toBe(0);
        expect(primaryActions, 'hero and final reading stage retain one primary request action each').toHaveCount(2);
        expect(primaryPositions[1] - primaryPositions[0], 'primary request actions must belong to separate reading stages').toBeGreaterThan(900);
        const battery = {
            classification: 'RESOLVED',
            repeatedCtaCount: batteryCtaCount,
            stickyDuplicateCount,
            primaryPositions,
            actions: await batteryCtas.evaluateAll((elements) => elements.map((element) => {
                const rect = element.getBoundingClientRect();
                return {
                    text: (element.textContent ?? '').trim().replace(/\s+/g, ' '),
                    className: element.getAttribute('class'),
                    x: Math.round(rect.x),
                    y: Math.round(rect.y + window.scrollY),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                };
            })),
            rationale: 'The hero and final reading stage retain their primary actions while sticky navigation is orientation-only and the intermediate aside is secondary.',
        };
        await page.screenshot({ path: path.join(target, 'critical-battery-repeated-cta.png'), fullPage: true });

        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await settle(page);
        const process = page.getByTestId('home-process');
        const processTimeline = process.locator('.et-v3-process');
        const processSteps = processTimeline.locator('.et-v3-process__step');
        await expect(process).toBeVisible();
        await expect(page.locator('body')).toHaveAttribute('data-storefront-design', 'modern');
        await expect(processTimeline).toHaveClass(/\bet-v3-process--timeline\b/);
        await expect(processSteps).toHaveCount(5);
        await expect(processSteps.locator('.et-v3-process__icon')).toHaveCount(0);
        const processComposition = {
            classification: 'MATCH',
            section: await visualState(process),
            stepCount: await processSteps.count(),
            iconCount: await processSteps.locator('.et-v3-process__icon').count(),
            steps: await processSteps.evaluateAll((elements) => elements.map((element) => {
                const rect = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {
                    text: (element.textContent ?? '').trim().replace(/\s+/g, ' '),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                    border: `${style.borderTopWidth} ${style.borderTopStyle} ${style.borderTopColor}`,
                };
            })),
            rationale: 'Modern uses the approved five-stage numbered timeline without the legacy process icons; Classic retains its separate four-step icon treatment.',
        };
        await process.screenshot({ path: path.join(target, 'critical-home-process.png') });

        const promotions = page.getByTestId('homepage-promotions');
        const promotionCards = promotions.getByTestId('homepage-promotion');
        await expect(promotions).toBeVisible();
        const promotionCardCount = await promotionCards.count();
        expect(promotionCardCount, 'the content fixture must expose at least one active promotion').toBeGreaterThan(0);
        const isSingletonPromotion = promotionCardCount === 1;
        const singletonPromotion = {
            classification: isSingletonPromotion ? 'MATCH_SINGLETON' : 'NOT_APPLICABLE_MULTI_ITEM_FIXTURE',
            cardCount: promotionCardCount,
            section: await visualState(promotions),
            card: await visualState(promotionCards.first()),
            rationale: isSingletonPromotion
                ? 'A single active promotion deliberately expands into one image-led feature instead of leaving an empty grid slot.'
                : 'The exact-head E2E fixture contains multiple active promotions, so singleton composition is recorded as not applicable instead of fabricating a one-card state.',
        };
        await promotions.screenshot({ path: path.join(target, 'critical-home-singleton-promotion.png') });

        await writeFile(path.join(target, 'explicit-composition-classifications.json'), JSON.stringify({
            navigation,
            battery,
            process: processComposition,
            singletonPromotion,
        }, null, 2));
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
