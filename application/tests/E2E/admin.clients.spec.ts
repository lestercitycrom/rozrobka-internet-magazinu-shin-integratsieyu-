import { expect, test } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginOperations } from './helpers';

test('operations completes the client to linked request workflow', async ({ page }, testInfo) => {
    const suffix = testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
    const russian = testInfo.project.name.includes('390');
    const name = `E2E Client ${suffix}`;
    const updatedName = `${name} Updated`;
    const phone = testInfo.project.name.includes('320') ? '92111111' : (russian ? '92222222' : '92333333');

    await loginOperations(page);
    await ensureAdminLocale(page, russian ? 'ru' : 'nb');
    await page.goto('/admin/clients');
    await expect(page.getByRole('heading', { name: russian ? 'Клиенты' : 'Kunder' })).toBeVisible();
    await page.getByTestId('client-create').click();

    await page.locator('[name="name"]').fill(name);
    await page.locator('[name="phone"]').fill(phone);
    await page.locator('[name="email"]').fill(`client-${suffix}@example.test`);
    await page.locator('[name="district"]').fill(russian ? 'Тромсдален' : 'Tromsdalen');
    await page.locator('[name="address"]').fill('Syntheticvegen 59');
    await page.getByTestId('client-save').click();
    await expect(page).toHaveURL(/\/admin\/clients\/\d+$/);
    await expect(page.getByText(russian ? 'У этого клиента пока нет связанных заявок.' : 'Denne kunden har ingen koblede forespørsler ennå.')).toBeVisible();

    await page.goto('/admin/clients');
    await page.locator('[name="q"]').fill(phone);
    await page.getByRole('button', { name: russian ? 'Найти' : 'Søk' }).click();
    await page.locator('a:visible', { hasText: name }).first().click();
    await page.getByRole('link', { name: russian ? 'Редактировать клиента' : 'Rediger kunde' }).click();
    await page.locator('[name="name"]').fill(updatedName);
    await page.getByTestId('client-save').click();
    await expect(page.getByRole('heading', { name: updatedName })).toBeVisible();

    await page.getByTestId('client-create-request').click();
    await expect(page.locator('[name="name"]')).toHaveValue(updatedName);
    await expect(page.locator('[name="phone"]')).toHaveValue(phone);
    await expect(page.locator('[name="client_id"]')).not.toHaveValue('');
    await page.locator('[name="service_slug"]').selectOption('oljeskift');
    await page.locator('[name="booking_mode"]').selectOption('manual');
    await page.locator('[name="preferred_window"]').fill(russian ? 'Время уточнить по телефону' : 'Avklar tid på telefon');
    await page.getByTestId('request-save').click();
    await expect(page).toHaveURL(/\/admin\/service-requests\/\d+$/);
    const reference = (await page.getByRole('heading').filter({ hasText: /^SR-/ }).textContent())?.trim();
    expect(reference).toMatch(/^SR-/);

    await page.getByRole('link', { name: russian ? 'Открыть карточку клиента' : 'Åpne kundekort' }).click();
    await expect(page.getByRole('heading', { name: updatedName })).toBeVisible();
    await expect(page.getByRole('link', { name: reference! })).toBeVisible();
    await expectNoGlobalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`issue-59-client-flow-${suffix}.png`), fullPage: true });
});
