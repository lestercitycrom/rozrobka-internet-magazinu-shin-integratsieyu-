import { expect, test } from '@playwright/test';

test('tire and wheel details expose the correct catalog section and normalized values', async ({ page }, testInfo) => {
    const russian = testInfo.project.name === 'buyer-ru-preview';
    const mobile = testInfo.project.name === 'buyer-mobile';
    const prefix = russian ? '/ru' : '';
    const navigation = () => page.getByTestId(mobile ? 'mobile-navigation' : 'desktop-navigation');

    await page.goto(`${prefix}/produkter/000205550016`);
    await expect(navigation()).toBeVisible();
    await expect(navigation().locator('a[aria-current="page"]', { hasText: russian ? 'Шины' : 'Dekk' })).toHaveCount(1);
    await expect(page.getByText('205/55 R16', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('205.00/55.00 R16.00');

    await page.goto(`${prefix}/produkter/DEMO-WHEEL-18112`);
    await expect(navigation()).toBeVisible();
    await expect(navigation().locator('a[aria-current="page"]', { hasText: russian ? 'Диски' : 'Felger' })).toHaveCount(1);
    await expect(page.getByText('8 × 18', { exact: true })).toBeVisible();
    await expect(page.getByText('5×112', { exact: true })).toBeVisible();
    await expect(page.getByText('66.6', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('8.00');
    await expect(page.locator('body')).not.toContainText('Silver');
});
