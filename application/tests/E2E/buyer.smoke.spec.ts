import { expect, test } from '@playwright/test';

test('buyer sees the safe vehicle lookup fallback and manual selection link', async ({ page }) => {
    await page.goto('/biloppslag');
    await page.getByLabel('Registreringsnummer').fill('AB12345');
    await page.getByRole('button', { name: 'Søk' }).click();
    await expect(page.getByRole('link', { name: 'Velg dekk manuelt' })).toHaveAttribute('href', /\/dekk$/);
});

test('buyer can browse the Norwegian storefront and open a catalog product', async ({ page }, testInfo) => {
    const response = await page.goto('/');

    expect(response).not.toBeNull();
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle('EasyTire | Dekk og felg i Tromsø');
    await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/site.webmanifest');
    await expect(page.locator('link[rel="icon"][type="image/svg+xml"]')).toHaveAttribute('href', '/favicon.svg');
    await expect(page.getByRole('link', { name: 'EasyTire, forsiden' }).locator('img')).toHaveAttribute('src', '/brand/easytire-mark.svg');
    await expect(page.getByRole('heading', { level: 1, name: 'Dekk og felg som passer hverdagen din' })).toBeVisible();
    await expect(page.getByRole('banner')).toBeVisible();
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('contentinfo')).toBeVisible();
    await page.getByRole('link', { name: 'EasyTire, forsiden' }).focus();
    await expect(page.getByRole('link', { name: 'EasyTire, forsiden' })).toBeFocused();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await expect(page.getByRole('img', { name: 'EasyTire-montør skifter hjul på en bil med Tromsø i bakgrunnen' })).toBeVisible();
    await expect(page.getByTestId('catalog-foundation')).toContainText('Velg kategori og filtrer deg frem');
    await expect(page.getByTestId('services-section')).toContainText('Mobilt dekkskift');

    await page.getByRole('link', { name: 'Se dekk' }).first().click();
    await expect(page).toHaveURL(/\/dekk$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Dekk' })).toBeVisible();
    await page.getByLabel('Bredde').fill('205');
    await page.getByLabel('Profil').fill('55');
    await page.getByRole('button', { name: 'Vis produkter' }).click();
    await expect(page).toHaveURL(/width=205.*aspect=55/);
    await expect(page.getByTestId('catalog-product')).toContainText('Demo 205/55 R16');
    await page.getByRole('link', { name: 'Se produkt' }).click();
    await expect(page.getByRole('button', { name: 'Legg i handlekurv' })).toBeVisible();
    await page.getByRole('button', { name: 'Legg i handlekurv' }).click();
    await page.getByRole('link', { name: /Handlekurv/ }).click();
    await expect(page.getByRole('heading', { name: 'Handlekurv' })).toBeVisible();
    await expect(page.getByText('Demo 205/55 R16')).toBeVisible();
    await expect(page.getByLabel('Antall')).toHaveValue('1');
    await expect(page.getByRole('link', { name: 'Til kassen' })).toBeVisible();
    await page.getByRole('link', { name: 'Til kassen' }).click();
    await expect(page.getByRole('heading', { name: 'Kasse' })).toBeVisible();
    await expect(page.getByLabel('Navn')).toBeVisible();

    if (testInfo.project.name === 'buyer-desktop') {
        await page.getByLabel('Navn').fill('E2E Buyer');
        await page.getByLabel('E-post').fill('buyer@example.test');
        await page.getByLabel('Telefon').fill('+4799999999');
        await page.getByLabel('Samtykke').check();
        await page.getByLabel('Vilkår').check();
        await page.getByRole('button', { name: 'Send bestilling' }).click();
        await expect(page).toHaveURL(/\/bestilling\/ET-/);
        await expect(page.getByRole('heading', { name: 'Bestilling mottatt' })).toBeVisible();
        await page.getByRole('link', { name: /Handlekurv/ }).click();
        await expect(page.getByText('Handlekurven er tom.')).toBeVisible();
    }

    if (testInfo.project.name === 'buyer-mobile') {
        await expect(page.getByTestId('mobile-navigation')).toBeVisible();
        await expect(page.getByTestId('desktop-navigation')).toBeHidden();
    } else {
        await expect(page.getByTestId('desktop-navigation')).toBeVisible();
        await expect(page.getByTestId('mobile-navigation')).toBeHidden();
    }

    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
});
