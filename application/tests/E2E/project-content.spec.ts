import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isRussianProject, localePrefix } from './helpers';

test('project content, current prices and business proof render in both QA locales', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = isRussianProject(testInfo.project.name);

    await page.goto(prefix || '/');
    await expect(page.getByTestId('homepage-promotions')).toBeVisible();
    await expect(page.getByTestId('homepage-reviews')).toBeVisible();
    await expect(page.getByTestId('business-contact-card')).toContainText(russian ? '300 автомобилей' : '300 biler');
    await expectNoGlobalOverflow(page);

    await page.goto(`${prefix}/priser`);
    await expect(page.getByTestId('public-price-list')).toBeVisible();
    await expect(page.getByTestId('public-price-row')).toHaveCount(11);
    await expect(page.getByTestId('public-price-list')).toContainText(russian ? 'Замена колёс в мастерской' : 'Dekkskift på verksted');
    await expectNoGlobalOverflow(page);
});

test('specialized service forms expose and retain the requested fields', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);

    await page.goto(`${prefix}/tjenester/omlegging-balansering/foresporsel`);
    const mounting = page.getByTestId('generic-service-request');
    await expect(mounting.locator('[name="service_variant"]')).toBeVisible();
    await expect(mounting.locator('[name="wheel_count"]')).toBeVisible();
    await mounting.locator('[name="service_variant"]').selectOption('balancing_only');
    await mounting.locator('[name="wheel_count"]').selectOption('2');
    await expect(mounting.locator('[name="service_variant"]')).toHaveValue('balancing_only');
    await expect(mounting.locator('[name="wheel_count"]')).toHaveValue('2');

    await page.goto(`${prefix}/tjenester/oljeskift/foresporsel`);
    const oil = page.getByTestId('generic-service-request');
    await expect(oil.locator('[name="additional_services[]"]')).toHaveCount(3);
    await oil.locator('[name="additional_services[]"][value="air_filter"]').check();
    await expect(oil.locator('[name="additional_services[]"][value="air_filter"]')).toBeChecked();
    await expectNoGlobalOverflow(page);
});

test('legacy shop route resolves to the populated store landing in both QA locales', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/shop`);
    await expect(page).toHaveURL(new RegExp(`${prefix.replace('/', '\\/')}\\/dekk-og-felger$`));

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByLabel(/registreringsnummer|регистрационн/i)).toBeVisible();
    await expect(page.locator('a[href$="/dekk"]')).not.toHaveCount(0);
    await expect(page.locator('a[href$="/felger"]')).not.toHaveCount(0);
    await expectNoGlobalOverflow(page);
});

test('company, contact and legal pages are published and localized', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);

    for (const slug of ['om-oss', 'kontakt', 'personvern', 'kjopsvilkar']) {
        const response = await page.goto(`${prefix}/${slug}`);
        expect(response?.status(), `${slug} must be covered by the real E2E seed`).toBe(200);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expectNoGlobalOverflow(page);
    }

    await page.goto(`${prefix}/kontakt`);
    await expect(page.getByTestId('map-iframe')).toBeVisible();
    await expect(page.getByTestId('map-iframe')).not.toHaveAttribute('loading', 'lazy');
});
