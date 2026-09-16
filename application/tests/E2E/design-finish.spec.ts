import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

const demo = 'NDI-TEST-4765205551649102';
const prefixFor = (name: string) => name.includes('-ru-') ? '/ru' : '';

test.beforeEach(async ({ page, baseURL }) => {
    const origin = new URL(baseURL!).origin;
    if (!origin.startsWith('http://127.0.0.1:')) throw new Error('Local-only design journey');
    await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
});

test('product hierarchy, fixed sandbox quantity and cart-to-checkout presentation', async ({ page }, info) => {
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/produkter/${demo}`);
    await page.evaluate(() => document.fonts.ready);
    const quantity = page.getByTestId('product-quantity');
    await expect(quantity).toHaveValue('2');
    for (const attribute of ['min', 'max']) await expect(quantity).toHaveAttribute(attribute, '2');
    await expect(quantity).toHaveAttribute('readonly', '');
    const title = (await page.locator('h1').boundingBox())!;
    const media = (await page.locator('.et-product-gallery').boundingBox())!;
    if (page.viewportSize()!.width <= 900) expect(title.y + title.height).toBeLessThan(media.y);
    const add = page.getByTestId('product-add-to-cart');
    const buy = page.getByTestId('product-buy-now');
    for (const button of [add, buy]) {
        const box = (await button.boundingBox())!;
        expect(box.width).toBeGreaterThanOrEqual(150);
        expect(box.height).toBeGreaterThanOrEqual(48);
    }
    await expectNoGlobalOverflow(page);
    await add.click();
    await page.goto(`${prefix}/handlekurv`);
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-line-quantity')).toHaveValue('2');
    await expect(page.locator('.et-order-summary__total')).toContainText(/20[,.]00/);
    await expectNoGlobalOverflow(page);
    await page.getByTestId('cart-checkout').click();
    await expect(page.getByTestId('checkout-form')).toBeVisible();
    await expect(page.getByTestId('checkout-terms')).toBeVisible();
    await expect(page.getByTestId('checkout-fitment-confirmation')).toBeVisible();
    await expectNoGlobalOverflow(page);
    // Do not submit here: legal/order behavior is covered by existing PHP/E2E.
});

test('seasonal fields, selected state and keyboard focus have separate visual roles', async ({ page }, info) => {
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile`);
    const address = page.locator('#booking-address');
    const district = page.locator('label[for="booking-district"]');
    await expect(address).toBeVisible();
    const addressBox = (await address.boundingBox())!;
    const labelBox = (await district.boundingBox())!;
    expect(labelBox.y - addressBox.y - addressBox.height).toBeGreaterThanOrEqual(23);
    const selected = page.locator('.et-radio-card').filter({ has: page.locator('input[value="mobile"]') });
    await expect(selected).toHaveCSS('box-shadow', 'none');
    await expect(selected).toHaveCSS('outline-style', 'none');
    await expect(page.locator('[data-testid="seasonal-step-location"] .et-step-actions')).toHaveCSS('border-top-width', '0px');
    // Keyboard entry, not a mouse click, proves the focus-visible treatment.
    await page.keyboard.press('Tab');
    await address.focus();
    await expect(address).toHaveCSS('outline-style', 'solid');
    await expect(address).toHaveCSS('outline-width', '2px');
    await page.locator('input[value="workshop"][name="fulfilment"]').check();
    await expect(address).toBeHidden();
    await expect(address).not.toHaveAttribute('required', '');
    await page.locator('input[value="mobile"][name="fulfilment"]').check();
    await expect(address).toBeVisible();
    await address.fill('Design QA address');
    await page.locator('#booking-district').fill('Tromsø');
    await page.getByTestId('seasonal-step-location').locator('[data-booking-next]').click();
    await expect(page.getByTestId('seasonal-step-vehicle-storage')).toBeVisible();
    await page.getByTestId('seasonal-step-vehicle-storage').locator('[data-booking-back]').click();
    await expect(address).toHaveValue('Design QA address');
    await expectNoGlobalOverflow(page);
});

test('empty results retain canonical filters and offer a clear recovery; genuine 404 links work', async ({ page }, info) => {
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/dekk?width=125&aspect=35&rim=13`);
    const empty = page.getByTestId('catalog-empty');
    await expect(empty).toBeVisible();
    await expect(empty).toHaveAttribute('data-empty-reason', 'no-match');
    await expect(empty).toHaveCSS('border-top-width', '0px');
    await expect(page.getByTestId('active-filters')).toContainText('125');
    await expect(empty.locator('.et-page-actions')).toHaveCSS('justify-content', 'center');
    await expectNoGlobalOverflow(page);
    await empty.locator('[data-catalog-live-link]').click();
    await expect(page.getByTestId('catalog-results')).toBeVisible();
    expect(new URL(page.url()).searchParams.has('width')).toBe(false);
    const response = await page.goto(`${prefix}/design-finish-missing-page`);
    expect(response!.status()).toBe(404);
    await expect(page.locator('.et-error-page h1')).toBeVisible();
    await expectNoGlobalOverflow(page);
    await page.locator('.et-error-page .et-action-link').click();
    await expect(page.getByTestId('catalog-results')).toBeVisible();
});

test('services form complete rows in source order; reduced motion remains respected', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/tjenester');
    const cards = page.getByTestId('service-card');
    await expect(cards).toHaveCount(13);
    const boxes = await cards.evaluateAll(nodes => nodes.map(node => {
        const r = node.getBoundingClientRect();
        return { x: r.x, y: r.y, width: r.width, height: r.height };
    }));
    const columns = page.viewportSize()!.width > 1180 ? 3 : page.viewportSize()!.width > 700 ? 2 : 1;
    for (let index = 0; index < boxes.length; index++) {
        expect(boxes[index].width).toBeCloseTo(boxes[0].width, 0);
        if (index % columns) expect(boxes[index].y).toBeCloseTo(boxes[index - 1].y, 0);
    }
    await expectNoGlobalOverflow(page);
    await page.goto('/tjenester/mobilt-dekkskift/foresporsel');
    const duration = await page.locator('.et-step-panel.is-active').evaluate(node => parseFloat(getComputedStyle(node).animationDuration));
    expect(duration).toBeLessThanOrEqual(.001);
});
