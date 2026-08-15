import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

test('approved homepage composition stays coherent at the priority desktop and mobile widths', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial multi-viewport visual contract is enough');

    for (const viewport of [
        { width: 1440, height: 1000 },
        { width: 430, height: 932 },
        { width: 390, height: 844 },
        { width: 360, height: 800 },
        { width: 320, height: 844 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        await page.locator('.et-v3-hero__photo').evaluate((image: HTMLImageElement) => {
            if (image.complete) return;
            return new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
            });
        });

        const skipLink = page.locator('.skip-link');
        await expect(skipLink).not.toBeFocused();
        expect(await skipLink.evaluate((element) => element.getBoundingClientRect().bottom)).toBeLessThanOrEqual(0);
        await expect(page.locator('[data-site-header]')).not.toHaveClass(/is-scrolled/);
        await expect(page.locator('.et-v3-hero__primary-action')).toHaveCount(1);
        await expect(page.getByTestId('home-dekkhotell')).toBeVisible();
        await expect(page.getByTestId('home-process')).toBeVisible();
        await expect(page.getByTestId('home-prices')).toBeVisible();
        await expect(page.getByTestId('homepage-promotions')).toBeVisible();
        await expect(page.locator('.et-v3-trust, .et-v3-story')).toHaveCount(0);
        await expectNoGlobalOverflow(page);

        const firstPromotion = page.getByTestId('homepage-promotion').first();
        const promotionDetails = firstPromotion.locator('.et-v3-promotion__details');
        await expect(firstPromotion).toBeVisible();

        if (viewport.width > 767) {
            await page.mouse.move(0, 0);
            await firstPromotion.blur();
            await expect(promotionDetails).not.toBeVisible();
            await firstPromotion.hover();
            await expect(promotionDetails).toBeVisible();
            await page.mouse.move(0, 0);
            await firstPromotion.focus();
            await expect(promotionDetails).toBeVisible();
        } else {
            await expect(promotionDetails).toBeVisible();
        }

        const ordering = await page.evaluate(() => {
            const y = (selector: string) => document.querySelector<HTMLElement>(selector)?.getBoundingClientRect().top
                ?? Number.POSITIVE_INFINITY;

            return {
                dekkhotell: y('[data-testid="home-dekkhotell"]'),
                process: y('[data-testid="home-process"]'),
                prices: y('[data-testid="home-prices"]'),
            };
        });
        expect(ordering.dekkhotell).toBeLessThan(ordering.process);
        expect(ordering.process).toBeLessThan(ordering.prices);

        const mapHeight = await page.getByTestId('map-card').evaluate((element) => element.getBoundingClientRect().height);
        expect(mapHeight).toBeGreaterThanOrEqual(viewport.width <= 640 ? 300 : 400);
        expect(mapHeight).toBeLessThanOrEqual(viewport.width <= 640 ? 380 : 500);

        const mapFrame = page.getByTestId('map-iframe');
        if (await mapFrame.count()) {
            await expect(mapFrame).toHaveAttribute('src', /^https:\/\//);
            await expect.poll(async () => {
                const handle = await mapFrame.elementHandle();
                const frame = await handle?.contentFrame();

                return frame?.url();
            }).not.toBe('about:blank');
        }

        await page.evaluate(() => window.scrollTo(0, 420));
        await expect(page.locator('[data-site-header]')).toHaveClass(/is-scrolled/);

        if (process.env.CAPTURE_VISUALS === '1' && [1440, 390, 320].includes(viewport.width)) {
            for (const [name, selector] of [
                ['hero', '.et-v3-hero'],
                ['dekkhotell', '[data-testid="home-dekkhotell"]'],
                ['process-prices', '[data-testid="home-process"]'],
                ['promotions', '[data-testid="homepage-promotions"]'],
                ['contact', '.et-v3-contact'],
                ['footer', '.et-footer'],
            ] as const) {
                if (name === 'hero') {
                    await page.evaluate(() => {
                        document.documentElement.style.scrollBehavior = 'auto';
                        document.body.style.scrollBehavior = 'auto';
                        window.scrollTo(0, 0);
                    });
                } else {
                    await page.locator(selector).evaluate((element) => {
                        document.documentElement.style.scrollBehavior = 'auto';
                        document.body.style.scrollBehavior = 'auto';
                        const headerHeight = document.querySelector<HTMLElement>('[data-site-header]')?.offsetHeight ?? 0;
                        const top = element.getBoundingClientRect().top + window.scrollY - headerHeight - 16;
                        window.scrollTo(0, Math.max(0, top));
                    });
                }
                await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve()))));
                await page.waitForTimeout(250);
                const headerBooking = page.getByTestId('header-booking');
                if (await headerBooking.isVisible()) {
                    expect(await headerBooking.evaluate((element) => element.scrollWidth <= element.clientWidth + 1)).toBeTruthy();
                }
                await page.screenshot({ path: testInfo.outputPath(`${viewport.width}-${name}.png`) });
            }
        }
    }
});
