import { expect, test } from '@playwright/test';

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';

test('administrator can sign in, persist Russian, and log out', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);

    await page.getByLabel(/e-postadresse/i).fill(adminEmail);
    await page.getByLabel(/passord/i).fill(adminPassword);
    await page.getByRole('button', { name: /logg inn/i }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: /oversikt/i })).toBeVisible();

    await page.getByTestId('switch-language-ru').click();
    await expect(page.getByRole('heading', { name: 'Панель управления' })).toBeVisible();

    await page.getByRole('button', { name: 'Выйти' }).click();

    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.getByRole('heading', { name: /logg inn/i })).toBeVisible();
});
