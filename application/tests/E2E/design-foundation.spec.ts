import { expect, test, type Locator, type Page } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

type Box = { x: number; y: number; width: number; height: number };

async function typography(locator: Locator) {
    await expect(locator).toBeVisible();

    return locator.evaluate((element) => {
        const style = getComputedStyle(element);
        return {
            family: style.fontFamily,
            size: Number.parseFloat(style.fontSize),
            weight: style.fontWeight,
            lineHeight: Number.parseFloat(style.lineHeight),
        };
    });
}

async function box(locator: Locator): Promise<Box> {
    await expect(locator).toBeVisible();
    const bounds = await locator.boundingBox();
    expect(bounds).not.toBeNull();
    return bounds!;
}

async function expectFunctionalType(page: Page, route: string, viewport: { width: number; height: number }, expectedH1: number, expectedH3: number) {
    await page.setViewportSize(viewport);
    await page.goto(route);
    const h1 = await typography(page.locator('main h1').first());
    const h3 = await typography(page.locator('main .et-h3').first());

    expect(h1.family).toMatch(/^Barlow\b/i);
    expect(h1.size).toBeCloseTo(expectedH1, 1);
    expect(h1.weight).toBe('700');
    expect(h3.family).toMatch(/^Barlow\b/i);
    expect(h3.size).toBeCloseTo(expectedH3, 1);
    expect(h3.weight).toBe('700');
    await expectNoGlobalOverflow(page);
}

test('DS-1 typography and responsive home composition stay on the approved scale', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic multi-viewport DS-1 pass is sufficient');

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const desktopHeroType = await typography(page.locator('.et-v3-display'));
    expect(desktopHeroType.family).toMatch(/^Barlow\b/i);
    expect(desktopHeroType.size).toBeCloseTo(64, 1);
    expect(desktopHeroType.weight).toBe('700');

    const desktopContent = await box(page.locator('.et-v3-hero__content'));
    const desktopFinder = await box(page.getByTestId('quick-finder'));
    expect(desktopFinder.x).toBeGreaterThanOrEqual(desktopContent.x + desktopContent.width);
    expect(desktopFinder.width).toBeGreaterThanOrEqual(360);
    expect(desktopFinder.width).toBeLessThanOrEqual(400);

    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/');
    const firstScreenAction = await box(page.locator('.et-v3-hero__primary-action'));
    const firstScreenFinder = await box(page.getByTestId('quick-finder'));
    expect(firstScreenAction.y + firstScreenAction.height).toBeLessThanOrEqual(800);
    expect(firstScreenFinder.y + firstScreenFinder.height).toBeLessThanOrEqual(800);

    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto('/');
    const tabletContent = await box(page.locator('.et-v3-hero__content'));
    const tabletFinder = await box(page.getByTestId('quick-finder'));
    expect(tabletFinder.y).toBeGreaterThanOrEqual(tabletContent.y + tabletContent.height);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const mobileHeroType = await typography(page.locator('.et-v3-display'));
    expect(mobileHeroType.size).toBeGreaterThanOrEqual(36);
    expect(mobileHeroType.size).toBeLessThanOrEqual(40);

    const mobilePhoto = await box(page.locator('.et-v3-hero > picture'));
    const mobileContent = await box(page.locator('.et-v3-hero__content'));
    const mobileAction = await box(page.locator('.et-v3-hero__primary-action'));
    const mobileFinder = await box(page.getByTestId('quick-finder'));
    expect(mobilePhoto.height).toBeGreaterThanOrEqual(180);
    expect(mobilePhoto.height).toBeLessThanOrEqual(220);
    expect(mobileContent.y).toBeGreaterThanOrEqual(mobilePhoto.y + mobilePhoto.height);
    expect(mobileAction.y).toBeLessThan(mobileFinder.y);
    expect(mobileFinder.y).toBeGreaterThanOrEqual(mobileContent.y + mobileContent.height);
    await expectNoGlobalOverflow(page);
});

test('functional reference pages keep DS-1 headings and the store header label stays stable', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic multi-route DS-1 pass is sufficient');

    await expectFunctionalType(page, '/tjenester/dekkverksted', { width: 1440, height: 900 }, 40, 24);
    await expectFunctionalType(page, '/tjenester/dekkverksted', { width: 390, height: 844 }, 32, 20);

    for (const route of ['/dekk', '/felger']) {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(route);
        const storeLink = page.getByTestId('desktop-navigation').getByRole('link', { name: 'Dekk og felger', exact: true });
        await expect(storeLink).toBeVisible();
        await expect(storeLink).toHaveAttribute('href', /\/dekk-og-felger$/);
        const h1 = await typography(page.locator('main h1').first());
        expect(h1.size).toBeCloseTo(40, 1);
        expect(h1.weight).toBe('700');
    }
});

test('mobile seasonal stepper exposes one named current step and advances it', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic mobile-sized interaction pass is sufficient');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/tjenester/mobilt-dekkskift/foresporsel');

    const stepper = page.getByTestId('seasonal-stepper');
    const current = stepper.locator('[data-booking-step][aria-current="step"]');
    await expect(current).toHaveCount(1);
    await expect(current).toContainText(/Steg 1 av 5/);
    await expect(current.locator('.et-stepper__label')).toBeVisible();

    await page.getByTestId('seasonal-step-location').locator('[data-booking-next]').click();
    await expect(current).toHaveCount(1);
    await expect(current).toContainText(/Steg 2 av 5/);
    await expect(page.getByTestId('seasonal-step-vehicle-storage')).toBeVisible();
});
