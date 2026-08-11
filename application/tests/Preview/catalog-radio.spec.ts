import { expect, test, type Locator, type Page } from '@playwright/test';

async function activeForm(page: Page, mobile: boolean): Promise<Locator> {
    if (mobile) {
        await page.locator('[data-filter-open]').click();
        const form = page.locator('[data-filter-drawer] [data-catalog-form]');
        await expect(form).toBeVisible();
        return form;
    }
    const form = page.locator('.et-filters[data-catalog-form]');
    await expect(form).toBeVisible();
    return form;
}

async function expectSingleCheckedPerCopy(page: Page, name: string): Promise<void> {
    for (const form of await page.locator('[data-catalog-form]').all()) {
        await expect(form.locator(`input[type="radio"][name="${name}"]:checked`)).toHaveCount(1);
    }
}

const seasonCases = [
    { value: 'summer', count: 2 },
    { value: 'winter', count: 3 },
    { value: 'all', count: 1 },
];

for (const season of seasonCases) {
    test(`season radio ${season.value} synchronizes copies, URL and product set`, async ({ page }, testInfo) => {
        await page.goto('/catalog.html');
        const mobile = testInfo.project.name.includes('mobile');
        const form = await activeForm(page, mobile);
        await form.locator(`input[name="season"][value="${season.value}"]`).check();

        await expect(page).toHaveURL(new RegExp(`season=${season.value}`));
        await expect(page.locator('[data-product-card]:visible')).toHaveCount(season.count);
        await expectSingleCheckedPerCopy(page, 'season');
    });
}

for (const brand of ['NordicGrip', 'Roadline', 'Touring']) {
    test(`brand radio ${brand} leaves exactly one choice in every copy`, async ({ page }, testInfo) => {
        await page.goto('/catalog.html');
        const mobile = testInfo.project.name.includes('mobile');
        const form = await activeForm(page, mobile);
        await form.locator(`input[name="brand"][value="${brand}"]`).check();

        await expect(page).toHaveURL(new RegExp(`brand=${encodeURIComponent(brand)}`));
        await expect(page.locator('[data-product-card]:visible')).toHaveCount(1);
        await expectSingleCheckedPerCopy(page, 'brand');
    });
}

test('reset restores product set, URL and all duplicated controls', async ({ page }, testInfo) => {
    await page.goto('/catalog.html');
    const mobile = testInfo.project.name.includes('mobile');
    const form = await activeForm(page, mobile);
    await form.locator('input[name="season"][value="winter"]').check();
    await form.locator('input[name="stock"]').check();
    await expect(page).toHaveURL(/season=winter/);

    await form.locator('[data-reset-catalog]').click();
    await expect(page).toHaveURL(/\/catalog\.html$/);
    await expect(page.locator('[data-product-card]:visible')).toHaveCount(6);
    await expect(page.locator('input[name="season"]:checked')).toHaveCount(0);
    await expect(page.locator('input[name="stock"]:checked')).toHaveCount(0);
});
