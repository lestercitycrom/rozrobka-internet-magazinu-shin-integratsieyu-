import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { expectNoGlobalOverflow, localePrefix } from './helpers';

const evidenceDirectory = path.resolve(process.cwd(), '..', 'evidence', 'issue-46');

type BoundingBox = { x: number; y: number; width: number; height: number };

function expectOverlayInsideCard(cardBox: BoundingBox | null, overlayBox: BoundingBox | null): void {
    expect(cardBox).not.toBeNull();
    expect(overlayBox).not.toBeNull();
    if (!cardBox || !overlayBox) throw new Error('Expected the card and its overlay to have layout boxes.');

    const leftInset = overlayBox.x - cardBox.x;
    const topInset = overlayBox.y - cardBox.y;
    const rightInset = cardBox.x + cardBox.width - (overlayBox.x + overlayBox.width);
    const bottomInset = cardBox.y + cardBox.height - (overlayBox.y + overlayBox.height);
    const widthDifference = cardBox.width - overlayBox.width;
    const heightDifference = cardBox.height - overlayBox.height;

    for (const inset of [leftInset, topInset, rightInset, bottomInset]) {
        expect(inset).toBeGreaterThan(0);
        expect(inset).toBeLessThanOrEqual(2);
    }
    expect(widthDifference).toBeGreaterThan(0);
    expect(widthDifference).toBeLessThanOrEqual(2);
    expect(heightDifference).toBeGreaterThan(0);
    expect(heightDifference).toBeLessThanOrEqual(2);
}

async function expectContained(
    outer: import('@playwright/test').Locator,
    inner: import('@playwright/test').Locator,
): Promise<void> {
    const outerElement = await outer.elementHandle();
    if (!outerElement) throw new Error('Expected containment parent to exist.');
    const geometry = await inner.evaluate((innerElement, outerNode) => {
        const outerBox = (outerNode as Element).getBoundingClientRect();
        const innerBox = innerElement.getBoundingClientRect();

        return {
            horizontalEnd: innerBox.right - outerBox.right,
            verticalEnd: innerBox.bottom - outerBox.bottom,
        };
    }, outerElement);
    await outerElement.dispose();
    expect(geometry.horizontalEnd).toBeLessThanOrEqual(2);
    expect(geometry.verticalEnd).toBeLessThanOrEqual(2);
}

async function limitHomepageOffers(page: import('@playwright/test').Page, maximum: number): Promise<void> {
    await page.route((url) => ['/', '/ru'].includes(url.pathname), async (route) => {
        if (route.request().resourceType() !== 'document') {
            await route.continue();
            return;
        }
        const response = await route.fetch();
        let seen = 0;
        const body = (await response.text()).replace(
            /<article\b[^>]*data-testid="homepage-promotion"[\s\S]*?<\/article>/g,
            (card) => { seen += 1; return seen <= maximum ? card : ''; },
        );
        await route.fulfill({ response, body });
    });
}

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
        promotions.getByTestId('homepage-promotion').first().locator('[data-media-fallback-image]'),
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
            top('[data-testid="home-prices"]'),
            top('[data-testid="homepage-promotions"]'),
            top('[data-testid="homepage-additional-services"]'),
            top('[data-testid="home-process"]'),
            top('[data-testid="homepage-wheel-services"]'),
        ];
    });
    expect(order).toEqual([...order].sort((a, b) => a - b));

    const firstOffer = promotions.getByTestId('homepage-promotion').first();
    const disclosure = firstOffer.locator('[data-promotion-card-overlay-open]');
    const detailsDialog = firstOffer.locator('[data-promotion-card-overlay]');
    const cardDocumentBox = () => firstOffer.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return { x: rect.x + scrollX, y: rect.y + scrollY, width: rect.width, height: rect.height };
    });
    const initialCardBox = await cardDocumentBox();
    await expect(firstOffer.locator('.et-v3-promotion__short')).toBeVisible();
    await expect(firstOffer.locator('.et-v3-promotion__cta')).toBeVisible();
    await expect(detailsDialog).not.toHaveAttribute('open', '');
    await expect(disclosure).toHaveAttribute('aria-expanded', 'false');
    await disclosure.click();
    await expect(detailsDialog).toHaveAttribute('open', '');
    await expect(detailsDialog).toBeVisible();
    await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
    const [cardBox, overlayBox] = await Promise.all([firstOffer.boundingBox(), detailsDialog.boundingBox()]);
    expectOverlayInsideCard(cardBox, overlayBox);
    expect(await cardDocumentBox()).toEqual(initialCardBox);
    await page.keyboard.press('Escape');
    await expect(detailsDialog).not.toHaveAttribute('open', '');
    await expect(disclosure).toHaveAttribute('aria-expanded', 'false');
    await expect(disclosure).toBeFocused();
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

test('exactly two offers remain reachable in the one-slot mobile carousel in NB and RU', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic DOM cardinality probe covers the CSS cascade');
    await limitHomepageOffers(page, 2);

    for (const route of ['/', '/ru']) {
        for (const viewport of [{ width: 390, height: 844 }, { width: 320, height: 844 }]) {
            await page.setViewportSize(viewport);
            await page.goto(route);
            const carousel = page.locator('[data-promotion-carousel]');
            const track = carousel.locator('[data-promotion-track]');
            const cards = track.getByTestId('homepage-promotion');
            const controls = page.locator('[data-promotion-controls]');
            const previous = page.locator('[data-promotion-previous]');
            const next = page.locator('[data-promotion-next]');
            const status = carousel.locator('[data-promotion-status]');
            await expect(cards).toHaveCount(2);
            await expect(carousel).toHaveClass(/is-enhanced/);
            await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('1');
            await expect(controls).toBeVisible();
            await expect(status).toBeVisible();
            await expect(previous).toBeDisabled();
            await expect(next).toBeEnabled();

            for (const [index, advance] of [[0, false], [1, true]] as const) {
                if (advance) await next.click();
                const card = cards.nth(index);
                await expect(card).toHaveAttribute('aria-hidden', 'false');
                await expectContained(card, card.locator('.et-v3-promotion__cta'));
                const dimensions = await card.evaluate((element) => ({
                    height: element.getBoundingClientRect().height,
                    clientHeight: element.clientHeight,
                    scrollHeight: element.scrollHeight,
                }));
                expect(dimensions.height).toBeGreaterThan(0);
                expect(dimensions.scrollHeight).toBeLessThanOrEqual(dimensions.clientHeight + 1);
            }
            await expect(cards.nth(0)).toHaveAttribute('aria-hidden', 'true');
            await expect(previous).toBeEnabled();
            await expect(next).toBeDisabled();
            await expectNoGlobalOverflow(page);
        }
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
        await page.evaluate(() => document.fonts.ready);
        await expect(page.getByTestId('homepage-additional-services')).toBeVisible();
        await expect(page.getByTestId('homepage-wheel-services')).toBeVisible();
        await expectNoGlobalOverflow(page);

        if (process.env.CAPTURE_VISUALS === '1' && [1440, 390, 320].includes(viewport.width)) {
            mkdirSync(evidenceDirectory, { recursive: true });
            await page.addStyleTag({ content: '.skip-link, .et-topbar, [data-site-header] { visibility: hidden !important; }' });
            await page.getByTestId('homepage-promotions').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-promotions.png`) });
            await page.getByTestId('homepage-additional-services').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-additional-services.png`) });
            await page.getByTestId('homepage-wheel-services').screenshot({ path: path.join(evidenceDirectory, `customer-home-${viewport.width}-nb-wheel-services.png`) });
        }
    }
});
