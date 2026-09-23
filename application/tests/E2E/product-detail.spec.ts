import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isMobileProject, isRussianProject, overlapArea } from './helpers';

async function expectStoreSection(page: import('@playwright/test').Page, mobile: boolean, label: string) {
    if (mobile) {
        await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();
    }
    const navigation = page.getByTestId(mobile ? 'mobile-navigation' : 'desktop-navigation');
    await expect(navigation).toBeVisible();
    await expect(navigation.locator('a[aria-current="page"]', { hasText: label })).toHaveCount(1);
}

test('tire and wheel details expose the store section and normalized values', async ({ page }, testInfo) => {
    const russian = isRussianProject(testInfo.project.name);
    const mobile = isMobileProject(testInfo.project.name);
    const prefix = russian ? '/ru' : '';

    await page.goto(`${prefix}/produkter/000205550016`);
    await expectStoreSection(page, mobile, russian ? 'Шины' : 'Dekk');
    await expect(page.locator('.et-product-dimension')).toHaveText('205/55 R16');
    await expect(page.locator('body')).not.toContainText('205.00/55.00 R16.00');

    await page.goto(`${prefix}/produkter/DEMO-WHEEL-18112`);
    await expectStoreSection(page, mobile, russian ? 'Диски' : 'Felger');
    const wheelFallback = page.getByTestId('product-visual-fallback');
    await expect(wheelFallback).toBeVisible();
    await expect(wheelFallback).toHaveAttribute('data-product-fallback-kind', 'wheel');
    await expect(wheelFallback.getByTestId('product-fallback-blueprint')).toBeVisible();
    await expect(wheelFallback.locator('.et-fallback-meta__status')).toHaveText(russian ? 'Без фото товара' : 'Uten produktfoto');
    await expect(wheelFallback.locator('.et-fallback-meta__context')).toHaveCSS('display', 'block');
    await expect(page.locator('.et-product-dimension')).toHaveText('8 × 18');
    await expect(page.getByText('5×112', { exact: true })).toBeVisible();
    await expect(page.getByText('66.6', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('8.00');
    await expect(page.locator('body')).not.toContainText('Silver');
    await expectNoGlobalOverflow(page);
});

test('a failed product image is replaced by the honest catalog fallback', async ({ page }, testInfo) => {
    const prefix = isRussianProject(testInfo.project.name) ? '/ru' : '';

    await page.route('**/storage/media/e2e-demo-product.png', (route) => route.abort());
    await page.goto(`${prefix}/produkter/000205550016`);

    await expect(page.getByTestId('product-primary-media')).toBeHidden();
    const fallback = page.getByTestId('product-visual-fallback');
    await expect(fallback).toBeVisible();
    await expect(fallback).toHaveAttribute('data-product-fallback-kind', 'tire');
    await expect(fallback.getByTestId('product-fallback-blueprint')).toBeVisible();
    await expect(fallback.locator('.et-fallback-meta__status')).toHaveText(/Uten produktfoto|Без фото товара/);
    await expect(page.locator('[data-product-media-note]')).toContainText(/Produktbilde mangler|Фото товара отсутствует/);
    await expectNoGlobalOverflow(page);
});

test('product media and honest fallback remain composed across the required viewport matrix', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');

    for (const viewport of [
        { width: 1440, height: 900 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/produkter/000205550016');
        await expect(page.getByTestId('product-primary-media')).toBeVisible();
        await expect(page.getByTestId('product-cart-form')).toBeVisible();
        await expectNoGlobalOverflow(page);

        await page.goto('/produkter/DEMO-WHEEL-18112');
        await expect(page.getByTestId('product-visual-fallback')).toBeVisible();
        await expect(page.getByTestId('product-fallback-blueprint')).toBeVisible();
        await expectNoGlobalOverflow(page);
    }
});

test('sandbox quantity, explanation and purchase actions use separate readable regions', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');

    for (const viewport of [
        { width: 1440, height: 900 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/produkter/NDI-TEST-4765205551649102');

        const quantity = page.getByTestId('product-quantity');
        const note = page.getByTestId('integration-test-quantity-note');
        const actions = page.getByTestId('product-purchase-actions');
        const add = page.getByTestId('product-add-to-cart');
        const buy = page.getByTestId('product-buy-now');
        await expect(quantity).toHaveValue('2');
        await expect(quantity).toHaveAttribute('readonly', '');
        await expect(quantity).toHaveAttribute('min', '2');
        await expect(quantity).toHaveAttribute('max', '2');
        await expect(note).toBeVisible();
        await expect(add).toBeVisible();
        await expect(buy).toBeVisible();

        const [quantityBox, noteBox, actionsBox, addBox, buyBox] = await Promise.all([
            quantity.boundingBox(), note.boundingBox(), actions.boundingBox(), add.boundingBox(), buy.boundingBox(),
        ]);
        expect(quantityBox).not.toBeNull();
        expect(noteBox).not.toBeNull();
        expect(actionsBox).not.toBeNull();
        expect(addBox).not.toBeNull();
        expect(buyBox).not.toBeNull();
        expect(quantityBox!.width).toBeGreaterThanOrEqual(96);
        // At 320px: DS-1 outer gutters (32) + panel padding/border (50).
        expect(noteBox!.width).toBeGreaterThanOrEqual(Math.min(240, viewport.width - 82));
        expect(addBox!.width).toBeGreaterThanOrEqual(viewport.width <= 390 ? 150 : 130);
        expect(buyBox!.width).toBeGreaterThanOrEqual(viewport.width <= 390 ? 150 : 130);
        expect(overlapArea(quantityBox!, noteBox!)).toBe(0);
        expect(overlapArea(actionsBox!, noteBox!)).toBe(0);
        expect(overlapArea(addBox!, buyBox!)).toBe(0);

        const noteTypography = await note.locator('small').evaluate((element) => {
            const style = getComputedStyle(element);
            return {
                lineHeight: Number.parseFloat(style.lineHeight),
                height: element.getBoundingClientRect().height,
            };
        });
        expect(noteTypography.height).toBeLessThanOrEqual(noteTypography.lineHeight * 4.2);
        await expectNoGlobalOverflow(page);
    }

    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto('/ru/produkter/NDI-TEST-4765205551649102');
    const russianNote = page.getByTestId('integration-test-quantity-note');
    await expect(russianNote).toContainText('без реальной поставки');
    const russianNoteTypography = await russianNote.locator('small').evaluate((element) => {
        const style = getComputedStyle(element);
        const bounds = element.getBoundingClientRect();
        const container = element.closest('[data-testid="integration-test-quantity-note"]')!.getBoundingClientRect();
        const range = document.createRange();
        range.selectNodeContents(element);
        return {
            fontSize: Number.parseFloat(style.fontSize),
            lineCount: new Set(Array.from(range.getClientRects(), rect => Math.round(rect.top))).size,
            contained: bounds.left >= container.left - 1 && bounds.right <= container.right + 1
                && bounds.top >= container.top - 1 && bounds.bottom <= container.bottom + 1,
        };
    });
    // An inline small uses its parent's line boxes, which can be taller than
    // its computed line-height. Count rendered lines, not height / line-height.
    expect(russianNoteTypography.fontSize).toBeGreaterThanOrEqual(14);
    expect(russianNoteTypography.contained).toBe(true);
    expect(russianNoteTypography.lineCount).toBeLessThanOrEqual(4);
    await expectNoGlobalOverflow(page);

    await page.goto('/produkter/000205550016');
    await expect(page.getByTestId('integration-test-quantity-note')).toHaveCount(0);
});
