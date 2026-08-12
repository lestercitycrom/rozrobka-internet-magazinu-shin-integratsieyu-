import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, localePrefix } from './helpers';

const exactMapUrl = 'https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic';

test('homepage map state is honest in NB and RU', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    let embeddedMapRequests = 0;
    await page.route(/^https:\/\/www\.google\.com\/maps(?:\?|$)/, async (route) => {
        embeddedMapRequests += 1;
        await route.fulfill({
            contentType: 'text/html; charset=utf-8',
            body: '<!doctype html><html><body>Google Maps test fixture</body></html>',
        });
    });
    await page.goto(prefix || '/');

    const card = page.getByTestId('map-card');
    await expect(card).toBeVisible();
    const iframe = page.getByTestId('map-iframe');
    await expect(iframe).toBeVisible();
    await expect(iframe).not.toHaveAttribute('loading', 'lazy');
    await expect(iframe).toHaveAttribute('src', /google\.com\/maps\?.*output=embed/);
    await expect.poll(() => embeddedMapRequests, { message: 'the embedded map must be requested without scrolling' }).toBeGreaterThan(0);
    const iframeHandle = await iframe.elementHandle();
    const mapFrame = iframeHandle ? await iframeHandle.contentFrame() : null;
    expect(mapFrame, 'the embedded map must expose its requested document').not.toBeNull();
    await expect(mapFrame!.locator('body')).toContainText('Google Maps test fixture');
    await expect(card.locator(`a[href="${exactMapUrl}"]`)).toBeVisible();

    await expectNoGlobalOverflow(page);
});

for (const route of ['/', '/dekk-og-felger', '/dekk', '/felger', '/tjenester', '/dekkskift-tromso', '/priser', '/tjenester/mobilt-dekkskift/foresporsel', '/handlekurv']) {
    test(`storefront route ${route} has no global horizontal overflow`, async ({ page }, testInfo) => {
        const prefix = localePrefix(testInfo.project.name);
        await page.goto(`${prefix}${route === '/' ? '' : route}` || '/');
        await expectNoGlobalOverflow(page);
    });
}
