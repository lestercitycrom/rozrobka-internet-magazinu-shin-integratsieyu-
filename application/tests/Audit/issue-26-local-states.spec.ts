import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const tireSku = '000205550016';

function rawRoot(testInfo: TestInfo): string {
    return path.resolve(process.cwd(), '../tmp/issue-26/local-raw', testInfo.project.name);
}

async function settle(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        document.querySelectorAll<HTMLImageElement>('img').forEach((image) => image.loading = 'eager');
    });
    await page.waitForTimeout(250);
}

async function addFixtureProduct(page: Page): Promise<void> {
    await page.goto('/dekk?width=205&aspect=55', { waitUntil: 'domcontentloaded' });
    const card = page.locator(`[data-testid="catalog-product"][data-product-sku="${tireSku}"]`);
    await expect(card).toBeVisible();
    await card.getByTestId('catalog-add-to-cart').click();
    await expect(page.getByTestId('header-cart')).toContainText('1');
    await page.goto('/handlekurv', { waitUntil: 'domcontentloaded' });
}

test.describe('Issue #26 safe local synthetic visual states', () => {
    test('captures populated catalog, product, cart and checkout', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });

        await page.goto('/dekk?width=205&aspect=55', { waitUntil: 'domcontentloaded' });
        await settle(page);
        await page.screenshot({ path: path.join(target, 'catalog-populated.png'), fullPage: true });

        await page.goto(`/produkter/${tireSku}`, { waitUntil: 'domcontentloaded' });
        await settle(page);
        await expect(page.getByTestId('product-primary-media')).toBeVisible();
        await page.screenshot({ path: path.join(target, 'product-with-media.png'), fullPage: true });

        await addFixtureProduct(page);
        await settle(page);
        await page.screenshot({ path: path.join(target, 'cart-populated.png'), fullPage: true });

        await page.getByTestId('cart-checkout').click();
        await expect(page).toHaveURL(/\/kasse$/);
        await settle(page);
        await page.screenshot({ path: path.join(target, 'checkout-default.png'), fullPage: true });
    });

    test('captures server-rendered checkout validation without external effects', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await addFixtureProduct(page);
        await page.getByTestId('cart-checkout').click();

        await page.getByTestId('checkout-name').fill('Audit Customer');
        await page.getByTestId('checkout-email').fill('audit@example.test');
        await page.getByTestId('checkout-phone').fill('12');
        await page.getByTestId('checkout-fitment-confirmation').locator('input').check();
        await page.getByTestId('checkout-privacy').locator('input').check();
        await page.getByTestId('checkout-terms').locator('input').check();
        await page.getByTestId('checkout-form').evaluate((form) => (form as HTMLFormElement).noValidate = true);
        await page.getByTestId('checkout-submit').click();

        await expect(page).toHaveURL(/\/kasse$/);
        await expect(page.getByTestId('checkout-phone')).toHaveAttribute('aria-invalid', 'true');
        await settle(page);
        await page.screenshot({ path: path.join(target, 'checkout-validation-error.png'), fullPage: true });
        await writeFile(path.join(target, 'checkout-validation.json'), JSON.stringify({
            phoneInvalid: await page.getByTestId('checkout-phone').getAttribute('aria-invalid'),
            error: (await page.getByRole('alert').first().innerText()).trim(),
        }, null, 2));
    });

    test('captures booking form default, disabled and validation states', async ({ page }, testInfo) => {
        const target = rawRoot(testInfo);
        await mkdir(target, { recursive: true });
        await page.goto('/tjenester/bilbatteri/foresporsel', { waitUntil: 'domcontentloaded' });
        await settle(page);
        await page.screenshot({ path: path.join(target, 'service-request-default.png'), fullPage: true });

        const form = page.getByTestId('generic-service-request');
        await form.evaluate((element) => (element as HTMLFormElement).noValidate = true);
        await page.getByTestId('generic-submit').click();
        await expect(page.getByTestId('request-errors')).toBeVisible();
        await settle(page);
        await page.screenshot({ path: path.join(target, 'service-request-validation-error.png'), fullPage: true });
        const nbErrors = await page.locator('.et-form-error').allInnerTexts();

        await page.goto('/ru/tjenester/bilbatteri/foresporsel', { waitUntil: 'domcontentloaded' });
        const russianForm = page.getByTestId('generic-service-request');
        await russianForm.evaluate((element) => (element as HTMLFormElement).noValidate = true);
        await page.getByTestId('generic-submit').click();
        await expect(page.getByTestId('request-errors')).toBeVisible();
        const ruErrors = await page.locator('.et-form-error').allInnerTexts();
        await page.screenshot({ path: path.join(target, 'service-request-validation-error-ru.png'), fullPage: true });
        await writeFile(path.join(target, 'service-request-validation.json'), JSON.stringify({ nbErrors, ruErrors }, null, 2));

        await page.goto('/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('seasonal-step-location')).toBeVisible();
        await expect(page.getByTestId('booking-slot-select')).toBeEnabled();
        await expect(page.getByTestId('booking-manual-window')).toBeDisabled();
        await page.screenshot({ path: path.join(target, 'seasonal-request-disabled-slot.png'), fullPage: true });
    });
});
