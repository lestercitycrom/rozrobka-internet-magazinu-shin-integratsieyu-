import { expect, test } from '@playwright/test';
import { cartLine, expectNoGlobalOverflow, productCard } from './helpers';

const tireSku = '000205550016';

test('buyer can use the private Russian storefront, cart and checkout', async ({ page }) => {
    await page.goto('/ru');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.getByRole('heading', { level: 1, name: 'Меняем колёса там, где стоит автомобиль.' })).toBeVisible();

    await page.goto('/ru/dekk?width=205');
    const card = productCard(page, tireSku);
    await expect(card).toBeVisible();
    await card.getByTestId('catalog-product-link').click();
    await page.getByTestId('product-add-to-cart').click();
    await page.getByTestId('header-cart').click();

    const line = cartLine(page, tireSku);
    await expect(line).toBeVisible();
    await expect(line.getByTestId('cart-line-quantity')).toHaveValue('1');
    await page.getByTestId('cart-checkout').click();
    await expect(page).toHaveURL(/\/ru\/kasse$/);
    await expect(page.getByTestId('checkout-form')).toBeVisible();
    await expect(page.locator('input[name="idempotency_key"]')).toHaveValue(/[0-9a-f-]{36}/);

    await page.goto('/ru/tjenester/dekkhotell/foresporsel');
    await expect(page.getByTestId('generic-service-request')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Norsk innhold');
    await expectNoGlobalOverflow(page);
});
