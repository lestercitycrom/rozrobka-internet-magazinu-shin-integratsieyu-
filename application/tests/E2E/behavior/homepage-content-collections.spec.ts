import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { expectNoGlobalOverflow } from '../helpers';

const qaDirectory = path.resolve(process.cwd(), 'storage/app/qa-design-v2/home-content');

async function routeHomepageMarkup(page: import('@playwright/test').Page, transform: (html: string) => string): Promise<void> {
    await page.route('**/', async (route) => {
        if (route.request().resourceType() !== 'document') {
            await route.continue();
            return;
        }
        const response = await route.fetch();
        await route.fulfill({ response, body: transform(await response.text()) });
    });
}

test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic browser pass is sufficient');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
});

test('promotion carousel uses 3/2/1 manual navigation and horizontal-only swipe', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    const carousel = page.locator('[data-promotion-carousel]');
    const track = carousel.locator('[data-promotion-track]');
    const cards = track.getByTestId('homepage-promotion');
    const controls = page.locator('[data-promotion-controls]');
    const previous = page.locator('[data-promotion-previous]');
    const next = page.locator('[data-promotion-next]');
    await expect(cards).toHaveCount(4);
    await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('3');
    await expect(controls).toBeVisible();
    await expect(cards.nth(0)).toHaveAttribute('aria-hidden', 'false');
    await expect(cards.nth(1)).toHaveAttribute('aria-hidden', 'false');
    await expect(cards.nth(2)).toHaveAttribute('aria-hidden', 'false');
    await expect(cards.nth(3)).toHaveAttribute('aria-hidden', 'true');

    const initialTransform = await track.evaluate((element) => getComputedStyle(element).transform);
    await page.waitForTimeout(400);
    expect(await track.evaluate((element) => getComputedStyle(element).transform)).toBe(initialTransform);
    await next.click();
    await expect(previous).toBeEnabled();
    await expect(next).toBeDisabled();

    await page.setViewportSize({ width: 900, height: 900 });
    await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('2');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('1');
    await page.reload();

    const dispatchSwipe = (deltaX: number, deltaY: number) => track.evaluate((element, delta) => {
        const dispatch = (type: string, x: number, y: number) => {
            const event = new Event(type, { bubbles: true, cancelable: true });
            Object.defineProperty(event, type === 'touchstart' ? 'touches' : 'changedTouches', {
                value: [{ clientX: x, clientY: y }],
            });
            element.dispatchEvent(event);
        };
        dispatch('touchstart', 300, 300);
        dispatch('touchend', 300 + delta.x, 300 + delta.y);
    }, { x: deltaX, y: deltaY });

    const beforeVertical = await track.evaluate((element) => getComputedStyle(element).transform);
    await dispatchSwipe(8, -100);
    expect(await track.evaluate((element) => getComputedStyle(element).transform)).toBe(beforeVertical);
    await dispatchSwipe(-120, 8);
    await expect.poll(() => track.evaluate((element) => getComputedStyle(element).transform)).not.toBe(beforeVertical);
    await expectNoGlobalOverflow(page);
});

test('promotion image failure hides broken media and leaves decorative fallback stable', async ({ page }) => {
    await page.goto('/');
    const card = page.getByTestId('homepage-promotion').first();
    const image = card.locator('[data-media-fallback-image]');
    const fallback = card.locator('.et-v3-promotion__fallback');
    await expect(image).toBeVisible();
    await image.evaluate((element: HTMLImageElement) => { element.src = '/__qa_missing_promotion_image__.webp'; });
    await expect(image).toBeHidden();
    await expect(card).toHaveClass(/has-media-error/);
    await expect(fallback).toBeVisible();
    await expect(fallback).toHaveAttribute('aria-hidden', 'true');
    expect(await card.evaluate((element) => element.innerText)).not.toContain('EasyTire utfører arbeid ved bilen');
    mkdirSync(qaDirectory, { recursive: true });
    await card.screenshot({ path: path.join(qaDirectory, 'promotion-image-fallback-1440x900.png'), animations: 'disabled' });
});

test('two desktop promotions use two effective slots and enable one-slot mobile navigation', async ({ page }) => {
    await routeHomepageMarkup(page, (source) => {
        const marker = '<article class="et-v3-promotion"';
        const starts: number[] = [];
        let cursor = 0;
        while ((cursor = source.indexOf(marker, cursor)) !== -1) {
            starts.push(cursor);
            cursor += marker.length;
        }
        let html = source;
        for (const start of starts.slice(2).reverse()) {
            const end = html.indexOf('</article>', start);
            if (end !== -1) html = html.slice(0, start) + html.slice(end + '</article>'.length);
        }
        return html.replace('data-total="4"', 'data-total="2"');
    });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const carousel = page.locator('[data-promotion-carousel]');
    const cards = carousel.getByTestId('homepage-promotion');
    await expect(cards).toHaveCount(2);
    await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('2');
    await expect(page.locator('[data-promotion-controls]')).toBeHidden();
    await expect(cards.nth(0)).toHaveAttribute('aria-hidden', 'false');
    await expect(cards.nth(1)).toHaveAttribute('aria-hidden', 'false');

    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('1');
    await expect(page.locator('[data-promotion-controls]')).toBeVisible();
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        await expect.poll(() => carousel.evaluate((element) => element.style.getPropertyValue('--cards-visible'))).toBe('1');
        const containment = await cards.first().evaluate((card) => {
            const cardBox = card.getBoundingClientRect();
            const ctaBox = card.querySelector('.et-v3-promotion__cta')?.getBoundingClientRect();
            return {
                cardLeft: cardBox.left,
                cardRight: cardBox.right,
                viewportWidth: document.documentElement.clientWidth,
                ctaContained: Boolean(ctaBox && ctaBox.left >= cardBox.left - 1 && ctaBox.right <= cardBox.right + 1),
            };
        });
        expect(containment.cardLeft).toBeGreaterThanOrEqual(0);
        expect(containment.cardRight).toBeLessThanOrEqual(containment.viewportWidth);
        expect(containment.ctaContained).toBe(true);
        await expectNoGlobalOverflow(page);
    }
});

test('no-JS mobile promotion fallback keeps every offer and full terms reachable without overflow', async ({ browser }) => {
    for (const width of [390, 320]) {
        const context = await browser.newContext({
            baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
            javaScriptEnabled: false,
            viewport: { width, height: 844 },
        });
        await context.route(/^https:\/\/www\.google\.com\/maps(?:\?|$)/, (route) => route.fulfill({
            contentType: 'text/html; charset=utf-8',
            body: '<!doctype html><html><body>Google Maps test fixture</body></html>',
        }));
        const page = await context.newPage();
        await page.goto('/');
        const section = page.getByTestId('homepage-promotions');
        const track = section.locator('[data-promotion-track]');
        const cards = track.getByTestId('homepage-promotion');
        await expect(cards).toHaveCount(4);
        await expect(section.locator('[data-promotion-carousel]')).not.toHaveClass(/is-enhanced/);
        await expect(section.locator('[data-promotion-controls]')).toBeHidden();
        const disclosures = cards.locator('[data-promotion-disclosure]');
        await expect(disclosures).toHaveCount(4);
        expect(await disclosures.evaluateAll((elements) => elements.every((element) => element.hidden))).toBe(true);

        const geometry = await cards.evaluateAll((elements) => elements.map((element) => {
            const bounds = element.getBoundingClientRect();
            return { left: bounds.left, right: bounds.right, top: bounds.top, bottom: bounds.bottom };
        }));
        for (const [index, bounds] of geometry.entries()) {
            expect(bounds.left).toBeGreaterThanOrEqual(0);
            expect(bounds.right).toBeLessThanOrEqual(width);
            if (index > 0) expect(bounds.top).toBeGreaterThanOrEqual(geometry[index - 1].bottom);
        }

        const fallbackDetails = cards.locator('[data-promotion-native-details]');
        await expect(fallbackDetails).toHaveCount(4);
        for (let index = 0; index < 4; index += 1) {
            const details = fallbackDetails.nth(index);
            await expect(details.locator('summary')).toBeVisible();
            await details.locator('summary').click();
            await expect(details.locator('p')).toBeVisible();
            expect((await details.locator('p').innerText()).trim().length).toBeGreaterThan(20);
            await expect(cards.nth(index).locator('.et-v3-promotion__cta')).toBeVisible();
        }
        await expectNoGlobalOverflow(page);
        await context.close();
    }
});

test('native promotion terms survive without the dialog API and later initializers still run', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    await page.addInitScript(() => {
        Object.defineProperty(window, 'HTMLDialogElement', {
            configurable: true,
            value: undefined,
            writable: true,
        });
    });
    await page.route(/^https:\/\/www\.google\.com\/maps(?:\?|$)/, (route) => route.fulfill({
        contentType: 'text/html; charset=utf-8',
        body: '<!doctype html><html><body>Google Maps test fixture</body></html>',
    }));

    await page.goto('/');
    const cards = page.getByTestId('homepage-promotion');
    const nativeDetails = cards.locator('[data-promotion-native-details]');
    const disclosures = cards.locator('[data-promotion-disclosure]');
    await expect(cards).toHaveCount(4);
    await expect(nativeDetails).toHaveCount(4);
    expect(await disclosures.evaluateAll((elements) => elements.every((element) => element.hidden))).toBe(true);
    await expect(nativeDetails.first().locator('summary')).toBeVisible();
    await nativeDetails.first().locator('summary').click();
    await expect(nativeDetails.first().locator('p')).toBeVisible();
    expect((await nativeDetails.first().locator('p').innerText()).trim().length).toBeGreaterThan(20);

    const video = page.locator('[data-homepage-video-element]').first();
    await video.scrollIntoViewIfNeeded();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    expect(pageErrors).toEqual([]);
});

test('promotion preview floats outside the card and explicit dialog restores focus', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    const card = page.getByTestId('homepage-promotion').filter({ has: page.locator('[data-promotion-disclosure]') }).first();
    const opener = card.locator('[data-promotion-disclosure]');
    const dialogId = await opener.getAttribute('aria-controls');
    const dialog = page.locator(`#${dialogId}`);
    await expect(card.locator('[data-promotion-preview]')).toHaveAttribute('aria-hidden', 'true');
    await expect(opener).toBeVisible();

    expect(await page.evaluate(() => window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 768px)').matches)).toBe(true);
    await card.scrollIntoViewIfNeeded();
    await page.mouse.move(0, 0);
    await card.hover();
    const floating = page.locator('body > .et-promotion-hover-preview');
    await expect(floating).toHaveClass(/is-visible/, { timeout: 1_000 });
    const [cardBox, floatingBox] = await Promise.all([card.boundingBox(), floating.boundingBox()]);
    expect(cardBox).not.toBeNull();
    expect(floatingBox).not.toBeNull();
    const overlaps = floatingBox!.x < cardBox!.x + cardBox!.width
        && floatingBox!.x + floatingBox!.width > cardBox!.x
        && floatingBox!.y < cardBox!.y + cardBox!.height
        && floatingBox!.y + floatingBox!.height > cardBox!.y;
    expect(overlaps).toBe(false);
    await expect(card.locator('h3')).toBeVisible();
    await expect(card.locator('.et-v3-promotion__cta')).toBeVisible();

    await opener.click();
    await expect(dialog).toHaveJSProperty('open', true);
    await expect(floating).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('hidden');
    await expect(dialog.locator('[data-promotion-dialog-close]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveJSProperty('open', false);
    await expect(opener).toBeFocused();
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).not.toBe('hidden');
});

test('dekkhotell dialog shares scroll lock, focus trap and opener restoration', async ({ page }) => {
    await page.goto('/');
    const opener = page.locator('[data-dekkhotell-dialog-open]');
    const dialog = page.locator('[data-dekkhotell-dialog]');
    await opener.click();
    await expect(dialog).toHaveJSProperty('open', true);
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('hidden');
    await expect(dialog.locator('[data-dekkhotell-dialog-close]')).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    expect(await dialog.evaluate((element) => element === document.activeElement || element.contains(document.activeElement))).toBe(true);
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveJSProperty('open', false);
    await expect(opener).toBeFocused();
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).not.toBe('hidden');
});

test('homepage video lazy-loads, pauses offscreen and preserves manual pause', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    const control = root.locator('[data-homepage-video-control]');

    await expect(video).toHaveAttribute('preload', 'none');
    await expect(video).not.toHaveAttribute('autoplay', '');
    await expect(video).toHaveAttribute('data-src', /\.(mp4|webm)(\?|$)/);
    await expect(video).not.toHaveAttribute('src', /.+/);

    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect.poll(() => root.evaluate((element) => element.classList.contains('is-video-playing')), { timeout: 10_000 }).toBe(true);
    await control.click();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await expect(root).not.toHaveClass(/is-video-playing/);

    await page.locator('body').evaluate(() => window.scrollTo(0, 0));
    await root.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await control.click();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);
    await expect.poll(() => root.evaluate((element) => element.classList.contains('is-video-playing'))).toBe(true);

    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await expect.poll(() => page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(false);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await expect(root).not.toHaveClass(/is-video-playing/);
});

test('homepage video pauses under a modal and resumes its prior visible intent', async ({ page }) => {
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    const control = root.locator('[data-homepage-video-control]');
    const dialog = page.locator('[data-dekkhotell-dialog]');

    await expect(control).toBeVisible();
    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused), { timeout: 10_000 }).toBe(false);
    await page.locator('[data-dekkhotell-dialog-open]').click();
    await expect(dialog).toHaveJSProperty('open', true);
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await dialog.locator('[data-dekkhotell-dialog-close]').click();
    await expect(dialog).toHaveJSProperty('open', false);
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);
});

test('blocked homepage autoplay keeps the poster and manual play control stable', async ({ page }) => {
    await page.addInitScript(() => {
        HTMLMediaElement.prototype.play = () => Promise.reject(new DOMException('Autoplay blocked', 'NotAllowedError'));
    });
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    const control = root.locator('[data-homepage-video-control]');
    const poster = root.locator('.et-v3-logistics-media__poster');
    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect(root).toHaveClass(/is-video-blocked/);
    await expect(root).not.toHaveClass(/is-video-playing/);
    await expect(poster).toBeVisible();
    await expect(control).toBeVisible();
    await expect(control).toHaveAttribute('aria-pressed', 'true');
});

test('configured public workshop clip decodes a real portrait frame and keeps playback safeguards', async ({ page }) => {
    await routeHomepageMarkup(page, (html) => html
        .replaceAll('/storage/media/homepage/e2e/homepage-loop.mp4', '/brand/easytire-workshop-loop.mp4')
        .replaceAll('/brand/mobile-service.webp', '/brand/easytire-workshop-poster.webp'));
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    const control = root.locator('[data-homepage-video-control]');
    const dialog = page.locator('[data-dekkhotell-dialog]');
    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => ({
        width: element.videoWidth,
        height: element.videoHeight,
        readyState: element.readyState,
    })), { timeout: 10_000 }).toEqual({ width: 540, height: 960, readyState: 4 });
    await expect.poll(() => root.evaluate((element) => element.classList.contains('is-video-playing'))).toBe(true);
    expect(await video.evaluate((element: HTMLVideoElement) => new Promise<boolean>((resolve) => {
        if (!element.requestVideoFrameCallback) {
            resolve(element.readyState >= 2);
            return;
        }
        element.requestVideoFrameCallback(() => resolve(true));
        window.setTimeout(() => resolve(false), 2_000);
    }))).toBe(true);
    mkdirSync(qaDirectory, { recursive: true });
    await root.screenshot({ path: path.join(qaDirectory, 'configured-workshop-video-frame-540x960.png'), animations: 'disabled' });

    await page.locator('[data-dekkhotell-dialog-open]').click();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await dialog.locator('[data-dekkhotell-dialog-close]').click();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
    await control.click();
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await expect.poll(() => page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(false);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);

    await video.evaluate((element: HTMLVideoElement) => {
        element.src = '/__qa_missing_workshop_video__.mp4';
        element.load();
    });
    await expect(video).toBeHidden();
    await expect(control).toBeHidden();
    await expect(root).toHaveClass(/has-video-error/);
    await expect(root.locator('.et-v3-logistics-media__poster')).toBeVisible();
});
