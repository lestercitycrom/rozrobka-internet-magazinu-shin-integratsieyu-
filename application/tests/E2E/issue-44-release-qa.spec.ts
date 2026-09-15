import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginAdmin } from './helpers';

test.describe.configure({ mode: 'serial' });

const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-44');
const isDesktopPass = (projectName: string): boolean => projectName === 'issue-44-release-desktop';
const isTouchPass = (projectName: string): boolean => projectName.startsWith('issue-44-release-touch-');

async function tabTo(page: Page, target: Locator, maximum = 240): Promise<void> {
    for (let index = 0; index < maximum; index += 1) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((element) => document.activeElement === element)) return;
    }

    throw new Error(`Keyboard focus did not reach ${await target.getAttribute('id') ?? 'target'}.`);
}

test('natural full storefront settings save preserves unrelated values and remains usable at 1024, 390 and 320 in NB and RU', async ({ page }, testInfo) => {
    test.skip(!isDesktopPass(testInfo.project.name), 'One isolated serial admin matrix is sufficient.');
    test.setTimeout(180_000);
    await loginAdmin(page);
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto('/admin/storefront-settings');

    const form = page.locator('form[data-dirty-form]');
    const marker = `Issue 44 full save ${Date.now()}`;
    const heroTitleBefore = await page.locator('#hero_title_nb').inputValue();
    const contactTitleBefore = await page.locator('#contact_title_ru').inputValue();
    const submittedNames = await form.locator('input[name], textarea[name], select[name]').evaluateAll((controls) =>
        controls.filter((control) => !(control as HTMLInputElement).disabled).map((control) => control.getAttribute('name')),
    );
    expect(submittedNames.length).toBeGreaterThan(90);
    expect(new Set(submittedNames).size).toBe(submittedNames.length);
    await expect(form.locator('input:disabled, textarea:disabled, select:disabled')).toHaveCount(0);

    await page.locator('#business_partners_heading_nb').fill(marker);
    await page.locator('#hotel_video_media_asset_id').selectOption('');
    await page.getByRole('button', { name: /lagre|сохранить/i }).click();
    await expect(page).toHaveURL(/\/admin\/storefront-settings$/);
    await expect(page.locator('p[role="status"]')).toContainText(/lagret|сохран/i);
    await expect(page.locator('#business_partners_heading_nb')).toHaveValue(marker);
    await expect(page.locator('#hero_title_nb')).toHaveValue(heroTitleBefore);
    await expect(page.locator('#contact_title_ru')).toHaveValue(contactTitleBefore);
    await expect(page.locator('#hotel_video_media_asset_id')).toHaveValue('');
    await page.reload();
    await expect(page.locator('#business_partners_heading_nb')).toHaveValue(marker);
    await expect(page.locator('#hero_title_nb')).toHaveValue(heroTitleBefore);
    await expect(page.locator('#contact_title_ru')).toHaveValue(contactTitleBefore);
    await expect(page.locator('#hotel_video_media_asset_id')).toHaveValue('');

    if (process.env.CAPTURE_ISSUE_44 === '1') await mkdir(evidenceRoot, { recursive: true });
    for (const locale of ['nb', 'ru'] as const) {
        await page.setViewportSize({ width: 1024, height: 768 });
        await ensureAdminLocale(page, locale);

        for (const viewport of [
            { width: 1024, height: 768 },
            { width: 390, height: 844 },
            { width: 320, height: 800 },
        ]) {
            await page.setViewportSize(viewport);
            await page.goto('/admin/storefront-settings');
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            for (const selector of [
                '#mobile_details_title_nb', '#mobile_details_body_1_nb', '#mobile_details_title_ru',
                '#business_partners_heading_nb', '#business_partners_heading_ru', '#hotel_video_media_asset_id',
            ]) {
                await expect(page.locator(selector)).toBeVisible();
            }
            await expectNoGlobalOverflow(page);

            const localeDetails = page.locator(`#mobile_details_title_${locale}`);
            await page.locator('body').press('Home');
            await tabTo(page, localeDetails);
            await expect(localeDetails).toBeFocused();
            await tabTo(page, page.locator('#hotel_video_media_asset_id'));
            await expect(page.locator('#hotel_video_media_asset_id')).toBeFocused();

            if (process.env.CAPTURE_ISSUE_44 === '1') {
                await page.screenshot({
                    path: path.join(evidenceRoot, `admin-storefront-settings-${locale}-${viewport.width}x${viewport.height}.png`),
                    fullPage: true,
                });
            }
        }
    }
});

test('Dekkhotell dialog contains reverse Tab and remains readable without JavaScript in NB and RU', async ({ browser, page }, testInfo) => {
    test.skip(!isDesktopPass(testInfo.project.name), 'One deterministic desktop/no-JS matrix is sufficient.');
    test.setTimeout(120_000);

    for (const route of ['/', '/ru']) {
        await page.goto(route);
        const opener = page.locator('[data-dekkhotell-dialog-open]');
        const dialog = page.locator('[data-dekkhotell-dialog]');
        const close = dialog.locator('[data-dekkhotell-dialog-close]');
        const cta = dialog.locator('a.et-btn');
        await tabTo(page, opener);
        await page.keyboard.press('Enter');
        await expect(close).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(cta).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(close).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(cta).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).not.toHaveAttribute('open', '');
        await expect(opener).toBeFocused();
    }

    const baseURL = String(testInfo.project.use.baseURL ?? 'http://127.0.0.1:8000');
    for (const viewport of [{ width: 390, height: 844 }, { width: 320, height: 800 }]) {
        const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport });
        const noJsPage = await context.newPage();
        for (const route of ['/', '/ru']) {
            await noJsPage.goto(route);
            const details = noJsPage.locator('details.et-v3-logistics-noscript');
            await details.scrollIntoViewIfNeeded();
            await expect(details).toBeVisible();
            await details.locator('summary').click();
            await expect(details).toHaveAttribute('open', '');
            await expect(details.locator('p')).toHaveCount(2);
            await expect(details.locator('p').last()).toBeVisible();
            await expectNoGlobalOverflow(noJsPage);
        }
        await context.close();
    }
});

test('Dekkhotell dialog opens and closes through real touch and its mobile content scrolls to the CTA', async ({ page }, testInfo) => {
    test.skip(!isTouchPass(testInfo.project.name), 'This contract requires a touch-enabled Playwright project.');
    test.setTimeout(90_000);
    await page.addInitScript(() => {
        (window as unknown as { __issue44PointerType?: string }).__issue44PointerType = '';
        document.addEventListener('pointerdown', (event) => {
            (window as unknown as { __issue44PointerType?: string }).__issue44PointerType = event.pointerType;
        }, true);
    });

    for (const route of ['/', '/ru']) {
        await page.goto(route);
        const opener = page.locator('[data-dekkhotell-dialog-open]');
        const dialog = page.locator('[data-dekkhotell-dialog]');
        const close = dialog.locator('[data-dekkhotell-dialog-close]');
        const cta = dialog.locator('a.et-btn');
        await opener.scrollIntoViewIfNeeded();
        await opener.tap();
        await expect(dialog).toHaveAttribute('open', '');
        expect(await page.evaluate(() => (window as unknown as { __issue44PointerType?: string }).__issue44PointerType)).toBe('touch');

        const scrollState = await dialog.evaluate((element) => ({
            clientHeight: element.clientHeight,
            scrollHeight: element.scrollHeight,
            before: element.scrollTop,
        }));
        if (scrollState.scrollHeight > scrollState.clientHeight + 1) {
            await dialog.evaluate((element) => element.scrollTo({ top: element.scrollHeight, behavior: 'instant' }));
            await expect.poll(() => dialog.evaluate((element) => element.scrollTop)).toBeGreaterThan(scrollState.before);
        } else {
            expect(scrollState.scrollHeight).toBeLessThanOrEqual(scrollState.clientHeight + 1);
        }
        await expect(cta).toBeInViewport();
        await expectNoGlobalOverflow(page);

        await close.tap();
        await expect(dialog).not.toHaveAttribute('open', '');
        await expect(opener).toBeFocused();
    }
});

test('reduced-motion homepage preserves its video poster at the focused native viewports in NB and RU', async ({ page }, testInfo) => {
    test.skip(!isDesktopPass(testInfo.project.name), 'One deterministic native-resolution evidence pass is sufficient.');
    test.setTimeout(180_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    if (process.env.CAPTURE_ISSUE_44 === '1') await mkdir(evidenceRoot, { recursive: true });
    const records: Record<string, unknown>[] = [];

    for (const locale of [{ code: 'nb', route: '/' }, { code: 'ru', route: '/ru' }]) {
        for (const viewport of [
            { width: 1440, height: 900 },
            { width: 390, height: 844 },
            { width: 320, height: 800 },
        ]) {
            await page.setViewportSize(viewport);
            await page.goto(locale.route, { waitUntil: 'domcontentloaded' });
            if (process.env.CAPTURE_ISSUE_44 === '1') {
                await page.screenshot({
                    path: path.join(evidenceRoot, `homepage-reduced-motion-${locale.code}-${viewport.width}x${viewport.height}.jpg`),
                    type: 'jpeg',
                    quality: 78,
                    fullPage: true,
                });
            }
            const hotel = page.getByTestId('home-dekkhotell');
            await hotel.scrollIntoViewIfNeeded();
            await expect(hotel.locator('.et-v3-logistics-media__poster')).toBeVisible();
            const video = hotel.locator('[data-homepage-video-element]');
            await expect(video).toHaveCount(1);
            await expect(video).toHaveAttribute('data-src', /\.mp4(?:\?|$)/);
            await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
            await expect(hotel.locator('[data-homepage-video-control]')).toBeVisible();
            await expect(hotel.locator('[data-dekkhotell-dialog-open]')).toBeVisible();
            await expectNoGlobalOverflow(page);
            const geometry = await hotel.evaluate((element) => {
                const rect = element.getBoundingClientRect();
                return { top: rect.top, width: rect.width, height: rect.height };
            });
            records.push({ locale: locale.code, viewport, hotel: geometry, videoAssigned: true, reducedMotion: true });

            if (process.env.CAPTURE_ISSUE_44 === '1') {
                await hotel.screenshot({
                    path: path.join(evidenceRoot, `reduced-motion-dekkhotell-${locale.code}-${viewport.width}x${viewport.height}.png`),
                });
            }
        }
    }

    if (process.env.CAPTURE_ISSUE_44 === '1') {
        await writeFile(path.join(evidenceRoot, 'reduced-motion-measurements.json'), `${JSON.stringify(records, null, 2)}\n`);
    }
});
