import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isRussianProject, localePrefix } from './helpers';

test('approved homepage structure, current prices and compact business contact render in both QA locales', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = isRussianProject(testInfo.project.name);

    await page.goto(prefix || '/');
    await expect(page.getByTestId('homepage-promotions')).toBeVisible();
    await expect(page.getByTestId('homepage-reviews')).toBeVisible();
    await expect(page.getByTestId('business-contact-card')).toBeVisible();
    await expect(page.getByTestId('home-dekkhotell')).toBeVisible();
    await expect(page.getByTestId('home-process')).toBeVisible();
    await expect(page.locator('.et-v3-hero__primary-action')).toHaveCount(1);
    await expect(page.locator('.et-v3-trust, .et-v3-story')).toHaveCount(0);

    const dekkhotellBox = await page.getByTestId('home-dekkhotell').boundingBox();
    const processBox = await page.getByTestId('home-process').boundingBox();
    const pricesBox = await page.getByTestId('home-prices').boundingBox();
    expect(dekkhotellBox?.y).toBeLessThan(pricesBox?.y ?? 0);
    expect(pricesBox?.y).toBeLessThan(processBox?.y ?? 0);
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
    await expect(page.getByTestId('quick-finder')).toBeVisible();
    await expect(page.getByTestId('quick-finder').locator('input[name="registration"]')).toBeVisible();
    await expect(page.getByTestId('quick-finder').getByRole('tab')).toHaveCount(2);
    await expect(page.locator('a[href$="/dekk"]')).not.toHaveCount(0);
    await expect(page.locator('a[href$="/felger"]')).not.toHaveCount(0);
    await expectNoGlobalOverflow(page);
});

test('company, contact and legal pages are published and localized', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.route(/^https:\/\/www\.google\.com\/maps(?:\?|$)/, (route) => route.fulfill({
        contentType: 'text/html; charset=utf-8',
        body: '<!doctype html><html><body>Google Maps test fixture</body></html>',
    }));

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
