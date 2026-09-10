import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { expectNoGlobalOverflow, loginAdmin } from './helpers';

const evidenceDirectory = path.resolve(process.cwd(), '..', 'evidence', 'issue-46');

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
