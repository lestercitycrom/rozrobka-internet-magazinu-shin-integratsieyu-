import { expect, test } from '@playwright/test';

test('storefront header exposes the current section and 44px targets', async ({ page }, testInfo) => {
    const russian = testInfo.project.name === 'buyer-ru-preview';
    await page.goto(russian ? '/ru/felger' : '/felger');

    const expectedLabel = russian ? 'Диски' : 'Felger';
    const navigation = testInfo.project.name === 'buyer-mobile'
        ? page.getByTestId('mobile-navigation')
        : page.getByTestId('desktop-navigation');
    const activeLink = navigation.locator('a[aria-current="page"]', { hasText: expectedLabel });

    await expect(navigation).toBeVisible();
    await expect(activeLink).toHaveCount(1);
    await expect(activeLink).toHaveCSS('border-bottom-color', 'rgb(201, 0, 0)');

    const targetHeights = await page.locator('header a:visible').evaluateAll((links) => links.map((link) => link.getBoundingClientRect().height));
    expect(targetHeights.length).toBeGreaterThan(0);
    expect(targetHeights.every((height) => height >= 44)).toBeTruthy();

    if (testInfo.project.name === 'buyer-mobile') {
        const headerBox = await page.getByRole('banner').boundingBox();
        expect(headerBox).not.toBeNull();
        expect(headerBox!.height).toBeLessThanOrEqual(110);
    }
});
