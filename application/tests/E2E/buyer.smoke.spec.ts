import { expect, test } from '@playwright/test';
import { addCatalogProduct, cartLine, ensureCatalogFiltersVisible, expectNoGlobalOverflow, isMobileProject, productCard } from './helpers';

const tireSku = '000205550016';

test('buyer sees the safe vehicle lookup fallback and manual selection link', async ({ page }) => {
    await page.goto('/biloppslag');
    await page.getByLabel('Registreringsnummer').fill('AB12345');
    await page.getByRole('button', { name: 'Søk', exact: true }).click();
    await expect(page.getByRole('link', { name: 'Velg dekk manuelt', exact: true })).toHaveAttribute('href', /\/dekk$/);
});

test('seasonal service hero exposes both requested fulfilment paths', async ({ page }) => {
    await page.goto('/tjenester/mobilt-dekkskift');

    const mobileLink = page.getByTestId('service-hero-mobile');
    const workshopLink = page.getByTestId('service-hero-workshop');
    await expect(mobileLink).toHaveAttribute('href', /\/tjenester\/mobilt-dekkskift\/foresporsel\?fulfilment=mobile$/);
    await expect(workshopLink).toHaveAttribute('href', /\/tjenester\/mobilt-dekkskift\/foresporsel\?fulfilment=workshop$/);

    await mobileLink.click();
    await expect(page).toHaveURL(/\/tjenester\/mobilt-dekkskift\/foresporsel\?fulfilment=mobile$/);
    await expect(page.getByTestId('seasonal-service-request')).toBeVisible();
    await expect(page.getByTestId('seasonal-step-location').locator('input[name="fulfilment"][value="mobile"]')).toBeChecked();
});

test('Norwegian storefront, catalog and product use stable scoped targets', async ({ page }, testInfo) => {
    await page.goto('/');
    await expect(page).toHaveTitle('EasyTire | Dekk og felg i Tromsø');
    await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
    await expect(page.getByRole('heading', { level: 1, name: 'Vi skifter hjul der bilen står.' })).toBeVisible();
    await expect(page.getByTestId('services-section')).toBeVisible();
    const catalogFoundation = page.getByTestId('catalog-foundation');
    await expect(catalogFoundation.getByRole('heading', { level: 2, name: 'Dekk og felg som passer hverdagen din' })).toBeVisible();
    await expect(catalogFoundation.getByRole('tab', { name: 'Manuell størrelse', exact: true })).toBeVisible();
    await expectNoGlobalOverflow(page);

    await catalogFoundation.getByRole('tab', { name: 'Manuell størrelse', exact: true }).click();
    const tireFoundationLink = catalogFoundation.locator('[role="tabpanel"] a[href$="/dekk"]');
    await expect(tireFoundationLink).toHaveCount(1);
    await expect(tireFoundationLink).toBeVisible();
    await expect(tireFoundationLink).toHaveCSS('transform', 'none');
    if (!isMobileProject(testInfo.project.name)) {
        const before = await tireFoundationLink.boundingBox();
        await tireFoundationLink.hover();
        await expect(tireFoundationLink).toHaveCSS('transform', 'none');
        expect(await tireFoundationLink.boundingBox()).toEqual(before);
    }

    await page.goto('/dekk');
    const form = await ensureCatalogFiltersVisible(page);
    await form.locator('select[name="width"]').selectOption('205');
    if (isMobileProject(testInfo.project.name)) {
        await form.getByTestId('catalog-filter-apply').click();
        await expect(page).toHaveURL(/width=205/);
        await expect(page.getByTestId('catalog-filter-drawer')).not.toHaveAttribute('open', '');
    } else {
        await expect(page).toHaveURL(/width=205/);
    }
    const card = productCard(page, tireSku);
    await expect(card).toBeVisible();
    await card.getByTestId('catalog-product-link').click();
    await expect(page).toHaveURL(/\/produkter\/000205550016$/);
    await expect(page.getByTestId('product-add-to-cart')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tekniske spesifikasjoner', exact: true })).toBeVisible();
    await expectNoGlobalOverflow(page);
});

test('buyer can add a tire, update an add-on and continue to checkout', async ({ page }) => {
    await addCatalogProduct(page, '/dekk?width=205&aspect=55', tireSku);
    await page.goto('/handlekurv');

    const line = cartLine(page, tireSku);
    await expect(line).toBeVisible();
    const quantity = line.getByTestId('cart-line-quantity');
    await expect(quantity).toHaveAttribute('min', '1');
    expect(Number(await quantity.getAttribute('max'))).toBeGreaterThanOrEqual(2);

    const service = page.getByTestId('service-addon').filter({ hasText: 'E2E dekkservice' });
    if (await service.count()) {
        await service.getByTestId('service-addon-toggle').click();
        await expect(service).toContainText('Valgt');
    }

    await quantity.fill('2');
    await line.getByTestId('cart-line-update').click();
    await expect(cartLine(page, tireSku).getByTestId('cart-line-quantity')).toHaveValue('2');
    await page.getByTestId('cart-checkout').click();
    await expect(page).toHaveURL(/\/kasse$/);
    await expect(page.getByTestId('checkout-form')).toBeVisible();
});
