// Local synthetic cart only. Never point this mutation audit at staging.
import { chromium, expect } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const base = 'http://127.0.0.1:8045';
const out = path.resolve('storage/app/qa-ui-polish/commerce-after');
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true });
const results = [];
try {
    for (const width of [1440, 390, 320]) {
        const context = await browser.newContext({ viewport: { width, height: 844 },
            ...(width === 390 ? { recordVideo: { dir: out, size: { width, height: 844 } } } : {}) });
        await context.route('**/*', route => {
            const request = route.request(), url = new URL(request.url());
            if (url.origin !== base) return route.abort();
            if (!['GET', 'HEAD'].includes(request.method()) && url.pathname !== '/handlekurv') return route.abort();
            return route.continue();
        });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(String(error)));
        const pause = () => width === 390 ? page.waitForTimeout(1100) : Promise.resolve();
        const capture = async filename => {
            await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
            await page.waitForTimeout(250);
            await page.screenshot({ path: path.join(out, filename), fullPage: true });
        };
        const count = value => expect(page.getByTestId('header-cart')).toHaveAccessibleName(`Handlekurv: ${value}`);
        const line = sku => page.locator(`[data-testid="cart-line"][data-product-sku="${sku}"]`);
        const anchor = () => page.getByTestId('header-cart').evaluate(node => {
            const a = node.getBoundingClientRect(), b = node.querySelector('.et-cart__count').getBoundingClientRect();
            return { width: a.width, height: a.height, right: a.right - b.right, top: b.top - a.top };
        });
        try {
            await page.goto(base + '/');
            await count(0); await pause();
            const finder = page.getByTestId('quick-finder');
            await finder.scrollIntoViewIfNeeded();
            await finder.getByRole('tab', { name: 'Dekkdimensjon', exact: true }).click(); await pause();
            await page.getByTestId('finder-width').selectOption('205');
            await finder.getByRole('tab', { name: 'Registreringsnummer', exact: true }).click(); await pause();
            await finder.locator('input[name=registration]').fill('AB 12345');
            await finder.getByRole('tab', { name: 'Dekkdimensjon', exact: true }).focus();
            await page.keyboard.press('Enter');
            await expect(page.getByTestId('finder-width')).toHaveValue('205'); await pause();

            await page.goto(base + '/kontakt');
            const action = page.locator('.et-card .et-action-link').first();
            await action.scrollIntoViewIfNeeded(); await action.focus(); await pause();
            const style = await action.evaluate(node => ({ decoration: getComputedStyle(node).textDecorationLine,
                label: getComputedStyle(node.querySelector('.et-action-link__label')).textDecorationLine }));
            expect(style).toEqual({ decoration: 'none', label: 'underline' });

            // Existing fixtures have four ordinary units per SKU. Build nine
            // from4+3+2, then ten from4+4+2; never relax the stock or demo guard.
            for (const [sku, quantity] of [['000205550016', '4'], ['DEMO-WHEEL-18112', '3'], ['NDI-TEST-4765205551649102', null]]) {
                await page.goto(base + '/produkter/' + sku);
                if (quantity) await page.getByTestId('product-quantity').fill(quantity);
                await page.getByTestId('product-add-to-cart').click();
            }
            await page.goto(base + '/handlekurv');
            await count(9); const initial = await anchor(); await pause();
            await line('DEMO-WHEEL-18112').getByTestId('cart-line-quantity').fill('4');
            await line('DEMO-WHEEL-18112').getByTestId('cart-line-update').click();
            await count(10); expect(await anchor()).toEqual(initial); await pause();
            await page.reload(); await count(10);
            await page.goto(base + '/priser'); await page.goBack(); await count(10);
            await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' }));
            expect(await anchor()).toEqual(initial); await pause();
            // The existing mixed-demo guard must still prevent checkout.
            await expect(page.getByTestId('cart-checkout')).toHaveCount(0);
            await capture(`cart-mixed-guard-${width}.png`);
            await line('NDI-TEST-4765205551649102').getByTestId('cart-line-remove').click();
            await count(8);
            await capture(`cart-${width}.png`);
            await page.getByTestId('cart-checkout').click();
            await expect(page.getByTestId('checkout-form')).toBeVisible(); await pause();
            await page.getByTestId('checkout-name').fill('Local UI Fixture');
            await page.getByTestId('checkout-email').fill('ui-fixture@example.test');
            await pause();
            await capture(`checkout-${width}.png`);
            const typography = await page.locator('main h1, main h2, main h3, main strong, main span, main label, main button').evaluateAll(nodes => nodes.filter(n => n.getBoundingClientRect().height && Number(getComputedStyle(n).fontWeight) > 700).map(n => ({ selector: n.tagName + '.' + n.className, text: n.textContent.trim(), weight: getComputedStyle(n).fontWeight })));
            expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
            await page.goto(base + '/handlekurv');
            await line('DEMO-WHEEL-18112').getByTestId('cart-line-remove').click();
            await line('000205550016').getByTestId('cart-line-quantity').fill('1');
            await line('000205550016').getByTestId('cart-line-update').click(); await count(1); await pause();
            await line('000205550016').getByTestId('cart-line-remove').click(); await count(0);
            await expect(page.getByTestId('cart-empty')).toBeVisible();
            expect(await anchor()).toEqual(initial); await pause();
            await page.getByTestId('header-cart').locator('.et-cart__count').click();
            await expect(page).toHaveURL(base + '/handlekurv'); await count(0); await pause();
            expect(errors).toEqual([]);
            results.push({ width, passed: true, anchor: initial, typography, errors });
        } finally {
            await context.close();
            if (width === 390 && page.video()) await page.video().saveAs(path.join(out, 'interaction-390.webm'));
        }
    }
} finally {
    await browser.close();
    await writeFile(path.join(out, 'report.json'), JSON.stringify(results, null, 2) + '\n');
}
console.log(JSON.stringify({ out, results }, null, 2));
