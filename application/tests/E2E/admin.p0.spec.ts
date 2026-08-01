import { expect, test } from '@playwright/test';

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';

async function expectNoPageOverflow(page: import('@playwright/test').Page) {
    const dimensions = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));

    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}

test('mobile admin content and add-on screens render in NB and RU without global overflow', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel(/e-postadresse/i).fill(adminEmail);
    await page.getByLabel(/passord/i).fill(adminPassword);
    await page.getByRole('button', { name: /logg inn/i }).click();

    await page.getByTestId('switch-language-nb').click();

    await page.goto('/admin/catalog');
    await page.getByRole('link', { name: 'Demo 205/55 R16', exact: true }).click();
    const currentMedia = page.getByTestId('current-product-media-preview');
    await expect(currentMedia).toHaveAttribute('src', /e2e-demo-product\.png/);
    await expect(currentMedia).toBeVisible();
    await expect(page.getByTestId('product-media-chooser')).toBeVisible();
    await expect(page.getByRole('radio', { name: /Demo Tyres.*Road Demo.*SKU 000205550016/ })).toBeChecked();
    await expectNoPageOverflow(page);

    await page.goto('/admin/content-pages');
    await expect(page.getByRole('heading', { name: 'Innholdssider', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ny innholdsside', exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/admin\.content\.|pagination\./);
    await expectNoPageOverflow(page);

    await page.getByRole('link', { name: 'Ny innholdsside', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Ny innholdsside', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'Status', exact: true })).toHaveValue('draft');
    const nbSlug = page.getByRole('textbox', { name: 'Nettadresse (slug)', exact: true });
    await nbSlug.fill('ulagret-nb');
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Du har ulagrede endringer. Vil du bytte språk og forkaste dem?');
        await dialog.dismiss();
    });
    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
    await expect(nbSlug).toHaveValue('ulagret-nb');

    page.once('dialog', async (dialog) => dialog.accept());
    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    const ruSlug = page.getByRole('textbox', { name: 'Адрес страницы (slug)', exact: true });
    await ruSlug.fill('nesohraneno-ru');
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Есть несохранённые изменения. Сменить язык и потерять их?');
        await dialog.dismiss();
    });
    await page.getByTestId('switch-language-nb').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(ruSlug).toHaveValue('nesohraneno-ru');

    page.once('dialog', async (dialog) => dialog.accept());
    await page.getByTestId('switch-language-nb').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'nb');

    await page.goto('/admin/service-addons');
    await expect(page.getByRole('heading', { name: 'Tilleggstjenester', exact: true })).toBeVisible();
    await expectNoPageOverflow(page);
    await page.getByRole('link', { name: 'Rediger', exact: true }).first().click();
    await expect(page.getByRole('textbox', { name: /^Pris \(NOK\)/ })).toBeVisible();

    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    await expect(page.getByRole('textbox', { name: /^Цена \(NOK\)/ })).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/admin\.content\.|payment_statuses\.|pagination\./);

    await page.goto('/admin/content-pages/create');
    await expect(page.getByRole('heading', { name: 'Новая информационная страница', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'Статус', exact: true })).toHaveValue('draft');
    await expectNoPageOverflow(page);
});
