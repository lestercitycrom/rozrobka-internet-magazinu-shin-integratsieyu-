import { execFileSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import { createServer } from 'node:net';
import path from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { expect, test, type Page } from '@playwright/test';
import { cartLine, expectNoGlobalOverflow, productCard } from './helpers';

const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const e2eDatabase = process.env.E2E_DB_DATABASE ?? path.resolve(process.cwd(), 'database', 'issue-19-e2e.sqlite');
const phpTempDirectory = (process.env.PLAYWRIGHT_PHP_TEMP_DIR ?? path.resolve(process.cwd(), 'storage', 'framework', 'playwright-tmp')).replace(/\\/g, '/');
const realSku = '000205550016';
const wheelSku = 'DEMO-WHEEL-18112';
const sandboxSku = 'NDI-TEST-4765205551649102';

type Locale = 'nb' | 'ru';
type StockState = 'fresh' | 'zero' | 'stale' | 'missing';

const copy = {
    nb: {
        zero: 'Ingen registrert lagerbeholdning.',
        stale: 'Lagerstatus oppdateres',
        staleDetail: 'Sist mottatte lagerstatus er for gammel til å brukes ved bestilling.',
        missing: 'Lagerstatus ikke mottatt',
        missingDetail: 'NDI har ikke levert lagerstatus for denne varen ennå.',
        blocked: 'Varen kan ikke legges i handlekurven før pris og lagerstatus er bekreftet.',
        contact: 'Spør om pris og tilgjengelighet',
        priceUnavailable: 'Pris er ikke klar for netthandel',
        demoDisclaimer: 'uten reell levering',
    },
    ru: {
        zero: 'Нет зарегистрированного остатка.',
        stale: 'Остатки обновляются',
        staleDetail: 'Последние данные об остатках устарели и не используются для оформления.',
        missing: 'Остаток ещё не получен',
        missingDetail: 'NDI ещё не передал складской остаток для этого товара.',
        blocked: 'Товар нельзя добавить в корзину, пока не подтверждены цена и актуальный остаток.',
        contact: 'Уточнить цену и наличие',
        priceUnavailable: 'Цена для интернет-заказа пока не настроена',
        demoDisclaimer: 'без реальной поставки',
    },
} as const;

function applicationEnv(overrides: NodeJS.ProcessEnv = {}): NodeJS.ProcessEnv {
    return {
        ...process.env,
        APP_DEBUG: 'false',
        APP_ENV: 'testing',
        CACHE_STORE: 'array',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: e2eDatabase,
        DB_URL: '',
        NDI_INQUIRY_ENABLED: 'false',
        NDI_ORDER_ENABLED: 'false',
        NDI_SANDBOX_CATALOG_ENABLED: 'true',
        NDI_SANDBOX_INQUIRY_ENABLED: 'false',
        NDI_SANDBOX_ORDER_ENABLED: 'false',
        PAYMENTS_ENABLED: 'false',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
        STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        ...overrides,
    };
}

function runPhp(arguments_: string[], environment: NodeJS.ProcessEnv = {}): string {
    return execFileSync(phpBinary, [
        '-d', `sys_temp_dir=${phpTempDirectory}`,
        '-d', `upload_tmp_dir=${phpTempDirectory}`,
        ...arguments_,
    ], {
        cwd: process.cwd(),
        env: applicationEnv(environment),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });
}

function setStockState(sku: string, warehouseCode: string, state: StockState, freshQuantity: number): void {
    runPhp(['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $sku = App\Modules\Catalog\Models\SupplierSku::query()
            ->where('external_sku', getenv('ISSUE19_SKU'))
            ->sole();
        $stockQuery = App\Modules\Catalog\Models\CurrentStock::query()
            ->where('supplier_sku_id', $sku->id);
        $state = getenv('ISSUE19_STATE');

        if ($state === 'missing') {
            $stockQuery->delete();
            exit(0);
        }

        $warehouse = App\Modules\Catalog\Models\Warehouse::query()
            ->where('supplier_id', $sku->supplier_id)
            ->where('code', getenv('ISSUE19_WAREHOUSE'))
            ->sole();
        $artifactId = (clone $stockQuery)->value('source_artifact_id')
            ?? Illuminate\Support\Facades\DB::table('source_artifacts')
                ->where('supplier_id', $sku->supplier_id)
                ->orderBy('received_at')
                ->value('id');

        $stockQuery->delete();
        App\Modules\Catalog\Models\CurrentStock::query()->create([
            'supplier_sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $state === 'zero' ? 0 : (int) getenv('ISSUE19_FRESH_QUANTITY'),
            'observed_at' => $state === 'stale'
                ? now()->subMinutes((int) config('storefront.stock_max_age_minutes') + 1)
                : now(),
            'source_artifact_id' => $artifactId,
        ]);
    `], {
        ISSUE19_SKU: sku,
        ISSUE19_WAREHOUSE: warehouseCode,
        ISSUE19_STATE: state,
        ISSUE19_FRESH_QUANTITY: String(freshQuantity),
    });
}

function refreshSandbox(): void {
    runPhp(['artisan', 'ndi:sandbox-catalog']);
}

function removeSellingPrice(sku: string): void {
    runPhp(['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $sku = App\Modules\Catalog\Models\SupplierSku::query()
            ->where('external_sku', getenv('ISSUE19_SKU'))
            ->sole();
        App\Modules\Catalog\Models\SkuPrice::query()
            ->where('supplier_sku_id', $sku->id)
            ->delete();
    `], { ISSUE19_SKU: sku });
}

function localeFor(projectName: string): Locale {
    return projectName.includes('-ru-') ? 'ru' : 'nb';
}

function prefixFor(locale: Locale): string {
    return locale === 'ru' ? '/ru' : '';
}

async function reserveAvailablePort(): Promise<number> {
    const probe = createServer();
    await new Promise<void>((resolve, reject) => {
        probe.once('error', reject);
        probe.listen(0, '127.0.0.1', resolve);
    });

    const address = probe.address();
    const port = typeof address === 'object' && address !== null ? address.port : 0;
    await new Promise<void>((resolve, reject) => {
        probe.close((error) => error ? reject(error) : resolve());
    });

    if (port === 0) throw new Error('Could not reserve a production proof port.');

    return port;
}

async function directCartPostIsRejected(page: Page, prefix: string, sku: string, quantity: number, message: string): Promise<void> {
    await page.goto(`${prefix}/`);
    const csrf = await page.locator('input[name="_token"]').first().inputValue();
    const response = await page.context().request.post(`${prefix}/handlekurv`, {
        form: { _token: csrf, sku, quantity: String(quantity), intent: 'cart' },
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    expect(response.status()).toBe(302);
    await page.goto(`${prefix}/handlekurv`);
    await expect(page.getByRole('alert')).toContainText(message);
    await expect(page.getByTestId('cart-empty')).toBeVisible();
    await expect(cartLine(page, sku)).toHaveCount(0);
}

test('real items remain browseable with honest stock states while only fresh positive stock is buyable', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo.project.name);
    const prefix = prefixFor(locale);
    const messages = copy[locale];

    setStockState(realSku, 'demo', 'fresh', 4);
    await page.goto(`${prefix}/dekk?q=${realSku}&in_stock=1`);
    await expect(productCard(page, realSku)).toBeVisible();
    await expect(productCard(page, realSku).getByTestId('catalog-add-to-cart')).toBeVisible();

    await page.goto(`${prefix}/produkter/${realSku}`);
    await expect(page.getByTestId('product-cart-form')).toBeVisible();
    await expect(page.locator('.et-product-price')).toContainText('1 499,00 kr');
    await expect(page.getByTestId('product-price-unavailable')).toHaveCount(0);
    await expect(page.getByTestId('product-availability-contact')).toHaveCount(0);
    await expectNoGlobalOverflow(page);

    for (const state of [
        { name: 'stale' as const, label: messages.stale, detail: messages.staleDetail },
        { name: 'zero' as const, label: messages.zero, detail: messages.zero },
        { name: 'missing' as const, label: messages.missing, detail: messages.missingDetail },
    ]) {
        await test.step(state.name, async () => {
            setStockState(realSku, 'demo', state.name, 4);

            await page.goto(`${prefix}/dekk?q=${realSku}`);
            const card = productCard(page, realSku);
            await expect(card).toBeVisible();
            await expect(card).toContainText(state.label);
            await expect(card.getByTestId('catalog-add-to-cart')).toHaveCount(0);

            await page.goto(`${prefix}/produkter/${realSku}`);
            await expect(page.locator('.et-stock-line')).toContainText(state.detail);
            await expect(page.getByTestId('product-cart-form')).toHaveCount(0);
            await expect(page.getByTestId('product-add-to-cart')).toHaveCount(0);
            await expect(page.getByTestId('product-buy-now')).toHaveCount(0);
            const contact = page.getByTestId('product-availability-contact');
            await expect(contact).toBeVisible();
            await expect(contact).toHaveAccessibleName(messages.contact);
            expect(new URL((await contact.getAttribute('href'))!, page.url()).pathname).toBe(`${prefix}/kontakt`);
            await expect(page.getByTestId('product-price-unavailable')).toHaveCount(0);
            await expect(page.locator('.et-product-price')).toContainText('1 499,00 kr');
            await expectNoGlobalOverflow(page);

            await page.goto(`${prefix}/dekk?q=${realSku}&in_stock=1`);
            await expect(productCard(page, realSku)).toHaveCount(0);

            await directCartPostIsRejected(page, prefix, realSku, 1, messages.blocked);
        });
    }
});

test('an unavailable wheel without a safe price shows a localized price placeholder and working contact action', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo.project.name);
    const prefix = prefixFor(locale);
    const messages = copy[locale];
    await page.route(/^https:\/\/www\.google\.com\/maps(?:\?|$)/, (route) => route.fulfill({
        contentType: 'text/html; charset=utf-8',
        body: '<!doctype html><html><body>Google Maps test fixture</body></html>',
    }));

    setStockState(wheelSku, 'demo', 'fresh', 4);
    removeSellingPrice(wheelSku);
    await page.goto(`${prefix}/produkter/${wheelSku}`);

    await expect(page.getByTestId('product-price-unavailable')).toHaveText(messages.priceUnavailable);
    await expect(page.getByTestId('product-cart-form')).toHaveCount(0);
    const contact = page.getByTestId('product-availability-contact');
    await expect(contact).toBeVisible();
    await expect(contact).toHaveAccessibleName(messages.contact);
    expect(new URL((await contact.getAttribute('href'))!, page.url()).pathname).toBe(`${prefix}/kontakt`);
    const productSchema = (await page.locator('script[type="application/ld+json"]').allTextContents())
        .find((schema) => schema.includes(wheelSku));
    expect(productSchema).toContain(wheelSku);
    expect(productSchema).not.toContain('"offers"');
    await expectNoGlobalOverflow(page);
    await directCartPostIsRejected(page, prefix, wheelSku, 1, messages.blocked);

    await page.goto(`${prefix}/produkter/${wheelSku}`);
    const restoredContact = page.getByTestId('product-availability-contact');
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    let reachedContact = false;
    for (let index = 0; index < 50; index += 1) {
        await page.keyboard.press('Tab');
        reachedContact = await restoredContact.evaluate((element) => document.activeElement === element);
        if (reachedContact) break;
    }
    expect(reachedContact).toBe(true);
    await expect(restoredContact).toBeFocused();
    const focusStyle = await restoredContact.evaluate((element) => {
        const style = getComputedStyle(element);
        return { style: style.outlineStyle, width: Number.parseFloat(style.outlineWidth), offset: Number.parseFloat(style.outlineOffset) };
    });
    expect(focusStyle.style).not.toBe('none');
    expect(focusStyle.width).toBeGreaterThanOrEqual(2);
    expect(focusStyle.offset).toBeGreaterThanOrEqual(2);
    await restoredContact.press('Enter');
    await expect.poll(() => new URL(page.url()).pathname).toBe(`${prefix}/kontakt`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(locale === 'ru' ? 'Свяжитесь с EasyTire.' : 'Snakk med EasyTire.');
    await expectNoGlobalOverflow(page);
});

test('sandbox refresh restores the exact two-item purchase and rejects a forged quantity', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo.project.name);
    const prefix = prefixFor(locale);
    const messages = copy[locale];

    setStockState(sandboxSku, 'sandbox', 'stale', 2);
    await page.goto(`${prefix}/produkter/${sandboxSku}`);
    await expect(page.locator('.et-stock-line')).toContainText(messages.staleDetail);
    await expect(page.getByTestId('product-cart-form')).toHaveCount(0);
    await expect(page.getByTestId('product-availability-contact')).toHaveCount(0);

    refreshSandbox();
    await page.reload();
    const quantity = page.getByTestId('product-quantity');
    await expect(quantity).toHaveValue('2');
    await expect(quantity).toHaveAttribute('readonly', '');
    await expect(quantity).toHaveAttribute('min', '2');
    await expect(quantity).toHaveAttribute('max', '2');
    await expect(page.locator('.et-product-price')).toContainText('10,00 kr');
    await expect(page.getByTestId('integration-test-quantity-note')).toContainText(messages.demoDisclaimer);
    await expect(page.getByTestId('product-availability-contact')).toHaveCount(0);
    await expectNoGlobalOverflow(page);

    await directCartPostIsRejected(page, prefix, sandboxSku, 1, messages.blocked);

    await page.goto(`${prefix}/produkter/${sandboxSku}`);
    await page.getByTestId('product-add-to-cart').click();
    await page.goto(`${prefix}/handlekurv`);
    const line = cartLine(page, sandboxSku);
    await expect(line).toBeVisible();
    await expect(line.getByTestId('cart-line-quantity')).toHaveValue('2');
    await expect(line.getByTestId('cart-line-quantity')).toHaveAttribute('readonly', '');
    await expect(line.locator('.et-cart-line-total')).toContainText('20,00 kr');
    await expect(page.getByTestId('cart-checkout')).toBeVisible();
});

test('a production HTTP boot never exposes the sandbox fixture', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'issue-19-nb-desktop', 'One production process proof is sufficient.');

    refreshSandbox();
    const productionPort = process.env.ISSUE19_PRODUCTION_PORT
        ? Number(process.env.ISSUE19_PRODUCTION_PORT)
        : await reserveAvailablePort();
    const productionURL = `http://127.0.0.1:${productionPort}`;
    let stderr = '';
    const server = spawn(phpBinary, [
        '-d', `sys_temp_dir=${phpTempDirectory}`,
        '-d', `upload_tmp_dir=${phpTempDirectory}`,
        '-S', `127.0.0.1:${productionPort}`,
        '-t', 'public',
        'tests/Audit/server.php',
    ], {
        cwd: process.cwd(),
        env: applicationEnv({ APP_ENV: 'production', APP_URL: productionURL }),
        windowsHide: true,
        stdio: ['ignore', 'ignore', 'pipe'],
    });
    const serverExited = once(server, 'exit').catch(() => []);
    server.stderr.on('data', (chunk: Buffer) => {
        stderr = `${stderr}${chunk.toString('utf8')}`.slice(-8_000);
    });

    try {
        let ready = false;
        for (let attempt = 0; attempt < 60; attempt += 1) {
            if (server.exitCode !== null) break;
            try {
                const response = await fetch(`${productionURL}/dekk?q=${sandboxSku}`);
                await response.arrayBuffer();
                if (response.ok) {
                    ready = true;
                    break;
                }
            } catch {
                // The production PHP process is still starting.
            }
            await delay(100);
        }
        expect(ready, `Production server did not become ready.\n${stderr}`).toBe(true);
        expect(server.exitCode, `Production server exited after readiness.\n${stderr}`).toBeNull();

        await page.goto(`${productionURL}/dekk?q=${sandboxSku}`, { waitUntil: 'domcontentloaded' });
        await expect(productCard(page, sandboxSku)).toHaveCount(0);
        await expect(page.getByTestId('catalog-empty')).toBeVisible();

        const response = await page.goto(`${productionURL}/produkter/${sandboxSku}`);
        expect(response?.status()).toBe(404);
    } finally {
        if (server.exitCode === null) server.kill();
        await Promise.race([serverExited, delay(2_000)]);
    }
});
