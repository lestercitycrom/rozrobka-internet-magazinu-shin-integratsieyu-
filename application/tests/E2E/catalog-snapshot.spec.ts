import { expect, test } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginAdmin, productCard } from './helpers';

const live = Boolean(process.env.SNAPSHOT_LIVE_URL);
const tire = live ? '063966245751629400' : '000205550016';
const wheel = live ? '09730007017510846410' : 'DEMO-WHEEL-18112';
const sandbox = 'NDI-TEST-4765205551649102';
const sourceDate = live ? '12.08.2026' : '01.03.2026';

test('historical tyre and wheel prices are localized, dated and display-only', async ({ page }, info) => {
    const ru = info.project.name.includes('-ru-');
    const prefix = ru ? '/ru' : '';
    const label = ru ? 'Тестовая цена' : 'Testpris';
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const [route, sku] of [['dekk', tire], ['felger', wheel]]) {
        await page.goto(`${prefix}/${route}?q=${sku}`);
        await expect(page.locator('html')).toHaveAttribute('lang', ru ? 'ru' : 'nb');
        await expect(page.getByTestId('snapshot-catalog-notice')).toBeVisible();
        const card = productCard(page, sku);
        await expect(card).toBeVisible();
        await expect(card.getByTestId('snapshot-price-note')).toContainText(label);
        await expect(card.getByTestId('snapshot-price-note')).toContainText(sourceDate);
        await expect(card.locator('.et-price')).toContainText(/\d/);
        await expect(card.getByTestId('catalog-add-to-cart')).toHaveCount(0);
        await expectNoGlobalOverflow(page);
        await page.screenshot({ path: info.outputPath(`${route}-listing.png`), fullPage: true });
        await card.getByTestId('catalog-product-link').click();
        await expect(page.getByTestId('snapshot-price-note')).toContainText(sourceDate);
        await expect(page.getByTestId('product-cart-form')).toHaveCount(0);
        await expect(page.getByTestId('product-price-unavailable')).toHaveCount(0);
        const structured = await page.locator('script[type="application/ld+json"]').allTextContents();
        expect(structured.join('')).not.toMatch(/"@type"\s*:\s*"Offer"/);
        await expectNoGlobalOverflow(page);
        await page.screenshot({ path: info.outputPath(`${route}-detail.png`), fullPage: true });
    }
    await page.goto(`${prefix}/produkter/${sandbox}`);
    await expect(page.getByTestId('product-cart-form')).toBeVisible();
    await expect(page.locator('.et-product-price')).toContainText('10,00 kr');
    await expect(page.getByTestId('snapshot-price-note')).toHaveCount(0);
    await expect(page.locator('input[name="quantity"]')).toHaveValue('2');
    await expectNoGlobalOverflow(page);
    expect(errors).toEqual([]);
});

test('direct real cart request is rejected and sandbox cart still works locally', async ({ page }, info) => {
    test.skip(live, 'Live smoke is read-only: no sessions/orders/payment or cart mutation.');
    const prefix = info.project.name.includes('-ru-') ? '/ru' : '';
    await page.goto(`${prefix}/produkter/${sandbox}`);
    const csrf = await page.locator('input[name="_token"]').first().inputValue();
    const response = await page.request.post(`${prefix}/handlekurv`, {
        form: { _token: csrf, sku: tire, quantity: '1', intent: 'cart' }, maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    await page.goto(`${prefix}/handlekurv`);
    await expect(page.getByTestId('cart-empty')).toBeVisible();
    await page.goto(`${prefix}/produkter/${sandbox}`);
    await page.getByTestId('product-cart-form').locator('button[value="cart"]').click();
    await page.goto(`${prefix}/handlekurv`);
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-line')).toHaveAttribute('data-product-sku', sandbox);
});

test('admin explains intentional snapshot pause without hiding raw freshness status', async ({ page }, info) => {
    test.skip(live, 'No customer credentials used in live browser smoke.');
    const ru = info.project.name.includes('-ru-');
    await loginAdmin(page);
    await ensureAdminLocale(page, ru ? 'ru' : 'nb');
    await page.goto('/admin/health');
    await expect(page.getByTestId('ndi-catalog-mode')).toContainText(ru ? 'Автоматический импорт приостановлен' : 'Automatisk import er satt på pause');
    await expect(page.getByTestId('ndi-catalog-mode')).toContainText(ru ? 'Продажа обычных товаров закрыта' : 'Vanlige varer kan ikke bestilles');
    await expectNoGlobalOverflow(page);
    await page.screenshot({ path: info.outputPath('admin-health.png'), fullPage: true });
});
