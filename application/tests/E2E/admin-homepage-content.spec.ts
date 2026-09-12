import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { ensureAdminLocale, expectNoGlobalOverflow, loginAdmin } from './helpers';

const evidenceDirectory = path.resolve(process.cwd(), '..', 'evidence', 'issue-46');
const compositionEvidenceDirectory = path.resolve(process.cwd(), '..', 'evidence', 'issue-47');
const requiredViewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 1000 },
    { width: 1366, height: 900 },
    { width: 1280, height: 900 },
    { width: 1024, height: 768 },
    { width: 390, height: 844 },
    { width: 320, height: 844 },
];

async function tabTo(page, selector: string, maximumTabs = 12): Promise<void> {
    await page.locator('#main-content a').first().focus();

    for (let index = 0; index < maximumTabs; index += 1) {
        await page.keyboard.press('Tab');
        if (await page.locator(selector).evaluate((element) => element === document.activeElement)) return;
    }

    throw new Error(`Keyboard focus did not reach ${selector}`);
}

async function tabForwardTo(page, selector: string, maximumTabs = 220): Promise<void> {
    for (let index = 0; index < maximumTabs; index += 1) {
        await page.keyboard.press('Tab');
        if (await page.locator(selector).evaluate((element) => element === document.activeElement)) return;
    }

    throw new Error(`Keyboard focus did not reach ${selector}.`);
}

test('administrator manages one homepage service card while collections remain independent', async ({ page }) => {
    await loginAdmin(page);
    await page.goto('/admin/homepage-service-collections/additional');
    await expect(page.locator('tbody tr')).toHaveCount(6);
    await expect(page.locator('a[href$="/admin/homepage-service-collections/wheels"]')).toBeVisible();
    if (process.env.CAPTURE_VISUALS === '1') {
        mkdirSync(evidenceDirectory, { recursive: true });
        await page.screenshot({ path: path.join(evidenceDirectory, 'admin-additional-services-1440.png'), fullPage: true });
    }
    await page.goto('/admin/homepage-service-collections/additional/create');

    if (process.env.CAPTURE_VISUALS === '1') {
        await page.screenshot({ path: path.join(evidenceDirectory, 'admin-additional-service-form-1440.png'), fullPage: true });
    }

    const marker = `E2E ${Date.now()}`;
    await page.locator('#service-card-order').fill('777');
    await page.locator('#service-card-status').selectOption('draft');
    await page.locator('#service-card-title_nb').fill(marker);
    await page.locator('#service-card-description_nb').fill('Kontrollert administratortekst.');
    await page.locator('#service-card-cta_label_nb').fill('Les mer');
    await page.locator('#service-card-title_ru').fill(`RU ${marker}`);
    await page.locator('#service-card-description_ru').fill('Проверенный текст администратора.');
    await page.locator('#service-card-cta_label_ru').fill('Подробнее');
    await page.locator('#service-card-url').fill('/kontakt');
    await page.getByRole('button', { name: /lagre|сохранить/i }).click();
    await expect(page).toHaveURL(/\/admin\/homepage-service-collections\/additional\/\d+\/edit$/);
    await expect(page.locator('#service-card-title_nb')).toHaveValue(marker);

    await page.locator('#service-card-order').fill('5');
    await page.locator('#service-card-title_nb').fill(`${marker} updated`);
    await page.getByRole('button', { name: /lagre|сохранить/i }).click();
    await expect(page.locator('#service-card-order')).toHaveValue('5');
    await expect(page.locator('#service-card-title_nb')).toHaveValue(`${marker} updated`);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /slett|удалить/i }).click();
    await expect(page).toHaveURL(/\/admin\/homepage-service-collections\/additional$/);
    await expect(page.getByText(`${marker} updated`)).toHaveCount(0);
    await expectNoGlobalOverflow(page);
});

test('homepage content administration stays usable in NB and RU at every required viewport', async ({ page }) => {
    test.setTimeout(120_000);
    await loginAdmin(page);

    for (const locale of ['nb', 'ru'] as const) {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await ensureAdminLocale(page, locale);

        for (const viewport of requiredViewports) {
            await page.setViewportSize(viewport);

            await page.goto('/admin/homepage-service-collections/additional');
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await expect(page.locator('main h1')).toBeVisible();
            if (viewport.width >= 768) {
                await expect(page.getByRole('region').filter({ has: page.locator('table') })).toBeVisible();
            } else {
                await expect(page.getByTestId('homepage-service-cards-mobile-list')).toBeVisible();
            }
            await expectNoGlobalOverflow(page);

            if (process.env.CAPTURE_VISUALS === '1' && locale === 'nb' && viewport.width === 1024) {
                mkdirSync(evidenceDirectory, { recursive: true });
                await page.screenshot({ path: path.join(evidenceDirectory, 'admin-additional-services-1024-nb.png'), fullPage: true });
            }

            await page.goto('/admin/homepage-service-collections/additional/create');
            await expect(page.locator('#service-card-order')).toBeVisible();
            await expect(page.locator('#service-card-title_nb')).toBeVisible();
            await expect(page.locator('#service-card-title_ru')).toBeVisible();
            await tabTo(page, '#service-card-order');
            await expect(page.locator('#service-card-order')).toBeFocused();
            await expectNoGlobalOverflow(page);

            if (process.env.CAPTURE_VISUALS === '1' && locale === 'ru' && viewport.width === 390) {
                await page.screenshot({ path: path.join(evidenceDirectory, 'admin-additional-service-form-390-ru.png'), fullPage: true });
            }

            if (viewport.width === 1440) {
                await page.locator('#service-card-title_nb').fill('');
                await page.getByRole('button', { name: /lagre|сохранить/i }).click();
                await expect(page.locator('#service-card-title_nb')).toBeFocused();
                await expect(page.locator('#service-card-title_nb')).toHaveJSProperty('validity.valueMissing', true);
                await expect(page).toHaveURL(/\/admin\/homepage-service-collections\/additional\/create$/);
            }

            await page.goto('/admin/homepage-promotions');
            await expect(page.locator('main h1')).toBeVisible();
            if (viewport.width >= 768) {
                await expect(page.getByRole('region').filter({ has: page.locator('table') })).toBeVisible();
            } else {
                await expect(page.getByTestId('homepage-promotions-mobile-list')).toBeVisible();
            }
            await expectNoGlobalOverflow(page);
            const editHref = await page.locator('tbody a[href*="/admin/homepage-promotions/"]').first().getAttribute('href');
            expect(editHref).toBeTruthy();
            await page.goto(editHref!);
            await expect(page.locator('#promotion-order')).toBeVisible();
            await expect(page.locator('#promotion-title_nb')).toBeVisible();
            await expect(page.locator('#promotion-title_ru')).toBeVisible();
            await tabTo(page, '#promotion-order');
            await expect(page.locator('#promotion-order')).toBeFocused();
            await expectNoGlobalOverflow(page);

            if (process.env.CAPTURE_VISUALS === '1' && locale === 'nb' && viewport.width === 320) {
                await page.screenshot({ path: path.join(evidenceDirectory, 'admin-promotion-form-320-nb.png'), fullPage: true });
            }
        }
    }
});

test('administrator can manage the final homepage copy and video assignment at 1024x768', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await loginAdmin(page);
    await page.goto('/admin/storefront-settings');

    for (const selector of [
        '#mobile_details_title_nb', '#mobile_details_body_1_nb', '#mobile_details_body_2_nb',
        '#process_cta_nb', '#business_cta_nb', '#business_partners_heading_nb', '#contact_map_note_nb',
        '#mobile_details_title_ru', '#process_cta_ru', '#business_cta_ru', '#business_partners_heading_ru', '#contact_map_note_ru',
        '#hotel_video_media_asset_id',
    ]) {
        await expect(page.locator(selector)).toBeVisible();
    }
    await expect(page.locator('#hotel_video_media_asset_id')).toHaveValue(/.+/);
    await expect(page.locator('#hotel_video_media_asset_id option:checked')).toContainText('e2e-homepage-loop.mp4');
    await expect(page.locator('#mobile_details_title_nb')).toHaveValue('Dekkhotell uten stress');
    await expect(page.locator('#process_cta_ru')).toHaveValue('Заказать сейчас!');
    await expectNoGlobalOverflow(page);

    await tabForwardTo(page, '#mobile_details_title_nb');
    await expect(page.locator('#mobile_details_title_nb')).toBeFocused();
    await tabForwardTo(page, '#hotel_video_media_asset_id');
    await expect(page.locator('#hotel_video_media_asset_id')).toBeFocused();

    const imageAssetId = await page.locator('#hero_media_asset_id option').evaluateAll((options) =>
        options.map((option) => (option as HTMLOptionElement).value).find(Boolean) ?? '',
    );
    expect(imageAssetId).not.toBe('');
    await page.locator('#hotel_video_media_asset_id').evaluate((select, invalidId) => {
        const option = document.createElement('option');
        option.value = String(invalidId);
        option.textContent = 'Injected image assignment';
        select.append(option);
    }, imageAssetId);
    await page.locator('#hotel_video_media_asset_id').selectOption(imageAssetId);
    await page.locator('form[data-dirty-form]').locator('input, textarea, select').evaluateAll((controls) => {
        const submitted = new Set(['_token', '_method', 'hotel_video_media_asset_id']);
        controls.forEach((control) => {
            const element = control as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
            if (!submitted.has(element.name)) element.disabled = true;
        });
    });
    await page.getByRole('button', { name: /lagre|сохранить/i }).click();
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/storefront-settings$/);

    await page.reload();
    const marker = `E2E partneroverskrift ${Date.now()}`;
    await page.locator('#business_partners_heading_nb').fill(marker);
    await page.locator('form[data-dirty-form]').locator('input, textarea, select').evaluateAll((controls) => {
        const submitted = new Set(['_token', '_method', 'business_partners_heading_nb']);
        controls.forEach((control) => {
            const element = control as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
            if (!submitted.has(element.name)) element.disabled = true;
        });
    });
    await page.getByRole('button', { name: /lagre|сохранить/i }).click();
    await expect(page.locator('p[role="status"]')).toContainText(/lagret|сохран/i);
    await expect(page.locator('#business_partners_heading_nb')).toHaveValue(marker);
    await page.reload();
    await expect(page.locator('#business_partners_heading_nb')).toHaveValue(marker);
    await expect(page.locator('#hotel_video_media_asset_id option:checked')).toContainText('e2e-homepage-loop.mp4');

    await page.goto('/admin/media');
    await expect(page.locator('input[type="file"][name="image"]')).toHaveAttribute('accept', 'image/jpeg,image/png,image/webp,video/mp4,video/webm');
    await expect(page.locator('video[src*="homepage-loop.mp4"]')).toBeVisible();
    await expectNoGlobalOverflow(page);

    if (process.env.CAPTURE_ISSUE_47 === '1') {
        mkdirSync(compositionEvidenceDirectory, { recursive: true });
        await page.screenshot({ path: path.join(compositionEvidenceDirectory, 'admin-media-video-1024x768.png'), fullPage: true });
        await page.goto('/admin/storefront-settings');
        await page.screenshot({ path: path.join(compositionEvidenceDirectory, 'admin-homepage-settings-1024x768.png'), fullPage: true });
    }
});
