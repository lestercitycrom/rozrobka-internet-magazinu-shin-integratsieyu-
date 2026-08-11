import { expect, test } from '@playwright/test';

function overlapArea(a: { x: number; y: number; width: number; height: number }, b: { x: number; y: number; width: number; height: number }): number {
    const width = Math.max(0, Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x));
    const height = Math.max(0, Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y));
    return width * height;
}

test('cart fallback does not cover update or remove controls', async ({ page }) => {
    await page.goto('/cart.html');
    const line = page.getByTestId('cart-line');
    const media = line.getByTestId('cart-line-media');
    const quantity = line.getByTestId('cart-quantity');
    const update = line.getByTestId('cart-update');
    const remove = line.getByTestId('cart-remove');
    await expect(media.getByTestId('product-visual-fallback')).toBeVisible();
    await expect(quantity).toBeVisible();
    await expect(update).toBeVisible();
    await expect(remove).toBeVisible();

    const [mediaBox, quantityBox, updateBox, removeBox] = await Promise.all([
        media.boundingBox(), quantity.boundingBox(), update.boundingBox(), remove.boundingBox(),
    ]);
    expect(mediaBox && quantityBox && updateBox && removeBox).toBeTruthy();
    expect(overlapArea(mediaBox!, quantityBox!)).toBe(0);
    expect(overlapArea(mediaBox!, updateBox!)).toBe(0);
    expect(overlapArea(mediaBox!, removeBox!)).toBe(0);

    await quantity.fill('2');
    await update.click();
    await remove.click();
    await expect(page.locator('[data-cart-empty]')).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
});

test('product buy box precedes the full technical specification', async ({ page }) => {
    await page.goto('/product.html');
    const order = await page.evaluate(() => {
        const buy = document.querySelector('[data-testid="product-buy-box"]');
        const technical = document.querySelector('[data-testid="technical-details"]');
        if (!buy || !technical) return null;
        return Boolean(buy.compareDocumentPosition(technical) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    expect(order).toBe(true);
    await expect(page.getByTestId('product-buy-box')).toBeVisible();
});

test('map preview stays external-only without an approved embed URL', async ({ page }) => {
    await page.goto('/contact.html');
    const card = page.getByTestId('map-external-only');
    await expect(card).toBeVisible();
    await expect(card.locator('iframe')).toHaveCount(0);
    await expect(card.locator('a')).toHaveAttribute('href', 'https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic');
    await expect(page.locator('[data-map-consent]')).toHaveCount(0);
});

test('current and future booking flows are visibly distinguished', async ({ page }) => {
    await page.goto('/booking.html');
    await expect(page.getByTestId('seasonal-service-request')).toBeVisible();
    await expect(page.locator('input[name="vehicle"]')).toHaveCount(1);
    await expect(page.locator('input[name^="vehicles["]')).toHaveCount(0);

    await page.goto('/booking-future.html');
    await expect(page.getByTestId('future-contract-warning')).toBeVisible();
    await expect(page.getByText(/P0-backendkontrakten/)).toBeVisible();
    await expect(page.getByText(/Mister Red implementerer kontrakten separat/)).toBeVisible();
});
