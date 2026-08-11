import { expect, test } from '@playwright/test';
import { isMobileProject, isRussianProject } from './helpers';

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
    await expect(page.locator('.et-product-dimension')).toHaveText('8 × 18');
    await expect(page.getByText('5×112', { exact: true })).toBeVisible();
    await expect(page.getByText('66.6', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('8.00');
    await expect(page.locator('body')).not.toContainText('Silver');
});

test('a failed product image is replaced by the honest catalog fallback', async ({ page }, testInfo) => {
    const prefix = isRussianProject(testInfo.project.name) ? '/ru' : '';

    await page.route('**/storage/media/e2e-demo-product.png', (route) => route.abort());
    await page.goto(`${prefix}/produkter/000205550016`);

    await expect(page.getByTestId('product-primary-media')).toBeHidden();
    await expect(page.getByTestId('product-visual-fallback')).toBeVisible();
    await expect(page.locator('[data-product-media-note]')).toContainText(/Produktbilde mangler|Фото товара отсутствует/);
});
