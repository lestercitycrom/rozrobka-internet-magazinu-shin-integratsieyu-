import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isMobileProject } from './helpers';

async function tabTo(page: import('@playwright/test').Page, target: import('@playwright/test').Locator, maximum = 120) {
    await expect(target).toBeVisible();
    for (let index = 0; index < maximum; index += 1) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((element) => element === document.activeElement)) return;
    }
    throw new Error(`Target was not reached by Tab traversal: ${await target.evaluate((element) => element.outerHTML)}`);
}

async function stableHover(page: import('@playwright/test').Page, target: import('@playwright/test').Locator, shadow = 'none') {
    await expect(target).toBeVisible();
    await page.mouse.move(0, 0);
    const before = await target.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
            width: rect.width,
            height: rect.height,
            borderTop: style.borderTopWidth,
            transform: style.transform,
        };
    });
    await target.hover();
    const after = await target.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
            width: rect.width,
            height: rect.height,
            borderTop: style.borderTopWidth,
            transform: style.transform,
            boxShadow: style.boxShadow,
        };
    });
    expect(after.width).toBeCloseTo(before.width, 2);
    expect(after.height).toBeCloseTo(before.height, 2);
    expect(after.borderTop).toBe(before.borderTop);
    expect(after.transform).toBe(before.transform);
    if (shadow === 'none') expect(after.boxShadow).toBe('none');
}

async function stableActive(page: import('@playwright/test').Page, target: import('@playwright/test').Locator) {
    await target.hover();
    const before = await target.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return { width: rect.width, height: rect.height };
    });
    await page.mouse.down();
    const active = await target.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return { width: rect.width, height: rect.height, transform: style.transform, shadow: style.boxShadow };
    });
    await page.mouse.move(0, 0);
    await page.mouse.up();
    expect(active.width).toBeCloseTo(before.width, 2);
    expect(active.height).toBeCloseTo(before.height, 2);
    expect(active.transform).toBe('none');
    expect(active.shadow).toBe('none');
}

async function expectDisabledState(target: import('@playwright/test').Locator) {
    await target.evaluate((element) => element.setAttribute('aria-disabled', 'true'));
    const state = await target.evaluate((element) => {
        const style = getComputedStyle(element);
        return { opacity: Number(style.opacity), cursor: style.cursor, shadow: style.boxShadow };
    });
    expect(state.opacity).toBeLessThan(.6);
    expect(state.cursor).toBe('not-allowed');
    expect(state.shadow).toBe('none');
    await target.evaluate((element) => element.removeAttribute('aria-disabled'));
}

test('semantic hover variants remain flat and dimensionally stable', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One desktop state contract is sufficient');

    await page.goto('/');
    await stableHover(page, page.getByTestId('header-call'));
    await stableActive(page, page.getByTestId('header-call'));
    await expectDisabledState(page.getByTestId('header-call'));
    await stableHover(page, page.locator('.et-action-link').first());
    await stableActive(page, page.locator('.et-action-link').first());
    await stableHover(page, page.getByTestId('footer-booking'));
    await stableActive(page, page.getByTestId('footer-booking'));
    await expectDisabledState(page.getByTestId('footer-booking'));

    const promotion = page.getByTestId('homepage-promotion').first();
    await stableHover(page, promotion, 'card');

    await page.goto('/handlekurv');
    const outline = page.locator('.et-btn--outline').first();
    if (await outline.count()) {
        await stableHover(page, outline);
        await stableActive(page, outline);
        await expectDisabledState(outline);
    }

    await page.goto('/dekk');
    const productCard = page.getByTestId('catalog-product').first();
    await stableHover(page, productCard, 'card');
    await stableHover(page, productCard.locator('.et-product-card__buy, .et-product-card__more').first());

    const activeCategory = page.locator('.et-catalog-category.is-active').first();
    const inactiveCategory = page.locator('.et-catalog-category:not(.is-active)').first();
    if (await activeCategory.count() && await inactiveCategory.count()) {
        const selectedBackground = await activeCategory.evaluate((element) => getComputedStyle(element).backgroundColor);
        await inactiveCategory.hover();
        const hoverBackground = await inactiveCategory.evaluate((element) => getComputedStyle(element).backgroundColor);
        expect(selectedBackground).not.toBe(hoverBackground);
        expect(await activeCategory.evaluate((element) => getComputedStyle(element).backgroundColor)).toBe(selectedBackground);
    }
});

test('real keyboard traversal uses one contextual focus outline and preserves submenu order', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One real keyboard traversal is sufficient');

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const headerCall = page.getByTestId('header-call');
    await tabTo(page, headerCall);
    const lightFocus = await headerCall.evaluate((element) => {
        const style = getComputedStyle(element);
        return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow };
    });
    expect(lightFocus.width).toBe('3px');
    expect(lightFocus.color).toBe('rgb(159, 0, 0)');
    expect(lightFocus.shadow).toBe('none');

    await page.goto('/');
    const serviceParent = page.locator('.et-nav__item').first().locator('.et-nav__trigger > a');
    await tabTo(page, serviceParent);
    const serviceToggle = page.locator('.et-nav__item').first().locator('[data-mega-menu-toggle]');
    const firstSubmenuLink = page.locator('.et-nav__item').first().locator('.et-nav__submenu a').first();
    await expect(firstSubmenuLink).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(serviceToggle).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(serviceToggle).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Tab');
    await expect(firstSubmenuLink).toBeFocused();
    await expect(firstSubmenuLink).toBeVisible();

    await page.goto('/');
    const footerBooking = page.getByTestId('footer-booking');
    await tabTo(page, footerBooking);
    const darkFocus = await footerBooking.evaluate((element) => {
        const style = getComputedStyle(element);
        return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow };
    });
    expect(darkFocus.width).toBe('3px');
    expect(darkFocus.color).toBe('rgb(255, 255, 255)');
    expect(darkFocus.shadow).toBe('none');

    if (process.env.CAPTURE_ISSUE_29 === '1') {
        await footerBooking.scrollIntoViewIfNeeded();
        await expect(footerBooking).toBeFocused();
        await expect(footerBooking).toBeInViewport();
        const box = await footerBooking.boundingBox();
        if (!box) throw new Error('Focused footer action has no screenshot geometry');
        const padding = 16;
        await page.screenshot({
            path: '../evidence/issue-29/focus-footer-1440.png',
            clip: {
                x: Math.max(0, box.x - padding),
                y: Math.max(0, box.y - padding),
                width: Math.min(1440 - Math.max(0, box.x - padding), box.width + padding * 2),
                height: Math.min(900 - Math.max(0, box.y - padding), box.height + padding * 2),
            },
        });
    }
});

test('mobile menu keeps keyboard focus handoff and order', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo.project.name), 'Mobile keyboard contract');

    await page.goto('/');
    const menuButton = page.locator('[data-menu-toggle]');
    await tabTo(page, menuButton, 20);
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('mobile-navigation')).toBeVisible();
    await expect(page.getByTestId('mobile-navigation').locator('summary').first()).toBeFocused();
    await expectNoGlobalOverflow(page);
});

test('sticky header is opaque and stable across the required viewport matrix', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(120_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1440, height: 900 },
        { width: 1366, height: 768 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        const header = page.locator('[data-site-header]');
        const before = await header.evaluate((element) => {
            const style = getComputedStyle(element);
            return { height: element.getBoundingClientRect().height, background: style.backgroundColor, backdrop: style.backdropFilter };
        });
        expect(before.background).toBe('rgb(255, 255, 255)');
        expect(before.backdrop).toBe('none');

        await page.evaluate(() => window.scrollTo(0, 640));
        await expect(header).toHaveClass(/is-scrolled/);
        const after = await header.evaluate((element) => {
            const style = getComputedStyle(element);
            return { height: element.getBoundingClientRect().height, background: style.backgroundColor, backdrop: style.backdropFilter };
        });
        expect(after.background).toBe('rgb(255, 255, 255)');
        expect(after.backdrop).toBe('none');
        expect(after.height).toBeCloseTo(before.height, 2);
        await expectNoGlobalOverflow(page);

        if (process.env.CAPTURE_ISSUE_29 === '1' && [1440, 390].includes(viewport.width)) {
            await page.screenshot({ path: `../evidence/issue-29/header-scrolled-${viewport.width}.png` });
        }
    }
});

test('mobile standalone actions and compact footer keep every destination reachable', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo.project.name), 'Mobile shell contract');

    for (const route of ['/', '/handlekurv']) {
        await page.goto(route);
        const footer = page.locator('.et-footer');
        const toggles = footer.locator('[data-footer-toggle]');
        await expect(toggles).toHaveCount(3);
        await expect(toggles.first()).toBeVisible();

        const collapsedHeight = await footer.evaluate((element) => element.getBoundingClientRect().height);
        // Compact means the navigation groups really collapse, not that company
        // copy and the R3 stacked, touch-sized contacts fit an arbitrary fraction
        // of a short phone viewport. Contact readability has its own R3 coverage.

        for (const toggle of await toggles.all()) {
            const toggleHeight = await toggle.evaluate((element) => element.getBoundingClientRect().height);
            expect(toggleHeight).toBeGreaterThanOrEqual(44);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            const linksId = await toggle.getAttribute('aria-controls');
            const links = page.locator(`#${linksId} a`);
            await expect(links.first()).toBeHidden();
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(links.first()).toBeVisible();
            await expect.poll(() => footer.evaluate(element => element.getBoundingClientRect().height)).toBeGreaterThan(collapsedHeight);
            const heights = await links.evaluateAll((items) => items.map((item) => item.getBoundingClientRect().height));
            expect(Math.min(...heights)).toBeGreaterThanOrEqual(44);
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(links.first()).toBeHidden();
            await expect.poll(() => footer.evaluate(element => element.getBoundingClientRect().height)).toBeCloseTo(collapsedHeight, 1);
        }
        await expectNoGlobalOverflow(page);
    }

    await page.goto('/');
    for (const selector of ['.et-home-prices__all', '.et-action-link']) {
        const action = page.locator(selector).first();
        await expect(action).toBeVisible();
        expect(await action.evaluate((element) => element.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
    }

    await page.goto('/dekk');
    const breadcrumb = page.locator('.et-breadcrumbs a').first();
    if (await breadcrumb.count()) {
        expect(await breadcrumb.evaluate((element) => element.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
    }

    if (process.env.CAPTURE_ISSUE_29 === '1' && page.viewportSize()?.width === 390) {
        await page.goto('/handlekurv');
        await page.locator('.et-footer').scrollIntoViewIfNeeded();
        await page.screenshot({ path: '../evidence/issue-29/footer-collapsed-390.png' });
    }
});

test('footer remains complete without JavaScript', async ({ browser }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One no-JavaScript proof is sufficient');
    const page = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    await page.goto('/handlekurv');
    const links = page.locator('.et-footer li > a');
    expect(await links.count()).toBeGreaterThan(8);
    for (const link of await links.all()) await expect(link).toBeVisible();
    await expectNoGlobalOverflow(page);
    await page.close();
});
