import { expect, test } from '@playwright/test';

test('checkout entry has a session-bound idempotency field and unknown confirmation is hidden', async ({ page }) => {
    await page.goto('/kasse');
    await expect(page).toHaveURL(/\/handlekurv$/);
    await expect(page.getByRole('alert')).toBeVisible();

    await page.goto('/dekk?width=205&aspect=55');
    await page.locator('[data-testid="catalog-product"] form[action*="/handlekurv"] button').first().click();
    await page.goto('/kasse');
    await expect(page.locator('input[name="idempotency_key"]')).toHaveAttribute('type', 'hidden');
    await expect(page.locator('input[name="idempotency_key"]')).toHaveValue(/[0-9a-f-]{36}/);
    await expect(page.getByLabel(/Navn|Имя/)).toBeVisible();
    await expect(page.getByLabel(/E-post|Эл. почта/)).toBeVisible();
    await expect(page.getByLabel(/E-post|Эл. почта/)).toHaveAttribute('type', 'email');
    await page.goto('/bestilling/ET-UNKNOWN-REFERENCE');
    await expect(page).toHaveURL(/bestilling\/ET-UNKNOWN-REFERENCE/);
    await expect(page.getByRole('heading', { name: /Vi finner ikke siden|Страница не найдена/ })).toBeVisible();
});

test('Russian checkout preview keeps its private route on mobile', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-ru-preview');
    await page.goto('/ru/dekk?width=205&aspect=55');
    await page.locator('[data-testid="catalog-product"] form[action*="/ru/handlekurv"] button').first().click();
    await page.goto('/ru/kasse');
    await expect(page).toHaveURL(/\/ru\/kasse$/);
    await expect(page.locator('input[name="idempotency_key"]')).toHaveValue(/[0-9a-f-]{36}/);
});
