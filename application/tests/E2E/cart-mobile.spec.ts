import { expect, test } from '@playwright/test';
import { addCatalogProduct, cartLine, expectNoGlobalOverflow, overlapArea } from './helpers';

const wheelSku = 'DEMO-WHEEL-18112';

test('wheel without media keeps quantity, update and remove usable on a narrow viewport', async ({ page }) => {
    await addCatalogProduct(page, '/felger?diameter=18&bolt_holes=5&pcd=112', wheelSku);
    await page.goto('/handlekurv');

    const line = cartLine(page, wheelSku);
    await expect(line).toBeVisible();
    const media = line.getByTestId('cart-line-media');
    const fallback = media.getByTestId('product-visual-fallback');
    const quantity = line.getByTestId('cart-line-quantity');
    const update = line.getByTestId('cart-line-update');
    const remove = line.getByTestId('cart-line-remove');

    await expect(fallback).toBeVisible();
    await expect(quantity).toBeVisible();
    await expect(update).toBeVisible();
    await expect(remove).toBeVisible();
    await expectNoGlobalOverflow(page);

    const [mediaBox, quantityBox, updateBox, removeBox] = await Promise.all([
        media.boundingBox(), quantity.boundingBox(), update.boundingBox(), remove.boundingBox(),
    ]);
    expect(mediaBox).not.toBeNull();
    expect(quantityBox).not.toBeNull();
    expect(updateBox).not.toBeNull();
    expect(removeBox).not.toBeNull();
    expect(overlapArea(mediaBox!, quantityBox!)).toBe(0);
    expect(overlapArea(mediaBox!, updateBox!)).toBe(0);
    expect(overlapArea(mediaBox!, removeBox!)).toBe(0);

    await quantity.fill('2');
    await update.click();
    await expect(cartLine(page, wheelSku).getByTestId('cart-line-quantity')).toHaveValue('2');

    await cartLine(page, wheelSku).getByTestId('cart-line-remove').click();
    await expect(cartLine(page, wheelSku)).toHaveCount(0);
    await expect(page.getByTestId('cart-empty')).toBeVisible();
});
