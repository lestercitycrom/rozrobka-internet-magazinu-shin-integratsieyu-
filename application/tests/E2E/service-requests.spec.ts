import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isRussianProject, localePrefix } from './helpers';

const field = (page: import('@playwright/test').Page, name: string) => page.locator(`[name="${name}"]`);

async function nextFrom(panel: import('@playwright/test').Locator): Promise<void> {
    await panel.locator('[data-booking-next]').click();
}

async function backFrom(panel: import('@playwright/test').Locator): Promise<void> {
    await panel.locator('[data-booking-back]').click();
}

test('seasonal request maps five labels to five production panels and preserves entered values', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = isRussianProject(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThan(10);
    await expect(page.getByTestId('seasonal-step-location').locator('h2')).not.toBeFocused();

    const form = page.getByTestId('seasonal-service-request');
    await expect(form).toBeVisible();
    await expect(page.getByTestId('generic-service-request')).toHaveCount(0);
    await expect(page.getByTestId('seasonal-stepper').locator('[data-booking-step]')).toHaveCount(5);
    await expect(page.getByTestId('seasonal-stepper')).toContainText(russian ? 'Место' : 'Sted');
    await expect(page.getByTestId('seasonal-stepper')).toContainText(russian ? 'Автомобиль и хранение' : 'Bil og lagring');
    await expect(page.getByTestId('seasonal-stepper')).toContainText(russian ? 'Проверка' : 'Kontroller');

    const location = page.getByTestId('seasonal-step-location');
    const vehicleStorage = page.getByTestId('seasonal-step-vehicle-storage');
    const time = page.getByTestId('seasonal-step-time');
    const contact = page.getByTestId('seasonal-step-contact');
    const review = page.getByTestId('seasonal-step-review');

    await expect(location).toBeVisible();
    await form.locator('input[name="fulfilment"][value="mobile"]').check();
    await expect(field(page, 'address')).toBeVisible();
    await expect(field(page, 'address')).toHaveAttribute('required', '');

    await nextFrom(location);
    await expect(location).toBeVisible();
    await field(page, 'address').fill('Stakkevollvegen 33, Tromsø');
    await nextFrom(location);
    await expect(vehicleStorage).toBeVisible();

    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'registration_number').fill('EV12345');
    await field(page, 'tire_size').fill('255/45 R19');
    await field(page, 'dekkhotell').check();
    await nextFrom(vehicleStorage);
    await expect(time).toBeVisible();

    await field(page, 'preferred_date').fill('2026-10-15');
    await field(page, 'preferred_window').fill('14:00–17:00');
    await backFrom(time);
    await expect(vehicleStorage).toBeVisible();
    await expect(field(page, 'vehicle')).toHaveValue('Tesla Model Y');
    await expect(field(page, 'registration_number')).toHaveValue('EV12345');
    await expect(field(page, 'tire_size')).toHaveValue('255/45 R19');
    await expect(field(page, 'dekkhotell')).toBeChecked();
    await nextFrom(vehicleStorage);
    await expect(field(page, 'preferred_date')).toHaveValue('2026-10-15');
    await expect(field(page, 'preferred_window')).toHaveValue('14:00–17:00');
    await nextFrom(time);

    await field(page, 'name').fill('E2E Kunde');
    await field(page, 'email').fill('kunde@example.test');
    await field(page, 'phone').fill('+47 900 00 000');
    await field(page, 'note').fill('Ring før ankomst');
    await nextFrom(contact);
    await expect(review).toBeVisible();
    await expect(form.locator('[data-summary-field="vehicle"]')).toHaveText('Tesla Model Y');
    await expect(form.locator('[data-summary-field="email"]')).toHaveText('kunde@example.test');

    await backFrom(review);
    await expect(contact).toBeVisible();
    await expect(field(page, 'name')).toHaveValue('E2E Kunde');
    await expect(field(page, 'email')).toHaveValue('kunde@example.test');
    await expect(field(page, 'phone')).toHaveValue('+47 900 00 000');
    await expect(field(page, 'note')).toHaveValue('Ring før ankomst');
    await expectNoGlobalOverflow(page);
});



test('seasonal server validation returns to the first panel and preserves old values', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel`);

    const form = page.getByTestId('seasonal-service-request');
    await form.locator('input[name="fulfilment"][value="mobile"]').check();
    await field(page, 'address').fill('Stakkevollvegen 33, Tromsø');
    await nextFrom(page.getByTestId('seasonal-step-location'));
    await field(page, 'vehicle').fill('Tesla Model Y');
    await field(page, 'registration_number').fill('EV12345');
    await field(page, 'tire_size').fill('255/45 R19');
    await nextFrom(page.getByTestId('seasonal-step-vehicle-storage'));
    await field(page, 'preferred_date').fill('2026-10-15');
    await field(page, 'preferred_window').fill('14:00–17:00');
    await nextFrom(page.getByTestId('seasonal-step-time'));

    await form.evaluate((element) => {
        const formElement = element as HTMLFormElement;
        formElement.noValidate = true;
        formElement.requestSubmit();
    });

    await expect(page.getByTestId('request-errors')).toBeVisible();
    await expect(page.getByTestId('seasonal-step-location')).toBeVisible();
    await expect(page.getByTestId('seasonal-step-vehicle-storage')).not.toBeVisible();
    await expect(field(page, 'address')).toHaveValue('Stakkevollvegen 33, Tromsø');
    await expect(field(page, 'vehicle')).toHaveValue('Tesla Model Y');
    await expect(field(page, 'registration_number')).toHaveValue('EV12345');
    await expect(field(page, 'tire_size')).toHaveValue('255/45 R19');
});

test('generic service request stays a simple current-contract form in NB and RU', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/tjenester/dekkhotell/foresporsel`);

    const form = page.getByTestId('generic-service-request');
    await expect(form).toBeVisible();
    await expect(page.getByTestId('seasonal-service-request')).toHaveCount(0);
    await expect(page.getByTestId('seasonal-stepper')).toHaveCount(0);
    await expect(form.locator('input[name="dekkhotell"]')).toHaveCount(0);
    await expect(form.locator('input[name="service_slug"]')).toHaveValue('dekkhotell');
    await expect(form.locator('#booking-fulfilment')).toBeVisible();

    await form.locator('#booking-fulfilment').selectOption('mobile');
    await expect(form.locator('[name="address"]')).toBeVisible();
    await expect(form.locator('[name="address"]')).toHaveAttribute('required', '');
    await form.locator('[name="address"]').fill('Stakkevollvegen 33, Tromsø');
    await form.locator('[name="vehicle"]').fill('Volvo XC60');
    await form.locator('[name="name"]').fill('Generic Kunde');
    await form.locator('[name="email"]').fill('generic@example.test');
    await form.locator('[name="phone"]').fill('+47 911 11 111');
    await expectNoGlobalOverflow(page);
});
