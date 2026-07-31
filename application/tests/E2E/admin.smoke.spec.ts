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

    await page.getByRole('link', { name: 'Katalog', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/catalog/);
    await page.getByRole('link', { name: 'Demo 205/55 R16', exact: true }).click();
    await expect(page.getByRole('combobox', { name: /status/i })).toHaveValue('published');
    await expect(page.getByRole('button', { name: /oppdater publisering/i })).toBeVisible();
    await page.getByRole('link', { name: 'Importer', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/imports/);
    await page.getByRole('link', { name: /Bestillinger|Orders/i }).click();
    await page.getByRole('combobox', { name: /status/i }).selectOption('pending_review');
    await page.getByRole('button', { name: /filtrer/i }).click();
    await page.getByText('ET-E2E-ORDER').click();
    await expect(page.getByText('Immutable items and pricing snapshot')).toBeVisible();
    await expect(page.getByText(/NDI reserve\/cancel unavailable/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /Submit NDI|Reserve|Cancel API/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /Submit NDI|Reserve|Cancel API/i })).toHaveCount(0);

    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.getByRole('heading', { name: 'ET-E2E-ORDER' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Товары и снимок цены' })).toBeVisible();
    await page.getByRole('link', { name: 'Панель управления' }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Панель управления' })).toBeVisible();

    await page.getByRole('button', { name: 'Выйти' }).click();

    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.getByRole('heading', { name: /logg inn/i })).toBeVisible();
});
