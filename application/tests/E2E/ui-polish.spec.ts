import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { expectNoGlobalOverflow, overlapArea } from './helpers';

const viewports = [320, 390, 440, 768, 1024, 1440, 1920].map((width) => ({ width, height: 900 }));
const routes = ['/', '/dekk-og-felger'];

async function visible(locator: Locator): Promise<Locator> {
    await expect(locator.first()).toBeVisible();
    return locator.first();
}

test.describe('UI polish: finder composition', () => {
    for (const route of routes) {
        test(`${route} keeps finder responsive without global overflow`, async ({ page }) => {
            for (const viewport of viewports) {
                await page.setViewportSize(viewport);
                await page.goto(route);
                await expectNoGlobalOverflow(page);
                await page.evaluate(() => document.fonts.ready);

                const finder = await visible(page.getByTestId('quick-finder'));
                const registration = await visible(finder.locator('.et-quick-finder__registration'));
                const input = await visible(registration.locator('input[name="registration"]'));
                const submit = await visible(registration.locator('button[type="submit"]'));
                const geometry = await page.evaluate(([finderNode, panelNode, inputNode, buttonNode]) => {
                    const rect = (node: Element) => node.getBoundingClientRect();
                    const finderRect = rect(finderNode);
                    const panelStyle = getComputedStyle(panelNode);
                    const inputRect = rect(inputNode);
                    const buttonRect = rect(buttonNode);
                    return {
                        finderWidth: finderRect.width,
                        panelContentWidth: panelNode.clientWidth - parseFloat(panelStyle.paddingLeft) - parseFloat(panelStyle.paddingRight),
                        input: inputRect.toJSON(), button: buttonRect.toJSON(),
                    };
                }, await Promise.all([finder.elementHandle(), finder.locator('.et-quick-finder__panel').first().elementHandle(), input.elementHandle(), submit.elementHandle()]));

                if (geometry.panelContentWidth < 400) {
                    expect(geometry.button.y).toBeGreaterThanOrEqual(geometry.input.y + geometry.input.height + 7);
                } else {
                    expect(Math.abs(geometry.button.y - geometry.input.y)).toBeLessThanOrEqual(1);
                    expect(geometry.input.width).toBeGreaterThanOrEqual(160);
                }

                const categories = finder.locator('.et-shop-categories a');
                await expect(categories).toHaveCount(4);
                const cards = await categories.evaluateAll((nodes) => nodes.map((node) => {
                    const rect = node.getBoundingClientRect();
                    return { left: Math.round(rect.left * 10) / 10, top: Math.round(rect.top * 10) / 10, width: rect.width };
                }));
                const finderContentWidth = await finder.evaluate((node) => {
                    const style = getComputedStyle(node);
                    return node.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                });
                const columns = new Set(cards.map((card) => card.left)).size;
                expect(columns).not.toBe(3);
                if (finderContentWidth < 14 * 16) expect(columns).toBe(1);
                else if (finderContentWidth < 720) expect(columns).toBe(2);
                else expect(columns).toBe(4);
            }
        });
    }
});

test('UI polish: finder container query boundaries use content width', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/dekk-og-felger');
    await page.evaluate(() => document.fonts.ready);
    const finder = page.getByTestId('quick-finder');
    const cards = finder.locator('.et-shop-categories a');
    const panel = finder.locator('.et-quick-finder__panel').first();
    const registration = finder.locator('.et-quick-finder__registration');
    const registrationInput = registration.locator('input[name="registration"]');
    const registrationSubmit = registration.locator('button[type="submit"]');
    try {
        for (const [width, expectedColumns] of [[399, 2], [400, 2], [719, 2], [720, 4]] as const) {
            await finder.evaluate((node, value) => {
                const panelNode = node.querySelector('.et-quick-finder__panel');
                if (!panelNode) throw new Error('Missing finder panel');
                const panelStyle = getComputedStyle(panelNode);
                const panelContentWidth = panelNode.clientWidth - parseFloat(panelStyle.paddingLeft) - parseFloat(panelStyle.paddingRight);
                const finderStyle = getComputedStyle(node);
                const finderContentWidth = node.clientWidth - parseFloat(finderStyle.paddingLeft) - parseFloat(finderStyle.paddingRight);
                const targetContentWidth = value < 401 ? panelContentWidth : finderContentWidth;
                const outerExtra = node.getBoundingClientRect().width - targetContentWidth;
                node.style.cssText += `;width:${value + outerExtra}px;min-width:0;max-width:none;flex:none`;
            }, width);
            const boundaryTarget = width < 401 ? panel : finder;
            await expect.poll(async () => boundaryTarget.evaluate((node) => {
                const style = getComputedStyle(node);
                return node.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            })).toBeGreaterThanOrEqual(width - 1);
            const actualContentWidth = await boundaryTarget.evaluate((node) => {
                const style = getComputedStyle(node);
                return node.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            });
            expect(actualContentWidth).toBeLessThanOrEqual(width + 1);
            if (width === 399 || width === 400) {
                const [inputBox, submitBox] = await Promise.all([registrationInput.boundingBox(), registrationSubmit.boundingBox()]);
                expect(inputBox).not.toBeNull(); expect(submitBox).not.toBeNull();
                if (width === 399) expect(submitBox!.y).toBeGreaterThanOrEqual(inputBox!.y + inputBox!.height + 7);
                else expect(Math.abs(submitBox!.y - inputBox!.y)).toBeLessThanOrEqual(1);
            }
            const columns = await cards.evaluateAll((nodes) => new Set(nodes.map((node) => Math.round(node.getBoundingClientRect().left * 10) / 10)).size);
            expect(columns).toBe(expectedColumns);
        }
    } finally {
        await finder.evaluate((node) => node.removeAttribute('style'));
        await page.reload();
    }
});

test('UI polish: cart badge presentation fixture preserves its local anchor', async ({ page }) => {
    await page.goto('/');
    const cart = page.getByTestId('header-cart');
    await expect(cart).toBeVisible();

    // Presentation-only DOM fixture. It deliberately does not test server/cart-store counts or updates.
    await page.evaluate(() => {
        const fixture = document.createElement('div');
        fixture.id = 'ui-polish-cart-fixture';
        fixture.style.cssText = 'position:fixed;left:8px;bottom:8px;z-index:9999;padding:8px;background:white';
        const clone = document.querySelector('[data-testid="header-cart"]')?.cloneNode(true) as HTMLAnchorElement | null;
        if (!clone) throw new Error('Missing real header cart for presentation fixture');
        clone.dataset.testid = 'ui-polish-fixture-cart';
        fixture.append(clone);
        document.body.append(fixture);
    });
    const fixtureCart = page.getByTestId('ui-polish-fixture-cart');
    const badge = fixtureCart.locator('.et-cart__count');
    const icon = fixtureCart.locator('svg');

    try {
        await page.evaluate(() => document.fonts.ready);
        let anchor: { top: number; right: number } | null = null;
        for (const count of [0, 1, 9, 10, 99, 100, 999]) {
            const display = count > 99 ? '99+' : String(count);
            await fixtureCart.evaluate((node, value) => {
                node.setAttribute('aria-label', `Handlekurv: ${value} varer`);
                const badgeNode = node.querySelector('.et-cart__count');
                if (badgeNode) badgeNode.textContent = value > 99 ? '99+' : String(value);
            }, count);
            await expect(badge).toHaveText(display);
            await expect(fixtureCart).toHaveAccessibleName(`Handlekurv: ${count} varer`);
            const [cartBox, badgeBox, iconBox] = await Promise.all([fixtureCart.boundingBox(), badge.boundingBox(), icon.boundingBox()]);
            expect(cartBox).not.toBeNull(); expect(badgeBox).not.toBeNull(); expect(iconBox).not.toBeNull();
            expect(cartBox!.width).toBe(48); expect(cartBox!.height).toBe(48);
            const current = { top: badgeBox!.y - cartBox!.y, right: (cartBox!.x + cartBox!.width) - (badgeBox!.x + badgeBox!.width) };
            expect(current.top).toBeGreaterThanOrEqual(1); expect(current.top).toBeLessThanOrEqual(3);
            expect(current.right).toBeGreaterThanOrEqual(1); expect(current.right).toBeLessThanOrEqual(3);
            if (anchor) { expect(Math.abs(current.top - anchor.top)).toBeLessThanOrEqual(1); expect(Math.abs(current.right - anchor.right)).toBeLessThanOrEqual(1); }
            anchor = current;
            expect(badgeBox!.x).toBeGreaterThanOrEqual(cartBox!.x); expect(badgeBox!.y).toBeGreaterThanOrEqual(cartBox!.y);
            expect(badgeBox!.x + badgeBox!.width).toBeLessThanOrEqual(cartBox!.x + cartBox!.width + 1);
            expect(badgeBox!.y + badgeBox!.height).toBeLessThanOrEqual(cartBox!.y + cartBox!.height + 1);
            expect(overlapArea(cartBox!, iconBox!)).toBeGreaterThan(0);
            expect(overlapArea(badgeBox!, iconBox!)).toBe(0);
        }
    } finally {
        await page.evaluate(() => document.querySelector('#ui-polish-cart-fixture')?.remove());
        await page.reload();
        await expect(cart).toBeVisible();
    }
});

test('UI polish: action link keeps its label and SVG in one focusable action', async ({ page }) => {
    await page.goto('/');
    const action = await visible(page.locator('.et-quick-finder .et-action-link'));
    const label = await visible(action.locator('.et-action-link__label'));
    const icon = await visible(action.locator('svg'));
    await expect(label.locator('xpath=..')).toHaveJSProperty('tagName', 'A');
    await expect(icon.locator('xpath=..')).toHaveJSProperty('tagName', 'A');
    await page.evaluate(() => document.fonts.ready);
    // Native focus may scroll the viewport. Compare document-space geometry,
    // so ordinary focus scrolling is not mistaken for a layout jump.
    const documentBox = () => action.evaluate(node => {
        const rect = node.getBoundingClientRect();
        return { x: rect.x + scrollX, y: rect.y + scrollY, width: rect.width, height: rect.height };
    });
    const before = await documentBox();
    await action.focus();
    await expect(action).toBeFocused();
    expect(await documentBox()).toEqual(before);
});

test('UI polish: long ActionLink remains readable at 320px and 200% text', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 844 });
    await page.goto('/');
    const source = await visible(page.locator('a.et-action-link'));
    try {
        await page.evaluate((sourceNode) => {
            const fixture = document.createElement('div');
            fixture.id = 'ui-polish-long-action-fixture';
            fixture.style.cssText = 'position:fixed;left:16px;top:96px;width:288px;z-index:9999;padding:0;background:#fff';
            const action = sourceNode.cloneNode(true) as HTMLAnchorElement;
            action.dataset.testid = 'ui-polish-long-action';
            action.style.cssText += ';font-size:32px;line-height:48px';
            const label = action.querySelector('.et-action-link__label');
            if (!label) throw new Error('ActionLink clone is missing its label');
            label.textContent = 'Les mer om dekkhotell og tilgjengelige tjenester';
            fixture.append(action);
            document.body.append(fixture);
        }, await source.elementHandle());
        const action = page.getByTestId('ui-polish-long-action');
        const label = action.locator('.et-action-link__label');
        const icon = action.locator('svg');
        await page.evaluate(() => document.fonts.ready);
        const [fixtureBox, actionBox, labelBox, iconBox] = await Promise.all([
            page.locator('#ui-polish-long-action-fixture').boundingBox(), action.boundingBox(), label.boundingBox(), icon.boundingBox(),
        ]);
        expect(fixtureBox).not.toBeNull(); expect(actionBox).not.toBeNull(); expect(labelBox).not.toBeNull(); expect(iconBox).not.toBeNull();
        expect(labelBox!.height).toBeGreaterThanOrEqual(96);
        expect(actionBox!.width).toBeLessThanOrEqual(fixtureBox!.width + 1);
        expect(iconBox!.x).toBeGreaterThanOrEqual(labelBox!.x);
        expect(iconBox!.y).toBeGreaterThanOrEqual(labelBox!.y);
        expect(iconBox!.y + iconBox!.height).toBeLessThanOrEqual(labelBox!.y + labelBox!.height);
        await action.focus();
        await expect(action).toBeFocused();
        await expectNoGlobalOverflow(page);
    } finally {
        await page.evaluate(() => document.querySelector('#ui-polish-long-action-fixture')?.remove());
        await page.reload();
    }
});

test('UI polish: tablet hero shade covers the complete hero and copy', async ({ page }) => {
    for (const viewport of [{ width: 844, height: 390 }, { width: 768, height: 1024 }]) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        const hero = await visible(page.locator('.et-v3-hero'));
        const shade = await visible(hero.locator('.et-v3-hero__shade'));
        const copy = await visible(hero.locator('.et-v3-hero__content'));
        const [heroBox, shadeBox, copyBox] = await Promise.all([hero.boundingBox(), shade.boundingBox(), copy.boundingBox()]);
        expect(heroBox).not.toBeNull(); expect(shadeBox).not.toBeNull(); expect(copyBox).not.toBeNull();
        expect(shadeBox!.height).toBeGreaterThanOrEqual(heroBox!.height - 1);
        expect(shadeBox!.y).toBeLessThanOrEqual(heroBox!.y + 1);
        expect(shadeBox!.y + shadeBox!.height).toBeGreaterThanOrEqual(heroBox!.y + heroBox!.height - 1);
        expect(shadeBox!.y).toBeLessThanOrEqual(copyBox!.y + 1);
        expect(shadeBox!.y + shadeBox!.height).toBeGreaterThanOrEqual(copyBox!.y + copyBox!.height - 1);
        if (viewport.width === 844) {
            const output = path.resolve('storage/app/qa-ui-polish/extended-local');
            await mkdir(output, { recursive: true });
            await page.screenshot({ path: path.join(output, 'home-844-fixed-viewport.png'), fullPage: false, animations: 'disabled' });
        }
    }
});
