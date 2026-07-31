import { expect, test } from '@playwright/test';

test('buyer can use the private Russian storefront preview', async ({ page }) => {
    await page.goto('/ru');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.getByRole('heading', { level: 1, name: 'Шины и диски для вашего ритма жизни' })).toBeVisible();
    await page.getByRole('link', { name: 'Смотреть шины' }).click();
    await expect(page).toHaveURL(/\/ru\/dekk$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Шины' })).toBeVisible();
    await page.getByLabel('Ширина').fill('205');
    await page.getByRole('button', { name: 'Показать товары' }).click();
    await page.getByRole('link', { name: 'Открыть товар' }).click();
    await expect(page.getByTestId('cart-coming-soon')).toContainText('Добавление в корзину скоро появится');
    await page.goto('/ru/tjenester');
    await page.getByRole('link', { name: /Подробнее/ }).first().click();
    await expect(page).toHaveURL(/\/ru\/tjenester\//);
    await expect(page.locator('body')).not.toContainText('Norsk innhold');
});
