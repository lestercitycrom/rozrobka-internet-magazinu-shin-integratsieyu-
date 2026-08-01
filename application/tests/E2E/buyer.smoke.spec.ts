import { expect, test } from '@playwright/test';

test('buyer sees the safe vehicle lookup fallback and manual selection link', async ({ page }) => {
    await page.goto('/biloppslag');
    await page.getByLabel('Registreringsnummer').fill('AB12345');
    await page.getByRole('button', { name: 'Søk' }).click();
    await expect(page.getByRole('link', { name: 'Velg dekk manuelt' })).toHaveAttribute('href', /\/dekk$/);
});

test('buyer can open a service request with the selected service context', async ({ page }) => {
    await page.goto('/tjenester/mobilt-dekkskift');

    const requestLink = page.locator('a[href$="/tjenester/mobilt-dekkskift/foresporsel"]');
    await expect(requestLink).toBeVisible();
    await requestLink.click();
    await expect(page).toHaveURL(/\/tjenester\/mobilt-dekkskift\/foresporsel$/);
});

test('buyer can autocomplete and filter wheels, then update and remove a cart line', async ({ page }) => {
    await page.goto('/felger');
    const wheelFilters = page.locator('[data-mobile-filter]');
    await expect(wheelFilters).toHaveAttribute('data-filter-ready', 'true');
    const search = page.locator('input[data-catalog-autocomplete]');
    if (!(await search.isVisible())) {
        await wheelFilters.locator('summary').click();
    }
    const suggestionsResponse = page.waitForResponse((response) => response.url().includes('/felger/suggestions?q=Demo') && response.ok());
    await search.fill('Demo');
    await suggestionsResponse;
    await expect(page.locator('#catalog-suggestions option')).not.toHaveCount(0);

    await page.goto('/felger?diameter=18&bolt_holes=5&pcd=112');

    const wheel = page.getByTestId('catalog-product').filter({ hasText: 'Demo 8x18 5x112' });
    await expect(wheel).toBeVisible();
    await wheel.locator('form[action*="/handlekurv"] button').click();
    await page.goto('/handlekurv');

    const cartLine = page.locator('article').filter({ hasText: 'Demo 8x18 5x112' });
    const quantity = cartLine.locator('input[name="quantity"]:not([type="hidden"])');
    await expect(quantity).toHaveAttribute('max', '4');

    const service = page.getByTestId('service-addon').filter({ hasText: 'E2E felgservice' });
    await expect(service).toContainText('99,00 NOK');
    await service.getByTestId('service-addon-toggle').click();
    await expect(service).toContainText('Valgt');

    await quantity.fill('2');
    await cartLine.locator('form:has(input[name="_method"][value="PATCH"]) button').click();
    await expect(quantity).toHaveValue('2');
    await expect(service).toContainText('Antall: 2');
    await expect(service).toContainText('198,00 NOK');

    await page.locator('a[href$="/kasse"]').click();
    await expect(page.getByTestId('checkout-service-item')).toContainText('E2E felgservice');
    await page.goto('/handlekurv');
    await service.getByTestId('service-addon-toggle').click();
    await expect(service).not.toContainText('Valgt');

    await cartLine.locator('form:has(input[name="_method"][value="DELETE"]) button').click();
    await expect(page.locator('article').filter({ hasText: 'Demo 8x18 5x112' })).toHaveCount(0);
    await expect(page.locator('a[href$="/kasse"]')).toHaveCount(0);
});

test('buyer can browse the Norwegian storefront and open a catalog product', async ({ page }, testInfo) => {
    test.slow();
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

    const catalogueCard = page.getByTestId('catalog-foundation').getByRole('link').first();
    await expect(catalogueCard).toHaveCSS('transform', 'none');
    if (testInfo.project.name === 'buyer-desktop') {
        await catalogueCard.scrollIntoViewIfNeeded();
        const cardBoundsBeforeHover = await catalogueCard.boundingBox();
        await catalogueCard.hover();
        await expect(catalogueCard).toHaveCSS('transform', 'none');
        expect(await catalogueCard.boundingBox()).toEqual(cardBoundsBeforeHover);
        await catalogueCard.focus();
        await expect(catalogueCard).toHaveCSS('outline-style', 'solid');
    }

    await page.getByRole('link', { name: 'Se dekk' }).first().click();
    await expect(page).toHaveURL(/\/dekk$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Dekk' })).toBeVisible();
    const filters = page.locator('[data-mobile-filter]');
    const filtersForm = page.getByTestId('catalog-filters');
    const openMobileFilters = async () => {
        await expect(filters).toHaveAttribute('data-filter-ready', 'true');
        if (testInfo.project.name === 'buyer-mobile' && !(await filtersForm.isVisible())) {
            await filters.locator('summary').click();
        }
        await expect(filtersForm).toBeVisible();
    };
    await openMobileFilters();
    const suggestionsResponse = page.waitForResponse((response) => response.url().includes('/dekk/suggestions?q=Demo') && response.ok());
    await page.getByLabel('Søk').fill('Demo');
    await suggestionsResponse;
    await expect(page.locator('#catalog-suggestions option')).not.toHaveCount(0);
    await page.getByLabel('Bredde').selectOption('205');
    await page.getByLabel('Profil').selectOption('55');
    await page.getByLabel('Kun på lager').check();
    await page.getByRole('button', { name: 'Vis produkter' }).click();
    await expect(page).toHaveURL(/width=205.*aspect=55.*in_stock=1/);
    await expect(page.getByTestId('active-filters')).toBeVisible();
    await page.getByRole('link', { name: 'Nullstill alle' }).click();
    await expect(page).toHaveURL(/\/dekk$/);
    await expect(page.getByLabel('Kun på lager')).not.toBeChecked();
    await openMobileFilters();
    await page.getByLabel('Bredde').selectOption('205');
    await page.getByLabel('Profil').selectOption('55');
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
        await page.getByRole('checkbox', { name: 'Vilkår', exact: true }).check();
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
