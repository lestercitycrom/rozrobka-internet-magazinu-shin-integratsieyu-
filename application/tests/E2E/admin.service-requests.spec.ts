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
    await expect(page.locator('[name="vehicles[0][tire_size]"]:enabled')).toHaveValue('255/45 R19');
    await expect(page.locator('[name="vehicles[1][tire_size]"]:enabled')).toHaveValue('235/55 R18');
    await expect(page.locator('[name="vehicles[0][registration_number]"]:enabled')).toHaveJSProperty('required', false);
    await page.locator('[name="name"]').fill(`E2E Historical updated ${suffix}`);
    await page.locator('[name="admin_note"]').fill('Only contact follow-up changed.');
    await page.getByTestId('request-save').click();

    await expect(page.getByText(`E2E Historical updated ${suffix}`)).toBeVisible();
    await expect(page.locator('main')).toContainText('255/45 R19');
    await expect(page.locator('main')).toContainText('235/55 R18');
    await expect(page.getByText(/2 300 NOK/)).toBeVisible();
    await expectNoGlobalOverflow(page);

    const exactReference = `SR-E2E-EXACT-${currentSurface}`;
    await page.goto('/admin/service-requests');
    await page.locator('[name="q"]').fill(exactReference);
    await page.getByRole('button', { name: 'Показать' }).click();
    visibleResults = page.locator('article:visible, table:visible');
    await visibleResults.getByRole('link', { name: exactReference }).first().click();
    await page.getByTestId('request-edit').click();

    await expect(page.locator('[name="service_slug"]')).toBeDisabled();
    await expect(page.locator('select[name="vehicles[0][storage_plan]"]')).toBeDisabled();
    const bookedStorageNumber = page.getByTestId('booked-storage-number-0');
    await expect(bookedStorageNumber).toBeVisible();
    await expect(bookedStorageNumber).toBeEnabled();
    await bookedStorageNumber.fill('00042-A');
    await page.getByTestId('request-save').click();

    await expect(page.locator('main')).toContainText('00042-A');
    await page.getByTestId('request-edit').click();
    await expect(page.getByTestId('booked-storage-number-0')).toHaveValue('00042-A');
    await expectNoGlobalOverflow(page);
});

test('operations stores and reopens five vehicles with an internal hotel number', async ({ page }, testInfo) => {
    const currentSurface = surface(testInfo.project.name);
    const suffix = currentSurface.toLowerCase();
    const storageNumber = `00042-${currentSurface}`;

    await loginOperations(page);
    await page.goto('/admin/service-requests/create');
    await page.locator('[name="service_slug"]').selectOption('mobilt-dekkskift');
    await page.locator('[name="fulfilment"]').selectOption('workshop');
    await page.locator('[name="booking_mode"]').selectOption('period');
    await page.locator('[name="preferred_date"]').fill(new Date(Date.now() + 4 * 86_400_000).toISOString().slice(0, 10));
    await page.locator('[name="preferred_time_start"]').fill('09:00');
    await page.locator('[name="preferred_time_end"]').fill('11:00');

    for (let index = 1; index < 5; index += 1) {
        await page.locator('[data-add-vehicle]').click();
    }
    await expect(page.locator('[data-admin-vehicles] [data-request-vehicle]:visible')).toHaveCount(5);

    for (let index = 0; index < 5; index += 1) {
        await page.locator(`[name="vehicles[${index}][vehicle]"]`).fill(`E2E vehicle ${index + 1} ${suffix}`);
        await page.locator(`[name="vehicles[${index}][vehicle_type]"]`).selectOption('passenger');
        await page.locator(`[name="vehicles[${index}][rim_band]"]`).selectOption('r16-r18');
        await page.locator(`[name="vehicles[${index}][storage_plan]"]`).selectOption(index === 4 ? 'last' : 'none');
    }
    await page.locator('[name="vehicles[4][registration_number]"]').fill(`LAST${currentSurface.replace('-', '')}`);
    await page.locator('[name="vehicles[4][storage_number]"]').fill(storageNumber);
    await page.locator('[name="name"]').fill(`E2E five cars ${suffix}`);
    await page.locator('[name="phone"]').fill('98 76 54 32');
    await page.getByTestId('request-save').click();

    await expect(page).toHaveURL(/\/admin\/service-requests\/\d+$/);
    await expect(page.locator('main')).toContainText(`E2E vehicle 5 ${suffix}`);
    await expect(page.locator('main')).toContainText(storageNumber);
    await page.getByTestId('request-edit').click();
    await expect(page.locator('[data-admin-vehicles] [data-request-vehicle]:visible')).toHaveCount(5);
    await expect(page.locator('[name="vehicles[4][storage_number]"]')).toHaveValue(storageNumber);
    await expectNoGlobalOverflow(page);
});
