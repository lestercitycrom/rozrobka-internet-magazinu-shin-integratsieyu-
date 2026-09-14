import { expect, test } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginOperations } from './helpers';

function surface(projectName: string): 'DESKTOP' | 'MOBILE-390' | 'MOBILE-320' {
    if (projectName.includes('320')) return 'MOBILE-320';
    if (projectName.includes('390')) return 'MOBILE-390';
    return 'DESKTOP';
}

test('operations completes the real Russian intake public follow-up and safe historical edit', async ({ page }, testInfo) => {
    const currentSurface = surface(testInfo.project.name);
    const suffix = currentSurface.toLowerCase();
    const manualName = `E2E RU intake ${suffix}`;
    const publicName = `E2E public no email ${suffix}`;

    await loginOperations(page);
    await ensureAdminLocale(page, 'nb');
    await page.goto('/admin/service-requests/create');
    await expect(page.getByRole('heading', { name: 'Ny forespørsel' })).toBeVisible();
    await ensureAdminLocale(page, 'ru');
    await page.goto('/admin/service-requests/create');

    await expect(page.getByRole('heading', { name: 'Новая заявка' })).toBeVisible();
    await page.locator('[name="service_slug"]').selectOption('bilbatteri');
    await page.locator('[name="fulfilment"]').selectOption('mobile');
    await page.locator('[name="address"]').fill('E2E Vanvegen 1');
    await expect(page.locator('[name="assigned_van"]')).toBeVisible();
    await page.locator('[name="assigned_van"]').selectOption('van_1');
    await page.locator('[name="booking_mode"]').selectOption('manual');
    await page.locator('[name="preferred_date"]').fill(new Date(Date.now() + 3 * 86_400_000).toISOString().slice(0, 10));
    await page.locator('[name="preferred_window"]').fill('После 14:00');
    await page.locator('[name="registration_number"]:enabled').fill('EV12345');
    await page.locator('[name="name"]').fill(manualName);
    await page.locator('[name="phone"]').fill('12345678');
    await page.locator('[name="email"]').fill('');
    await page.locator('[name="note"]').fill('Аккумулятор разряжается за ночь.');
    await page.getByTestId('request-save').click();

    await expect(page.getByTestId('request-form-errors')).toBeVisible();
    await expect(page.locator('[name="name"]')).toHaveValue(manualName);
    await expect(page.locator('[name="registration_number"]:enabled')).toHaveValue('EV12345');
    await expect(page.locator('[name="note"]')).toHaveValue('Аккумулятор разряжается за ночь.');
    await expect(page.locator('[name="service_slug"]')).toHaveValue('bilbatteri');

    await page.locator('[name="phone"]').fill('98 76 54 32');
    await page.getByTestId('request-save').click();
    await expect(page).toHaveURL(/\/admin\/service-requests\/\d+$/);
    const manualReference = (await page.getByRole('heading').filter({ hasText: /^SR-/ }).textContent())?.trim();
    expect(manualReference).toMatch(/^SR-/);
    await expect(page.getByText('Внесено сотрудником')).toBeVisible();
    await expect(page.getByText('Выездная машина 1', { exact: true })).toBeVisible();
    await expect(page.getByText('Не подтверждено', { exact: true }).first()).toBeVisible();

    await page.goto('/admin/service-requests');
    await page.locator('[name="q"]').fill('98 76 54 32');
    await page.getByRole('button', { name: 'Показать' }).click();
    let visibleResults = page.locator('article:visible, table:visible');
    await expect(visibleResults.getByText(manualName).first()).toBeVisible();
    await visibleResults.getByRole('link', { name: manualReference! }).first().click();
    await page.getByTestId('request-edit').click();
    await page.locator('[name="status"]').selectOption('contacted');
    await page.locator('[name="admin_note"]').fill('Клиенту перезвонили.');
    await page.getByTestId('request-save').click();
    await expect(page.getByText('Связались', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Клиенту перезвонили.')).toBeVisible();

    await page.goto('/ru/tjenester/oljeskift/foresporsel');
    await page.locator('#booking-fulfilment').selectOption('workshop');
    await page.locator('#booking-preferred-window').fill('После 16:00');
    await page.locator('#booking-name').fill(publicName);
    await page.locator('#booking-email').fill('');
    await page.locator('#booking-phone').fill('91234567');
    await page.locator('input[name="consent"]').check();
    await page.getByTestId('generic-submit').click();
    await expect(page).toHaveURL(/\/ru\/foresporsel\/SR-[^/]+\/takk$/);

    await page.goto('/admin/service-requests');
    await page.locator('[name="q"]').fill(publicName);
    await page.getByRole('button', { name: 'Показать' }).click();
    visibleResults = page.locator('article:visible, table:visible');
    await expect(visibleResults.getByText(publicName).first()).toBeVisible();

    const historicalReference = `SR-E2E-HIST-${currentSurface}`;
    await page.goto('/admin/service-requests');
    await page.locator('[name="q"]').fill(historicalReference);
    await page.getByRole('button', { name: 'Показать' }).click();
    visibleResults = page.locator('article:visible, table:visible');
    await visibleResults.getByRole('link', { name: historicalReference }).first().click();
    await page.getByTestId('request-edit').click();

    await expect(page.locator('[name="preferred_date"]')).toHaveValue(/\d{4}-\d{2}-\d{2}/);
    await expect(page.locator('[name="tire_size"]:enabled')).toHaveValue('255/45 R19');
    await expect(page.locator('[name="tire_size_2"]:enabled')).toHaveValue('235/55 R18');
    await expect(page.locator('[name="registration_number"]:enabled')).toHaveJSProperty('required', false);
    await page.locator('[name="name"]').fill(`E2E Historical updated ${suffix}`);
    await page.locator('[name="admin_note"]').fill('Only contact follow-up changed.');
    await page.getByTestId('request-save').click();

    await expect(page.getByText(`E2E Historical updated ${suffix}`)).toBeVisible();
    await expect(page.locator('main')).toContainText('255/45 R19');
    await expect(page.locator('main')).toContainText('235/55 R18');
    await expect(page.getByText(/2 300 NOK/)).toBeVisible();
    await expectNoGlobalOverflow(page);
});
