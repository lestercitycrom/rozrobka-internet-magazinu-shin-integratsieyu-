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
    const rail = page.locator('.et-catalog-categories__rail');
    expect(await rail.evaluate(node => node.scrollHeight - node.clientHeight)).toBeLessThanOrEqual(0);
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

test('review R1: photo captions stay separate, readable and contained through hover and focus', async ({ page }, info) => {
    await page.goto(prefixFor(info.project.name) || '/');
    await page.evaluate(() => document.fonts.ready);
    const cards = page.locator('.et-home-service-collection .et-v3-service--feature');
    await expect(cards).toHaveCount(4);
    for (const card of await cards.all()) {
        const content = card.locator('.et-v3-service__content');
        const link = content.locator('a');
        for (const state of ['normal', 'hover', 'focus']) {
            if (state === 'hover') await link.hover();
            if (state === 'focus') {
                await page.keyboard.press('Tab');
                await link.focus();
                await expect(link).toBeFocused();
            }
            const metrics = await card.evaluate(element => {
                const rgb = (value: string) => value.match(/[\d.]+/g)!.map(Number);
                const luminance = (values: number[]) => values.slice(0, 3).map(value => {
                    const channel = value / 255;
                    return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
                }).reduce((sum, channel, index) => sum + channel * [.2126, .7152, .0722][index], 0);
                const background = rgb(getComputedStyle(element).backgroundColor);
                const bounds = element.getBoundingClientRect();
                // Read related bounds in one frame: keyboard focus can still be
                // smoothly scrolling the document between separate protocol calls.
                const media = element.querySelector(':scope > img, :scope > .et-v3-service__fallback')!.getBoundingClientRect();
                const content = element.querySelector('.et-v3-service__content')!.getBoundingClientRect();
                const foregrounds = [...element.querySelectorAll('.et-v3-service__content h3, .et-v3-service__content p, .et-v3-service__content a')];
                return {
                    mediaOverlap: media.bottom - content.top,
                    opaque: background.length === 3 || background[3] === 1,
                    contrasts: foregrounds.map(node => {
                        const text = luminance(rgb(getComputedStyle(node).color));
                        const surface = luminance(background);
                        return (Math.max(text, surface) + .05) / (Math.min(text, surface) + .05);
                    }),
                    contained: foregrounds.every(node => {
                        const rect = node.getBoundingClientRect();
                        return rect.left >= bounds.left && rect.right <= bounds.right + 1 && rect.bottom <= bounds.bottom + 1;
                    }),
                };
            });
            expect(metrics.mediaOverlap).toBeLessThanOrEqual(1);
            expect(metrics.opaque).toBe(true);
            expect(metrics.contained).toBe(true);
            metrics.contrasts.forEach(ratio => expect(ratio).toBeGreaterThanOrEqual(4.5));
        }
    }
    // Presentation-only stress fixture: long localized copy must grow the caption,
    // not overlap the photograph or conceal the action. Never changes stored data.
    const longCard = cards.nth(1);
    await longCard.locator('p').evaluate(node => { node.textContent = `${node.textContent} ${node.textContent} ${node.textContent}`; });
    const textOverlap = await longCard.evaluate(element => element.querySelector('p')!.getBoundingClientRect().bottom - element.querySelector('a')!.getBoundingClientRect().top);
    expect(textOverlap).toBeLessThanOrEqual(0);
    await expectNoGlobalOverflow(page);
});

test('review R2: price page has exactly one current navigation item in both languages', async ({ page }, info) => {
    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/priser`);
    const mobile = page.viewportSize()!.width < 768;
    if (mobile) await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();
    const navigation = page.getByTestId(mobile ? 'mobile-navigation' : 'desktop-navigation');
    const current = navigation.locator('[aria-current="page"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveAttribute('href', new RegExp(`${prefix}/priser$`));
    await expect(current).toHaveText(prefix ? 'Цены' : 'Priser');
    await expect(navigation.locator('summary[aria-current="page"], a[aria-current="page"][href$="/tjenester"]')).toHaveCount(0);
    if (mobile) {
        const label = current.locator(':scope > .et-mobile-menu__label');
        const currentCue = async () => {
            await expect(current).toHaveCSS('text-decoration-line', 'none');
            await expect(label).toHaveCSS('text-decoration-line', 'underline');
            expect(await current.evaluate(node => ({
                glyph: getComputedStyle(node, '::after').content.replace(/["']/g, ''),
                decoration: getComputedStyle(node, '::after').textDecorationLine,
            }))).toEqual({ glyph: '→', decoration: 'none' });
        };
        // Current-page semantics must also have a visible, non-colour-only cue.
        await currentCue();
        expect((await current.boundingBox())!.height).toBeGreaterThanOrEqual(44);
        expect(await label.getAttribute('tabindex')).toBeNull();
        await expect(label).not.toBeFocused();
        const currentColor = await current.evaluate(node => getComputedStyle(node).color);
        const servicesColor = await navigation.locator('summary').evaluate(node => getComputedStyle(node).color);
        expect(currentColor).not.toBe(servicesColor);
        await page.keyboard.press('Tab');
        await current.focus();
        await expect(current).toBeFocused();
        await expect(label).not.toBeFocused();
        await expect(current).toHaveCSS('outline-style', 'solid');
        expect(parseFloat(await current.evaluate(node => getComputedStyle(node).outlineWidth))).toBeGreaterThanOrEqual(2);
        await currentCue();
        await expectNoGlobalOverflow(page);
    }
});

test('review R2b: mobile current grouping underlines only its label and preserves disclosure cues', async ({ page }, info) => {
    test.skip(page.viewportSize()!.width >= 768, 'Mobile navigation contract only');

    const prefix = prefixFor(info.project.name);
    await page.goto(`${prefix}/tjenester`);
    await page.getByRole('button', { name: /Åpne meny|Открыть меню/ }).click();

    const navigation = page.getByTestId('mobile-navigation');
    const group = navigation.locator('.et-mobile-menu__group');
    const summary = group.locator('summary[aria-current="page"]');
    const label = summary.locator(':scope > .et-mobile-menu__label');
    await expect(summary).toHaveCount(1);
    await expect(label).toHaveText(prefix ? 'Услуги' : 'Tjenester');
    expect(await label.getAttribute('tabindex')).toBeNull();
    await expect(summary).toHaveCSS('text-decoration-line', 'none');
    await expect(label).toHaveCSS('text-decoration-line', 'underline');
    await expect(navigation.locator(':scope > a:not([aria-current]) > .et-mobile-menu__label').first()).toHaveCSS('text-decoration-line', 'none');

    const disclosure = async () => summary.evaluate(node => ({
        glyph: getComputedStyle(node, '::after').content.replace(/["']/g, ''),
        decoration: getComputedStyle(node, '::after').textDecorationLine,
    }));
    expect(await disclosure()).toEqual({ glyph: '+', decoration: 'none' });

    const target = (await summary.boundingBox())!;
    expect(target.height).toBeGreaterThanOrEqual(50);
    await page.keyboard.press('Tab');
    await summary.focus();
    await expect(summary).toBeFocused();
    await expect(label).not.toBeFocused();
    await expect(summary).toHaveCSS('outline-style', 'solid');
    expect(parseFloat(await summary.evaluate(node => getComputedStyle(node).outlineWidth))).toBeGreaterThanOrEqual(2);
    await expect(summary).toHaveCSS('text-decoration-line', 'none');
    await expect(label).toHaveCSS('text-decoration-line', 'underline');
    expect(await disclosure()).toEqual({ glyph: '+', decoration: 'none' });
    await page.keyboard.press('Enter');
    await expect(group).toHaveAttribute('open', '');
    await expect(summary).toBeFocused();
    await expect(summary).toHaveCSS('outline-style', 'solid');
    expect(parseFloat(await summary.evaluate(node => getComputedStyle(node).outlineWidth))).toBeGreaterThanOrEqual(2);
    await expect(summary).toHaveCSS('text-decoration-line', 'none');
    await expect(label).toHaveCSS('text-decoration-line', 'underline');
    expect(await disclosure()).toEqual({ glyph: '−', decoration: 'none' });
    await expectNoGlobalOverflow(page);
});

test('review R3: footer contact values remain intact and stack on narrow screens', async ({ page }, info) => {
    await page.goto(`${prefixFor(info.project.name)}/priser`);
    const footer = page.locator('.et-footer__contact');
    const email = footer.locator('a[href^="mailto:"]');
    const phone = footer.locator('a[href^="tel:"]');
    await expect(email).toHaveText('max.easytire@gmail.com');
    await expect(email).toHaveAttribute('href', 'mailto:max.easytire@gmail.com');
    await expect(phone).toHaveAttribute('href', /^tel:\+?4793958270$/);
    if (page.viewportSize()!.width <= 390) {
        const emailBox = (await email.boundingBox())!;
        const phoneBox = (await phone.boundingBox())!;
        expect(emailBox.y).toBeGreaterThanOrEqual(phoneBox.y + phoneBox.height);
        const type = await email.evaluate(node => {
            const range = document.createRange();
            range.selectNodeContents(node);
            return {
                lines: new Set(Array.from(range.getClientRects(), rect => Math.round(rect.top))).size,
                fontSize: parseFloat(getComputedStyle(node).fontSize),
                textOverflow: getComputedStyle(node).textOverflow,
                fits: range.getBoundingClientRect().width <= node.clientWidth,
            };
        });
        expect(type.lines).toBe(1);
        expect(type.fontSize).toBeGreaterThanOrEqual(14);
        expect(type.textOverflow).not.toBe('ellipsis');
        expect(type.fits).toBe(true);
    }
    await expectNoGlobalOverflow(page);
});
