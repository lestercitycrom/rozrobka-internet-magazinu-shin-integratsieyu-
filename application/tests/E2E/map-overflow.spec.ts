import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, localePrefix } from './helpers';

const exactMapUrl = 'https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic';

test('homepage map state is honest in NB and RU', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(prefix || '/');

    const card = page.getByTestId('map-card');
    await expect(card).toBeVisible();
    const iframe = page.getByTestId('map-iframe');
    const externalOnly = page.getByTestId('map-external-only');

    if (await iframe.count()) {
        await expect(iframe).toBeVisible();
        await expect(card.locator(`a[href="${exactMapUrl}"]`)).toBeVisible();
    } else {
        await expect(externalOnly).toBeVisible();
        await expect(externalOnly).toHaveAttribute('href', exactMapUrl);
        await expect(card.locator('iframe')).toHaveCount(0);
    }

    await expectNoGlobalOverflow(page);
});

for (const route of ['/', '/dekk-og-felger', '/dekk', '/felger', '/tjenester', '/dekkskift-tromso', '/priser', '/tjenester/mobilt-dekkskift/foresporsel', '/handlekurv']) {
    test(`storefront route ${route} has no global horizontal overflow`, async ({ page }, testInfo) => {
        const prefix = localePrefix(testInfo.project.name);
        await page.goto(`${prefix}${route === '/' ? '' : route}` || '/');
        await expectNoGlobalOverflow(page);
    });
}
