import { expect, test } from '@playwright/test';

async function expectStoreSection(page: import('@playwright/test').Page, mobile: boolean, label: string) {
    if (mobile) {
        await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();
    }
    const navigation = page.getByTestId(mobile ? 'mobile-navigation' : 'desktop-navigation');
    await expect(navigation).toBeVisible();
    await expect(navigation.locator('a[aria-current="page"]', { hasText: label })).toHaveCount(1);
}

test('tire and wheel details expose the store section and normalized values', async ({ page }, testInfo) => {
    const russian = testInfo.project.name === 'buyer-ru-preview';
    const mobile = testInfo.project.name === 'buyer-mobile';
    const prefix = russian ? '/ru' : '';

    await page.goto(`${prefix}/produkter/000205550016`);
    await expectStoreSection(page, mobile, russian ? 'Шины' : 'Dekk');
    await expect(page.getByText('205/55 R16', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('205.00/55.00 R16.00');

    await page.goto(`${prefix}/produkter/DEMO-WHEEL-18112`);
    await expectStoreSection(page, mobile, russian ? 'Диски' : 'Felger');
    await expect(page.getByText('8 × 18', { exact: true })).toBeVisible();
    await expect(page.getByText('5×112', { exact: true })).toBeVisible();
    await expect(page.getByText('66.6', { exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('8.00');
    await expect(page.locator('body')).not.toContainText('Silver');
});
