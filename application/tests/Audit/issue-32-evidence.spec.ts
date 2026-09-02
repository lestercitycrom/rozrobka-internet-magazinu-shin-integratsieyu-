import { expect, test, type Page } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const evidenceRoot = path.resolve(process.cwd(), '../evidence/issue-32');
const tireSku = '000205550016';

async function settle(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        document.querySelectorAll<HTMLImageElement>('img').forEach((image) => image.loading = 'eager');
    });
    await page.waitForTimeout(180);
}

async function capture(page: Page, name: string, fullPage = true): Promise<void> {
    await settle(page);
    await page.screenshot({ path: path.join(evidenceRoot, name), fullPage });
}

test('captures the complete Issue #32 synthetic catalogue and commerce evidence', async ({ page }) => {
    await mkdir(evidenceRoot, { recursive: true });
    const evidence: Record<string, unknown> = { desktopFilters: [], mobileFilters: [], purchase: [], emptyCart: [] };

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1440, height: 900 },
        { width: 1366, height: 768 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/dekk');
        await capture(page, `catalog-${viewport.width}x${viewport.height}.png`);

        if (viewport.width >= 1280) {
            const geometry = await page.locator('.et-production-filter__panel').evaluate((element) => ({
                viewport: { width: innerWidth, height: innerHeight },
                clientHeight: element.clientHeight,
                scrollHeight: element.scrollHeight,
                overflowY: getComputedStyle(element).overflowY,
                documentHeight: document.documentElement.scrollHeight,
            }));
            (evidence.desktopFilters as unknown[]).push(geometry);
        } else {
            await page.getByTestId('catalog-filter-toggle').click();
            const form = page.getByTestId('catalog-filters');
            await form.locator('select[name="width"]').selectOption('205');
            await expect(page).not.toHaveURL(/width=205/);
            await capture(page, `catalog-filter-draft-${viewport.width}x${viewport.height}.png`, false);
            (evidence.mobileFilters as unknown[]).push(await form.evaluate((element) => ({
                viewport: { width: innerWidth, height: innerHeight },
                draft: element.getAttribute('data-catalog-draft'),
                width: (element.querySelector('[name="width"]') as HTMLSelectElement).value,
                url: location.href,
            })));
            await page.getByTestId('catalog-filter-close').click();
        }
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/dekk');
    const category = page.getByTestId('catalog-category-wheels');
    await category.hover();
    await page.waitForTimeout(200);
    await capture(page, 'catalog-category-hover-1440x900.png', false);
    await category.focus();
    await capture(page, 'catalog-category-focus-1440x900.png', false);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/dekk');
    await page.getByTestId('catalog-filter-toggle').click();
    await page.getByTestId('catalog-filters').locator('select[name="width"]').selectOption('205');
    await page.route('**/dekk?*', async (route) => {
        if (route.request().resourceType() !== 'fetch') return route.continue();
        await new Promise((resolve) => setTimeout(resolve, 650));
        return route.continue();
    });
    await page.getByTestId('catalog-filter-apply').click();
    await expect(page.getByTestId('catalog-filter-apply')).toBeDisabled();
    await capture(page, 'catalog-filter-busy-390x844.png', false);
    await expect(page).toHaveURL(/width=205/);
    await page.unroute('**/dekk?*');

    await page.goto('/dekk');
    await page.getByTestId('catalog-filter-toggle').click();
    await page.getByTestId('catalog-filters').locator('select[name="width"]').selectOption('205');
    await page.route('**/dekk?*', (route) => route.request().resourceType() === 'fetch'
        ? route.fulfill({ status: 503, contentType: 'text/plain', body: 'Synthetic failure' })
        : route.continue());
    await page.getByTestId('catalog-filter-apply').click();
    await expect(page.getByTestId('catalog-filter-error')).toBeVisible();
    await capture(page, 'catalog-filter-error-390x844.png', false);
    await page.unroute('**/dekk?*');

    for (const viewport of [
        { width: 1440, height: 900 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto(`/produkter/${tireSku}`);
        await capture(page, `product-media-${viewport.width}x${viewport.height}.png`);
        await page.goto('/produkter/DEMO-WHEEL-18112');
        await capture(page, `product-fallback-${viewport.width}x${viewport.height}.png`);
        await page.goto('/produkter/NDI-TEST-4765205551649102');
        const purchase = await page.getByTestId('product-cart-form').evaluate((form) => {
            const rect = (selector: string) => form.querySelector<HTMLElement>(selector)!.getBoundingClientRect();
            return {
                viewport: { width: innerWidth, height: innerHeight },
                quantity: rect('[data-testid="product-quantity"]'),
                actions: rect('[data-testid="product-purchase-actions"]'),
                note: rect('[data-testid="integration-test-quantity-note"]'),
            };
        });
        (evidence.purchase as unknown[]).push(purchase);
        await capture(page, `product-sandbox-buy-box-${viewport.width}x${viewport.height}.png`);
    }

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1440, height: 900 },
        { width: 1366, height: 768 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/handlekurv');
        const cart = await page.getByTestId('cart-empty').evaluate((element) => ({
            viewport: { width: innerWidth, height: innerHeight },
            box: element.getBoundingClientRect(),
            recoveryActions: element.querySelectorAll('a').length,
        }));
        (evidence.emptyCart as unknown[]).push(cart);
        await capture(page, `cart-empty-${viewport.width}x${viewport.height}.png`);
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`/dekk?width=205&aspect=55`);
    await page.locator(`[data-testid="catalog-product"][data-product-sku="${tireSku}"]`).getByTestId('catalog-add-to-cart').click();
    await page.goto('/handlekurv');
    await capture(page, 'cart-populated-1440x900.png');
    await page.getByTestId('cart-checkout').click();
    await capture(page, 'checkout-populated-1440x900.png');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/handlekurv');
    await capture(page, 'cart-populated-390x844.png');
    await page.getByTestId('cart-checkout').click();
    await capture(page, 'checkout-populated-390x844.png');

    await writeFile(path.join(evidenceRoot, 'geometry-and-state.json'), JSON.stringify(evidence, null, 2));
});
