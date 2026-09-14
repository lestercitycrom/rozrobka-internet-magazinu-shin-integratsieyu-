import { expect, type Locator, type Page } from '@playwright/test';

export const localePrefix = (projectName: string): string => projectName.includes('ru') ? '/ru' : '';
export const isRussianProject = (projectName: string): boolean => projectName.includes('ru');
export const isMobileProject = (projectName: string): boolean => projectName.includes('mobile') || projectName.includes('cart-');

export async function expectNoGlobalOverflow(page: Page): Promise<void> {
    await expect.poll(async () => page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth
    )).toBeLessThanOrEqual(1);
}

export function productCard(page: Page, sku: string): Locator {
    return page.locator(`[data-testid="catalog-product"][data-product-sku="${sku}"]`);
}

export function cartLine(page: Page, sku: string): Locator {
    return page.locator(`[data-testid="cart-line"][data-product-sku="${sku}"]`);
}

export async function addCatalogProduct(page: Page, route: string, sku: string): Promise<void> {
    await page.goto(route);
    const card = productCard(page, sku);
    await expect(card).toBeVisible();
    await card.getByTestId('catalog-add-to-cart').click();
}

export async function ensureCatalogFiltersVisible(page: Page): Promise<Locator> {
    const form = page.getByTestId('catalog-filters');
    if (!await form.isVisible()) {
        await page.getByTestId('catalog-filter-toggle').click();
    }
    await expect(form).toBeVisible();
    return form;
}

export async function loginAdmin(page: Page): Promise<void> {
    const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
    const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';
    await page.goto('/admin/login');
    await page.locator('input[name="email"]').fill(adminEmail);
    await page.locator('input[name="password"]').fill(adminPassword);
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

export async function loginOperations(page: Page): Promise<void> {
    const operationsEmail = process.env.E2E_OPERATIONS_EMAIL ?? 'e2e-operations@example.test';
    const operationsPassword = process.env.E2E_OPERATIONS_PASSWORD ?? 'e2e-operations-password';
    await page.goto('/admin/login');
    await page.locator('input[name="email"]').fill(operationsEmail);
    await page.locator('input[name="password"]').fill(operationsPassword);
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

export async function ensureAdminLocale(page: Page, locale: 'nb' | 'ru'): Promise<void> {
    if (await page.locator('html').getAttribute('lang') === locale) {
        return;
    }

    await page.getByTestId(`switch-language-${locale}`).click();
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
}

export function overlapArea(a: { x: number; y: number; width: number; height: number }, b: { x: number; y: number; width: number; height: number }): number {
    const width = Math.max(0, Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x));
    const height = Math.max(0, Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y));
    return width * height;
}
