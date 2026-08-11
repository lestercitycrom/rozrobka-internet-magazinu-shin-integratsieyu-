import { expect, test } from '@playwright/test';

async function navigationFor(page: import('@playwright/test').Page, mobile: boolean) {
    if (mobile) {
        await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();
        const navigation = page.getByTestId('mobile-navigation');
        await expect(navigation).toBeVisible();
        return navigation;
    }

    const navigation = page.getByTestId('desktop-navigation');
    await expect(navigation).toBeVisible();
    return navigation;
}

test('storefront header exposes the current section and accessible targets', async ({ page }, testInfo) => {
    const russian = testInfo.project.name === 'buyer-ru-preview';
    const mobile = testInfo.project.name === 'buyer-mobile';
    await page.goto(russian ? '/ru/felger' : '/felger');

    const navigation = await navigationFor(page, mobile);
    const activeLink = navigation.locator('a[aria-current="page"]', { hasText: russian ? 'Диски' : 'Felger' });
    await expect(activeLink).toHaveCount(1);

    const targetHeights = await page.locator('header a:visible, header button:visible').evaluateAll((targets) => targets.map((target) => target.getBoundingClientRect().height));
    expect(targetHeights.length).toBeGreaterThan(0);
    expect(targetHeights.every((height) => height >= 44)).toBeTruthy();

    if (mobile) {
        const headerBox = await page.getByRole('banner').boundingBox();
        expect(headerBox).not.toBeNull();
        expect(headerBox!.height).toBeLessThanOrEqual(420);
    } else {
        await expect(page.getByTestId('mobile-navigation')).toBeHidden();
    }
});
