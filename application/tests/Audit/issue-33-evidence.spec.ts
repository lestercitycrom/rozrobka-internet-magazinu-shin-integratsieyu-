import { expect, test, type Locator, type Page } from '@playwright/test';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

type Viewport = { width: number; height: number };
type Box = { x: number; y: number; width: number; height: number };

const viewports: Viewport[] = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 1280, height: 800 },
    { width: 390, height: 844 },
    { width: 320, height: 800 },
];
const evidenceRoot = path.resolve(process.cwd(), '../evidence/issue-33/after');
const baselineRoot = path.resolve(process.cwd(), '../evidence');
const tireSku = '000205550016';

const surfaces = [
    ['home', '/'],
    ['services', '/tjenester'],
    ['service-detail', '/tjenester/bilbatteri'],
    ['request', '/tjenester/bilbatteri/foresporsel'],
    ['store', '/dekk-og-felger'],
    ['catalog', '/dekk'],
    ['product', `/produkter/${tireSku}`],
    ['cart-empty', '/handlekurv'],
    ['prices', '/priser'],
    ['about', '/om-oss'],
    ['contact', '/kontakt'],
    ['legal', '/kjopsvilkar'],
    ['not-found', '/issue-33-intentional-404'],
] as const;

async function settle(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        const images = Array.from(document.images);
        images.forEach((image) => image.loading = 'eager');
        await Promise.all(images.map((image) => image.complete
            ? image.decode().catch(() => undefined)
            : new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
                window.setTimeout(resolve, 2500);
            })));
    });
    await page.waitForTimeout(120);
}

async function box(locator: Locator): Promise<Box> {
    const value = await locator.boundingBox();
    expect(value).not.toBeNull();

    return value!;
}

function expectBoxClose(actual: Box, expected: Box, label: string, tolerance = 1.1): void {
    for (const key of ['x', 'y', 'width', 'height'] as const) {
        expect(Math.abs(actual[key] - expected[key]), `${label}.${key}`).toBeLessThanOrEqual(tolerance);
    }
}

async function noOverflow(page: Page): Promise<Record<string, unknown>> {
    const layout = await page.evaluate(() => ({
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        documentHeight: document.documentElement.scrollHeight,
    }));
    expect(layout.scrollWidth).toBeLessThanOrEqual(layout.viewportWidth + 1);

    return layout;
}

async function state(locator: Locator): Promise<Record<string, unknown>> {
    return locator.evaluate((element) => {
        const style = getComputedStyle(element);
        return {
            className: element.getAttribute('class'),
            borderRadius: style.borderRadius,
            borderColor: style.borderColor,
            backgroundColor: style.backgroundColor,
            boxShadow: style.boxShadow,
            outline: style.outline,
            focusVisible: element.matches(':focus-visible'),
            disabled: (element as HTMLButtonElement).disabled ?? false,
            open: (element as HTMLDetailsElement).open ?? false,
        };
    });
}

test('captures every required page family at all six accepted viewports', async ({ page }) => {
    test.setTimeout(600_000);
    await mkdir(evidenceRoot, { recursive: true });
    const layouts: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        for (const [id, url] of surfaces) {
            const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
            await settle(page);
            const layout = await noOverflow(page);
            const status = response?.status() ?? null;
            if (id === 'not-found') expect(status).toBe(404);
            else expect(status, `${url} must not fail`).toBeLessThan(500);
            layouts.push({ id, url, viewport, status, ...layout });
            await page.screenshot({
                path: path.join(evidenceRoot, `${id}-${viewport.width}x${viewport.height}.jpg`),
                fullPage: true,
                type: 'jpeg',
                quality: 62,
            });
        }
    }

    await writeFile(path.join(evidenceRoot, 'surface-layouts.json'), `${JSON.stringify(layouts, null, 2)}\n`);
});

test('preserves accepted home service cart and product geometry', async ({ page }) => {
    const heroBaseline = JSON.parse(await readFile(path.join(baselineRoot, 'issue-30/hero-geometry.json'), 'utf8'));
    const serviceBaseline = JSON.parse(await readFile(path.join(baselineRoot, 'issue-31/service-detail-geometry.json'), 'utf8'));
    const commerceBaseline = JSON.parse(await readFile(path.join(baselineRoot, 'issue-32/geometry-and-state.json'), 'utf8'));
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        await settle(page);
        const expectedHero = heroBaseline.find((item: { viewport: Viewport }) => item.viewport.width === viewport.width);
        expectBoxClose(await box(page.getByTestId('quick-finder')), expectedHero.finder, `hero-${viewport.width}.finder`);
        expectBoxClose(await box(page.locator('.et-v3-hero__content')), expectedHero.content, `hero-${viewport.width}.content`);
        expectBoxClose(await box(page.locator('.et-v3-hero__primary-action')), expectedHero.primaryAction, `hero-${viewport.width}.action`);

        await page.goto('/handlekurv');
        await settle(page);
        const expectedCart = commerceBaseline.emptyCart.find((item: { viewport: Viewport }) => item.viewport.width === viewport.width);
        expectBoxClose(await box(page.getByTestId('cart-empty')), expectedCart.box, `cart-${viewport.width}`);

        if (viewport.width !== 1920) {
            await page.goto('/tjenester/bilbatteri');
            await settle(page);
            const expectedService = serviceBaseline.find((item: { viewport: Viewport }) => item.viewport.width === viewport.width);
            expectBoxClose(await box(page.locator('.et-service-detail__top')), expectedService.top, `service-${viewport.width}.top`);
            expectBoxClose(await box(page.locator('.et-service-final-action')), expectedService.finalAction, `service-${viewport.width}.final`, 1.6);
        }

        const expectedPurchase = commerceBaseline.purchase.find((item: { viewport: Viewport }) => item.viewport.width === viewport.width);
        if (expectedPurchase) {
            await page.goto('/produkter/NDI-TEST-4765205551649102');
            await settle(page);
            expectBoxClose(await box(page.getByTestId('product-quantity')), expectedPurchase.quantity, `product-${viewport.width}.quantity`, 1.6);
            expectBoxClose(await box(page.getByTestId('product-purchase-actions')), expectedPurchase.actions, `product-${viewport.width}.actions`, 1.6);
            expectBoxClose(await box(page.getByTestId('integration-test-quantity-note')), expectedPurchase.note, `product-${viewport.width}.note`, 1.6);
        }

        measurements.push({ viewport, preserved: true });
    }

    await writeFile(path.join(evidenceRoot, 'baseline-geometry-comparison.json'), `${JSON.stringify(measurements, null, 2)}\n`);
});

test('captures explicit geometry and named interaction states without side effects', async ({ page }) => {
    const records: Record<string, unknown> = {};
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await settle(page);

    const headerAction = page.getByTestId('header-booking');
    records.headerDefault = await state(headerAction);
    await headerAction.hover();
    records.headerHover = await state(headerAction);
    await headerAction.focus();
    records.headerFocus = await state(headerAction);
    expect((records.headerFocus as { focusVisible: boolean }).focusVisible).toBeTruthy();

    await page.evaluate(() => window.scrollTo({ top: 1000, behavior: 'instant' }));
    await page.waitForTimeout(160);
    records.headerScrolled = await state(page.locator('[data-site-header]'));
    await page.screenshot({ path: path.join(evidenceRoot, 'state-header-scrolled-1440x900.jpg'), type: 'jpeg', quality: 72 });

    await page.goto('/dekk');
    await settle(page);
    records.desktopFilter = await state(page.locator('.et-production-filter__panel'));
    expect((records.desktopFilter as { borderRadius: string }).borderRadius).toBe('2px');
    expect(await page.locator('.et-product-card').first().evaluate((element) => getComputedStyle(element).borderRadius)).toBe('2px');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/dekk');
    await page.getByTestId('catalog-filter-toggle').click();
    const drawer = page.getByTestId('catalog-filter-drawer');
    records.filterOpen = await state(drawer.locator('.et-production-filter__panel'));
    expect((records.filterOpen as { borderRadius: string }).borderRadius).toBe('2px 2px 0px 0px');

    await drawer.locator('select[name="width"]').selectOption('205');
    await page.route('**/dekk?*', async (route) => {
        if (route.request().resourceType() !== 'fetch') return route.continue();
        await new Promise((resolve) => setTimeout(resolve, 500));
        return route.continue();
    });
    await page.getByTestId('catalog-filter-apply').click();
    await expect(page.getByTestId('catalog-filter-apply')).toBeDisabled();
    records.filterDisabled = await state(page.getByTestId('catalog-filter-apply'));
    await page.screenshot({ path: path.join(evidenceRoot, 'state-filter-disabled-390x844.jpg'), type: 'jpeg', quality: 72 });
    await expect(page.locator('[data-catalog-live-root]')).toHaveAttribute('aria-busy', 'false');
    await page.unroute('**/dekk?*');

    await page.goto('/dekk');
    await page.getByTestId('catalog-filter-toggle').click();
    await page.route('**/dekk?*', (route) => route.request().resourceType() === 'fetch'
        ? route.fulfill({ status: 503, contentType: 'text/plain', body: 'Synthetic Issue 33 failure' })
        : route.continue());
    await page.getByTestId('catalog-filter-drawer').locator('select[name="width"]').selectOption('205');
    await page.getByTestId('catalog-filter-apply').click();
    await expect(page.getByTestId('catalog-filter-error')).toBeVisible();
    records.filterError = await state(page.getByTestId('catalog-filter-error'));
    await page.screenshot({ path: path.join(evidenceRoot, 'state-filter-error-390x844.jpg'), type: 'jpeg', quality: 72 });
    await page.unroute('**/dekk?*');

    await page.goto('/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile');
    await settle(page);
    const selectedRadio = page.locator('input[type="radio"]:checked').first();
    records.selectedRadio = {
        ...await state(selectedRadio),
        ...await selectedRadio.evaluate((element) => {
        const style = getComputedStyle(element);

        return {
            functionalCircle: style.getPropertyValue('--et-radius-functional-circle'),
            appearance: style.appearance,
            width: style.width,
            height: style.height,
        };
        }),
    };
    expect((records.selectedRadio as { functionalCircle: string }).functionalCircle.trim()).toBe('50%');
    expect((records.selectedRadio as { appearance: string }).appearance).toBe('auto');
    expect((records.selectedRadio as { width: string; height: string }).width)
        .toBe((records.selectedRadio as { width: string; height: string }).height);

    await page.goto('/kontakt');
    await settle(page);
    if (await page.locator('.et-map__pin').count()) {
        records.mapPin = await state(page.locator('.et-map__pin'));
        expect((records.mapPin as { borderRadius: string }).borderRadius).toBe('999px 999px 999px 2px');
    } else {
        records.mapPin = 'not rendered because the configured map iframe is available';
    }

    await writeFile(path.join(evidenceRoot, 'interaction-and-geometry-states.json'), `${JSON.stringify(records, null, 2)}\n`);
});
