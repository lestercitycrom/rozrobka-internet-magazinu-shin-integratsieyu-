import { expect, test, type Locator, type Page } from '@playwright/test';
import { expectNoGlobalOverflow, localePrefix } from './helpers';

const field = (page: Page, name: string) => page.locator(`[name="${name}"]`);

async function nextFrom(panel: Locator): Promise<void> {
    await panel.locator('[data-booking-next]').click();
}

async function openSeasonalVehicleStep(page: Page): Promise<Locator> {
    const form = page.getByTestId('seasonal-service-request');
    await expect(form).toBeVisible();
    await nextFrom(page.getByTestId('seasonal-step-location'));
    const vehicleStep = page.getByTestId('seasonal-step-vehicle-storage');
    await expect(vehicleStep).toBeVisible();

    return vehicleStep;
}

test('seasonal quote updates to the exact public price for one car', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    const vehicleStep = await openSeasonalVehicleStep(page);
    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'rim_band').selectOption('r16-r18');
    await field(page, 'storage_plan').selectOption('none');

    const quote = page.locator('[data-seasonal-quote-text]');
    await expect(quote).toContainText(/600\s*NOK/);
    await expect(page.locator('[data-seasonal-summary-price]')).toContainText(/600\s*NOK/);
    await expectNoGlobalOverflow(page);
});

test('seasonal quote includes both cars and a renewed six-month hotel period', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    await page.getByTestId('seasonal-step-location').locator('input[name="fulfilment"][value="mobile"]').check();
    await field(page, 'address').fill('Stakkevollvegen 33, Tromsø');
    const vehicleStep = await openSeasonalVehicleStep(page);

    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'rim_band').selectOption('r16-r18');
    await field(page, 'storage_plan').selectOption('renew');
    await field(page, 'car_count').selectOption('2');
    const secondVehicle = vehicleStep.locator('[data-seasonal-vehicle="2"]');
    await expect(secondVehicle).toBeVisible();
    await field(page, 'vehicle_2').fill('Volvo XC60');
    await field(page, 'rim_band_2').selectOption('r19');
    await field(page, 'storage_plan_2').selectOption('none');

    await expect(page.locator('[data-seasonal-quote-text]')).toContainText(/2\s*300\s*NOK/);
    await expect(page.locator('[data-seasonal-summary-price]')).toContainText(/2\s*300\s*NOK/);

    await field(page, 'rim_band').selectOption('unknown');
    await expect(page.locator('[data-seasonal-quote-text]')).toContainText(/[–-]/);
    await expect(page.locator('[data-seasonal-quote-text]')).toContainText(/NOK/);
    await expectNoGlobalOverflow(page);
});

test('seasonal flow retains its entered data when the buyer moves between panels', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    const vehicleStep = await openSeasonalVehicleStep(page);
    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'registration_number').fill('EV12345');
    await field(page, 'tire_size').fill('255/45 R19');
    await field(page, 'rim_band').selectOption('r19');
    await nextFrom(vehicleStep);

    const time = page.getByTestId('seasonal-step-time');
    await field(page, 'preferred_date').fill('2030-10-15');
    const slotSelect = field(page, 'booking_slot_key');
    await expect(slotSelect).toBeVisible();
    await expect(slotSelect.locator('option')).toHaveCount(7);
    await slotSelect.selectOption({ index: 1 });
    const selectedSlot = await slotSelect.inputValue();
    await time.locator('[data-booking-back]').click();

    await expect(field(page, 'vehicle')).toHaveValue('Tesla Model Y');
    await expect(field(page, 'registration_number')).toHaveValue('EV12345');
    await expect(field(page, 'tire_size')).toHaveValue('255/45 R19');
    await expect(field(page, 'rim_band')).toHaveValue('r19');
    await nextFrom(vehicleStep);
    await expect(field(page, 'preferred_date')).toHaveValue('2030-10-15');
    await expect(field(page, 'booking_slot_key')).toHaveValue(selectedSlot);
    await expectNoGlobalOverflow(page);
});

test('seasonal server validation reopens the first panel and preserves the buyer input', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    await page.getByTestId('seasonal-step-location').locator('input[name="fulfilment"][value="mobile"]').check();
    await field(page, 'address').fill('Stakkevollvegen 33, Tromsø');
    const vehicleStep = await openSeasonalVehicleStep(page);
    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'registration_number').fill('EV12345');
    await field(page, 'rim_band').selectOption('r19');
    await nextFrom(vehicleStep);
    await field(page, 'preferred_date').fill('2030-10-15');
    await nextFrom(page.getByTestId('seasonal-step-time'));

    const form = page.getByTestId('seasonal-service-request');
    await form.evaluate((element) => {
        const formElement = element as HTMLFormElement;
        formElement.noValidate = true;
        formElement.requestSubmit();
    });

    await expect(page.getByTestId('request-errors')).toBeVisible();
    await expect(page.getByTestId('seasonal-step-location')).toBeVisible();
    await expect(page.getByTestId('seasonal-step-vehicle-storage')).toBeHidden();
    await expect(field(page, 'address')).toHaveValue('Stakkevollvegen 33, Tromsø');
    await expect(field(page, 'vehicle')).toHaveValue('Tesla Model Y');
    await expect(field(page, 'registration_number')).toHaveValue('EV12345');
});

test('mounting and TPMS requests are workshop-only', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);

    for (const service of ['omlegging-balansering', 'tpms-sensor']) {
        await page.goto(`${prefix}/tjenester/${service}/foresporsel`);
        const form = page.getByTestId('generic-service-request');
        const fulfilment = form.locator('select[name="fulfilment"]');
        await expect(fulfilment).toHaveValue('workshop');
        await expect(fulfilment.locator('option')).toHaveCount(1);
        await expect(fulfilment.locator('option[value="mobile"]')).toHaveCount(0);
    }

    await page.goto(`${prefix}/tjenester/omlegging-balansering/foresporsel`);
    const mounting = page.getByTestId('generic-service-request');
    await expect(mounting.locator('[name="service_variant"]')).toHaveAttribute('required', '');
    await expect(mounting.locator('[name="wheel_count"]')).toHaveAttribute('required', '');
    await expect(mounting.locator('[name="tire_size"]')).toHaveAttribute('required', '');
    await expect(mounting.locator('[name="preferred_date"]')).toHaveAttribute('required', '');
    await expectNoGlobalOverflow(page);
});

test('generic service request remains available outside the seasonal flow', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/oljeskift/foresporsel`);

    const form = page.getByTestId('generic-service-request');
    await expect(form).toBeVisible();
    await expect(page.getByTestId('seasonal-service-request')).toHaveCount(0);
    await form.locator('#booking-fulfilment').selectOption('mobile');
    await expect(form.locator('[name="address"]')).toBeVisible();
    await expect(form.locator('[name="address"]')).toHaveAttribute('required', '');
    await expect(form.locator('[name="additional_services[]"]')).toHaveCount(3);
    await expectNoGlobalOverflow(page);
});

test('generic request renders natural localized server errors with accessible focus', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = testInfo.project.name.includes('ru');
    await page.goto(`${prefix}/tjenester/oljeskift/foresporsel`);

    const form = page.getByTestId('generic-service-request');
    await expect(form).toBeVisible();
    await form.locator('[name="email"]').fill('invalid');
    await form.locator('[name="phone"]').fill('123');
    await form.evaluate((element) => {
        const formElement = element as HTMLFormElement;
        formElement.noValidate = true;
        formElement.requestSubmit();
    });

    await expect(page.getByTestId('request-errors')).toBeVisible();
    const name = field(page, 'name');
    const nameError = page.locator('#booking-name-error');
    await expect(nameError).toHaveText(russian ? 'Заполните поле «имя».' : 'Fyll ut navn.');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', /booking-name-error/);
    await expect(name).toBeFocused();

    const visibleErrors = (await form.locator('.et-form-error').allTextContents()).join(' ');
    expect(visibleErrors).not.toMatch(/\b(?:validation|[a-z][a-z0-9_]*)\.[a-z0-9_.]+\b/i);
    expect(visibleErrors).toContain(russian ? 'Укажите корректный адрес электронной почты.' : 'Oppgi en gyldig e-postadresse.');
    expect(visibleErrors).toContain(russian ? 'Необходимо согласиться на обработку персональных данных.' : 'Du må godta behandlingen av personopplysninger.');
    await expectNoGlobalOverflow(page);
    await form.screenshot({ path: testInfo.outputPath(`issue-28-service-validation-${testInfo.project.name}.png`) });
});

test('battery request enforces its contract in Bokmål, Russian QA and mobile layouts', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = testInfo.project.name.includes('ru');
    await page.goto(`${prefix}/tjenester/bilbatteri/foresporsel`);

    const form = page.getByTestId('generic-service-request');
    const registration = field(page, 'registration_number');
    const preferredDate = field(page, 'preferred_date');
    await expect(form).toBeVisible();
    await expect(registration).toHaveAttribute('required', '');
    await expect(preferredDate).toHaveAttribute('required', '');
    await expect(registration).toHaveAttribute('aria-describedby', /booking-registration-number-hint/);
    await expect(preferredDate).toHaveAttribute('aria-describedby', /booking-preferred-date-hint/);
    await expect(page.locator('#booking-preferred-date-hint')).toContainText(russian ? 'не подтверждённая запись' : 'ikke en bekreftet time');
    if (russian) await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

    await field(page, 'name').fill('Synthetic Battery Buyer');
    await field(page, 'email').fill('battery-browser@example.test');
    await field(page, 'phone').fill('939 58 270');
    await field(page, 'note').fill(russian ? 'Автомобиль плохо заводится утром.' : 'Bilen starter tregt om morgenen.');
    await field(page, 'consent').check();
    await form.evaluate((element) => {
        const formElement = element as HTMLFormElement;
        formElement.noValidate = true;
        formElement.requestSubmit();
    });

    await expect(page.getByTestId('request-errors')).toBeVisible();
    await expect(page.locator('#booking-registration-number-error')).toHaveText(russian ? 'Укажите госномер автомобиля.' : 'Oppgi registreringsnummeret til bilen.');
    await expect(registration).toHaveAttribute('aria-invalid', 'true');
    await expect(preferredDate).toHaveAttribute('aria-invalid', 'true');
    await expect(registration).toBeFocused();
    await expectNoGlobalOverflow(page);

    const future = new Date();
    future.setDate(future.getDate() + 10);
    await registration.fill('EV12345');
    await preferredDate.fill(future.toISOString().slice(0, 10));
    await form.getByTestId('generic-submit').click();
    await expect(page).toHaveURL(/\/foresporsel\/SR-[A-Z0-9]+\/takk$/);
});
