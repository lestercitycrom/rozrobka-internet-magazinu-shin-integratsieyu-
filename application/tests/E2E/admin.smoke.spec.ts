import { expect, test } from '@playwright/test';
import { ensureAdminLocale } from './helpers';

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';

test('administrator can sign in, persist Russian, and log out', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);

    await page.getByLabel(/e-postadresse/i).fill(adminEmail);
    await page.getByLabel(/passord/i).fill(adminPassword);
    await page.getByRole('button', { name: /logg inn/i }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await ensureAdminLocale(page, 'nb');
    await expect(page.getByRole('heading', { name: 'Det som trenger oppfølging nå.' })).toBeVisible();
    await page.getByRole('navigation').getByRole('link', { name: 'Tjenesteforespørsler', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/service-requests/);
    await expect(page.getByRole('heading', { name: 'Tjenesteforespørsler', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Timeplan', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Timeplan og kapasitet', exact: true })).toBeVisible();
    await expect(page.getByTestId('booking-interval-row')).toHaveCount(7);
    await page.getByRole('link', { name: 'Oversikt', exact: true }).click();
    await page.getByRole('link', { name: 'Tilleggstjenester', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/service-addons/);
    await expect(page.getByRole('heading', { name: 'Tilleggstjenester', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Offentlige priser', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/public-service-prices/);
    await expect(page.getByRole('heading', { name: 'Tjenestepriser', exact: true })).toBeVisible();
    await expect(page.getByTestId('public-service-price-row')).toHaveCount(11);
    await page.getByRole('link', { name: 'Oversikt', exact: true }).click();

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
    await expect(page.getByText(/Uforanderlig varelinje-/i)).toBeVisible();
    await expect(page.getByText(/Betaling og NDI håndteres bare gjennom de sikre handlingene/i)).toBeVisible();
    await expect(page.getByTestId('admin-order-payment')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Submit NDI|Reserve|Cancel API/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /Submit NDI|Reserve|Cancel API/i })).toHaveCount(0);

    await page.goto('/admin/orders');
    await page.getByText('ET-E2E-PAYMENT').click();
    await expect(page.getByTestId('admin-order-payment')).toBeVisible();
    await expect(page.getByTestId('admin-capture-payment')).toBeVisible();
    await expect(page.getByText('dintero_psp.creditcard')).toBeVisible();
    await expect(page.getByText(/maks 2/i)).toBeVisible();
    await expect(page.getByText(/Reservasjonen utløper/i)).toBeVisible();

    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.getByRole('heading', { name: 'ET-E2E-PAYMENT' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Товары', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Панель управления' }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'То, что требует внимания сейчас.' })).toBeVisible();

    await page.getByRole('button', { name: 'Выйти' }).click();

    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.getByRole('heading', { name: /logg inn|войти/i })).toBeVisible();
});
