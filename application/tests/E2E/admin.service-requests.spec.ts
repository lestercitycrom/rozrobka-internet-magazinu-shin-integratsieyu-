import { expect, test } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginAdmin } from './helpers';

test('staff intake flow creates, searches and edits an unconfirmed request', async ({ page }) => {
    await loginAdmin(page);
    await ensureAdminLocale(page, 'nb');
    await page.goto('/admin/service-requests/create');

    await expect(page.getByRole('heading', { name: 'Ny forespørsel' })).toBeVisible();
    await page.locator('[name="service_slug"]').selectOption('tesla-og-elbil');
    await page.locator('[name="booking_mode"]').selectOption('manual');
    await page.locator('[name="name"]').fill('E2E Telefonkunde');
    await page.locator('[name="phone"]').fill('98 76 54 32');
    await page.locator('[name="email"]').fill('');
    await page.locator('[name="preferred_window"]').fill('Etter kl. 14');
    await page.locator('[data-seasonal-fields] [data-vehicle="1"] [name="vehicle"]').fill('Tesla Model 3');
    await page.locator('[name="note"]').fill('Opprettet gjennom den manuelle arbeidsflyten.');
    await page.getByTestId('request-save').click();

    await expect(page).toHaveURL(/\/admin\/service-requests\/\d+$/);
    const reference = (await page.getByRole('heading').filter({ hasText: /^SR-/ }).textContent())?.trim();
    expect(reference).toMatch(/^SR-/);
    await expect(page.getByText('Registrert av medarbeider')).toBeVisible();
    await expect(page.getByText('Ikke bekreftet', { exact: true }).first()).toBeVisible();
    await expectNoGlobalOverflow(page);

    await page.goto('/admin/service-requests');
    await page.locator('[name="q"]').fill('98 76 54 32');
    await page.getByRole('button', { name: 'Vis resultater' }).click();
    const visibleResults = page.locator('article:visible, table:visible');
    await expect(visibleResults.getByText('E2E Telefonkunde').first()).toBeVisible();
    await visibleResults.getByRole('link', { name: reference! }).click();
    await page.getByTestId('request-edit').click();
    await page.locator('[name="name"]').fill('E2E Oppdatert kunde');
    await page.locator('[name="status"]').selectOption('contacted');
    await page.locator('[name="admin_note"]').fill('Kunden er kontaktet.');
    await page.getByTestId('request-save').click();

    await expect(page.getByText('E2E Oppdatert kunde')).toBeVisible();
    await expect(page.getByText('Kontaktet', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Kunden er kontaktet.')).toBeVisible();
    await expectNoGlobalOverflow(page);

    await ensureAdminLocale(page, 'ru');
    await expect(page.getByRole('link', { name: 'Редактировать заявку' })).toBeVisible();
    await expect(page.getByText('Внесено сотрудником')).toBeVisible();
});
