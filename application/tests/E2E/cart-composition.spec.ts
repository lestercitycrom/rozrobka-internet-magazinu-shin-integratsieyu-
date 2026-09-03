import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

test('empty cart has one compact recovery action across the full viewport matrix', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1440, height: 900 },
        { width: 1366, height: 768 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/handlekurv');
        const empty = page.getByTestId('cart-empty');
        const actions = empty.getByRole('link', { name: 'Fortsett å handle' });
        await expect(empty).toBeVisible();
        await expect(actions).toHaveCount(1);
        await expect(page.locator('.et-cart-toolbar')).toHaveCount(0);
        await expect(empty).not.toHaveClass(/border-dashed/);

        const state = await empty.evaluate((element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return {
                width: rect.width,
                height: rect.height,
                borderTopStyle: style.borderTopStyle,
                borderLeftWidth: style.borderLeftWidth,
                display: style.display,
            };
        });
        expect(state.width).toBeGreaterThanOrEqual(Math.min(1280, viewport.width - (viewport.width <= 900 ? 40 : 140)));
        expect(state.height).toBeLessThanOrEqual(280);
        expect(state.borderTopStyle).toBe('solid');
        expect(state.borderLeftWidth).toBe('0px');
        expect(state.display).toBe('grid');
        expect(await actions.evaluate((element) => element.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
        await expectNoGlobalOverflow(page);
    }
});
