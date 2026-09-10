import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { expectNoGlobalOverflow, localePrefix } from './helpers';

const evidenceDirectory = path.resolve(process.cwd(), '..', 'evidence', 'issue-46');

test('offers and both independently managed service collections render in NB and RU', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(prefix || '/');

    const promotions = page.getByTestId('homepage-promotions');
    const additional = page.getByTestId('homepage-additional-services');
    const wheels = page.getByTestId('homepage-wheel-services');
    await expect(promotions).toBeVisible();
    await expect(promotions.getByTestId('homepage-promotion')).toHaveCount(4);
    await expect(additional.getByTestId('homepage-service-card')).toHaveCount(6);
    await expect(wheels.getByTestId('homepage-service-card')).toHaveCount(6);
    await expect(additional.locator('.et-v3-service--feature')).toHaveCount(2);
    await expect(wheels.locator('.et-v3-service--feature')).toHaveCount(2);
    for (const image of [
        promotions.getByTestId('homepage-promotion').first().locator('img'),
        additional.locator('.et-v3-service--feature img').first(),
        wheels.locator('.et-v3-service--feature img').first(),
    ]) {
        const source = await image.getAttribute('src');
        await expect.poll(
            async () => image.evaluate((element: HTMLImageElement) => element.naturalWidth),
            { message: `Expected homepage image to load: ${source}` },
        ).toBeGreaterThan(0);
    }

    const order = await page.evaluate(() => {
        const top = (selector: string) => document.querySelector<HTMLElement>(selector)?.offsetTop ?? Number.POSITIVE_INFINITY;
        return [
            top('[data-testid="home-dekkhotell"]'),
            top('[data-testid="homepage-promotions"]'),
            top('[data-testid="homepage-additional-services"]'),
            top('[data-testid="home-process"]'),
            top('[data-testid="home-prices"]'),
            top('[data-testid="homepage-wheel-services"]'),
        ];
    });
    expect(order).toEqual([...order].sort((a, b) => a - b));

    const firstOffer = promotions.getByTestId('homepage-promotion').first();
    const disclosure = firstOffer.locator('[data-promotion-disclosure]');
    await expect(firstOffer.locator('.et-v3-promotion__short')).toBeVisible();
    await expect(firstOffer.locator('.et-v3-promotion__cta')).toBeVisible();
    await disclosure.click();
    await expect(firstOffer.locator('.et-v3-promotion__details')).toBeVisible();
    await expectNoGlobalOverflow(page);

    if (process.env.CAPTURE_VISUALS === '1' && testInfo.project.name === 'buyer-ru-preview') {
        mkdirSync(evidenceDirectory, { recursive: true });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.addStyleTag({ content: '.skip-link, .et-topbar, [data-site-header] { visibility: hidden !important; }' });
        await page.getByTestId('homepage-promotions').screenshot({ path: path.join(evidenceDirectory, 'customer-home-1440-ru-promotions.png') });
        await page.getByTestId('homepage-additional-services').screenshot({ path: path.join(evidenceDirectory, 'customer-home-1440-ru-additional-services.png') });
        await page.getByTestId('homepage-wheel-services').screenshot({ path: path.join(evidenceDirectory, 'customer-home-1440-ru-wheel-services.png') });
    }
});

test('four offers use manual non-autoplay navigation and stay clamped after resize', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One browser is sufficient for the stateful resize contract');
    await page.goto('/');

    const carousel = page.locator('[data-promotion-carousel]');
    const track = carousel.locator('[data-promotion-track]');
    const next = page.locator('[data-promotion-next]');
    const previous = page.locator('[data-promotion-previous]');
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();
    const initialTransform = await track.evaluate((element) => getComputedStyle(element).transform);
    await page.waitForTimeout(600);
    expect(await track.evaluate((element) => getComputedStyle(element).transform)).toBe(initialTransform);

    await next.click();
    await expect(previous).toBeEnabled();
    await expect(next).toBeDisabled();
    await expect(carousel.getByTestId('homepage-promotion').nth(3)).toHaveAttribute('aria-hidden', 'false');

    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(async () => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('1');
    await next.click();
    await next.click();
    await expect(next).toBeDisabled();

    await page.setViewportSize({ width: 1440, height: 1000 });
    await expect.poll(async () => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('3');
    await expect(next).toBeDisabled();
    await expect(previous).toBeEnabled();
    await expectNoGlobalOverflow(page);
});

test('homepage collections retain a coherent responsive layout at required intermediate and mobile widths', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial multi-viewport contract avoids duplicate heavy browser work');

    for (const viewport of [
        { width: 1920, height: 1080 },
        { width: 1440, height: 1000 },
        { width: 1366, height: 900 },
        { width: 1280, height: 900 },
        { width: 1024, height: 900 },
        { width: 390, height: 844 },
        { width: 320, height: 844 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        await expect(page.getByTestId('homepage-additional-services')).toBeVisible();
        await expect(page.getByTestId('homepage-wheel-services')).toBeVisible();
        await expectNoGlobalOverflow(page);

        if (process.env.CAPTURE_VISUALS === '1') {
            mkdirSync(evidenceDirectory, { recursive: true });
            await page.addStyleTag({ content: '.skip-link, .et-topbar, [data-site-header] { visibility: hidden !important; }' });
            await page.getByTestId('homepage-promotions').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-promotions.png`) });
            await page.getByTestId('homepage-additional-services').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-additional-services.png`) });
            await page.getByTestId('homepage-wheel-services').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-wheel-services.png`) });
        }
    }
});
