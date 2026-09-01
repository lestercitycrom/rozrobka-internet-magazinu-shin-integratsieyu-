import { expect, test } from '@playwright/test';
import { addCatalogProduct, localePrefix } from './helpers';

const tireSku = '000205550016';

test('checkout entry has a session-bound idempotency field and unknown confirmation is hidden', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(`${prefix}/kasse`);
    await expect(page).toHaveURL(new RegExp(`${prefix.replace('/', '\\/')}\/handlekurv$`));
    await expect(page.getByRole('alert')).toBeVisible();

    await addCatalogProduct(page, `${prefix}/dekk?width=205&aspect=55`, tireSku);
    await page.goto(`${prefix}/kasse`);
    await expect(page.getByTestId('checkout-form')).toBeVisible();
    await expect(page.locator('input[name="idempotency_key"]')).toHaveAttribute('type', 'hidden');
    await expect(page.locator('input[name="idempotency_key"]')).toHaveValue(/[0-9a-f-]{36}/);
    await expect(page.getByTestId('checkout-name')).toBeVisible();
    await expect(page.getByTestId('checkout-email')).toHaveAttribute('type', 'email');
    await expect(page.getByTestId('checkout-phone')).toBeVisible();
    await expect(page.getByTestId('checkout-vat-summary')).toBeVisible();
    await expect(page.getByTestId('checkout-merchant-identity')).toContainText('934 860 187 MVA');
    await expect(page.getByTestId('checkout-fitment-confirmation').locator('input')).not.toBeChecked();
    await expect(page.getByTestId('checkout-privacy').locator('input')).not.toBeChecked();
    await expect(page.getByTestId('checkout-terms').locator('input')).not.toBeChecked();
    await expect(page.getByTestId('checkout-submit')).toHaveText(
        prefix === '/ru'
            ? 'Заказать с обязательством оплаты'
            : 'Bestill med betalingsplikt',
    );

    await page.goto(`${prefix}/bestilling/ET-UNKNOWN-REFERENCE`);
    await expect(page.getByRole('heading', { name: /Vi finner ikke siden|Страница не найдена/ })).toBeVisible();
});

test('checkout renders natural localized server errors with accessible focus', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = testInfo.project.name.includes('ru');
    await addCatalogProduct(page, `${prefix}/dekk?width=205&aspect=55`, tireSku);
    await page.goto(`${prefix}/kasse`);

    const form = page.getByTestId('checkout-form');
    await expect(form).toBeVisible();
    await form.locator('[name="email"]').fill('invalid');
    await form.locator('[name="phone"]').fill('123');
    await form.evaluate((element) => {
        const formElement = element as HTMLFormElement;
        formElement.noValidate = true;
        formElement.requestSubmit();
    });

    await expect(page.getByTestId('request-errors')).toBeVisible();
    const name = page.getByTestId('checkout-name');
    const nameError = page.locator('#checkout-name-error');
    await expect(nameError).toHaveText(russian ? 'Заполните поле «имя».' : 'Fyll ut navn.');
    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toHaveAttribute('aria-describedby', /checkout-name-error/);
    await expect(name).toBeFocused();

    const visibleErrors = (await form.locator('.et-form-error').allTextContents()).join(' ');
    expect(visibleErrors).not.toMatch(/\b(?:validation|[a-z][a-z0-9_]*)\.[a-z0-9_.]+\b/i);
    expect(visibleErrors).toContain(russian ? 'Укажите корректный адрес электронной почты.' : 'Oppgi en gyldig e-postadresse.');
    expect(visibleErrors).toContain(russian ? 'Необходимо принять условия покупки.' : 'Du må godta kjøpsvilkårene.');
    await form.screenshot({ path: testInfo.outputPath(`issue-28-checkout-validation-${testInfo.project.name}.png`) });
});
