import { expect, test } from '@playwright/test';

test('buyer can open the Norwegian storefront foundation on desktop and mobile', async ({ page }, testInfo) => {
    const response = await page.goto('/');

    expect(response).not.toBeNull();
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle('EasyTire | Dekk og felg i Tromsø');
    await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
    await expect(page.getByRole('heading', { level: 1, name: 'Dekk og felg – på din måte' })).toBeVisible();
    await expect(page.getByTestId('catalog-foundation')).toContainText('Finn riktig utgangspunkt for bilen din');
    await expect(page.getByRole('button', { name: 'Søk kommer snart' })).toBeDisabled();
    await expect(page.getByTestId('services-section')).toContainText('Mobilt dekkskift');

    if (testInfo.project.name === 'buyer-mobile') {
        await expect(page.getByTestId('mobile-navigation')).toBeVisible();
        await expect(page.getByTestId('desktop-navigation')).toBeHidden();
    } else {
        await expect(page.getByTestId('desktop-navigation')).toBeVisible();
        await expect(page.getByTestId('mobile-navigation')).toBeHidden();
    }
});
