import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const serverPort = new URL(baseURL).port || '8000';
const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';
const e2eDatabase = process.env.E2E_DB_DATABASE ?? `${process.cwd()}/database/e2e.sqlite`;
const e2eDatabaseForPhp = e2eDatabase.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const buyerSpecs = /(buyer\.smoke|checkout\.smoke|catalog-live|catalog-mobile-layout|header-navigation|product-detail|service-requests|map-overflow|project-content|storefront-style)\.spec\.ts/;

export default defineConfig({
    testDir: './tests/E2E',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'buyer-desktop',
            use: { ...devices['Desktop Chrome'] },
            testMatch: buyerSpecs,
        },
        {
            name: 'buyer-mobile',
            use: { ...devices['iPhone 13'], browserName: 'chromium' },
            testMatch: buyerSpecs,
        },
        {
            name: 'buyer-mobile-320',
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } },
            testMatch: /(catalog-mobile-layout|header-navigation|map-overflow|product-detail|project-content|storefront-style)\.spec\.ts/,
        },
        {
            name: 'buyer-ru-preview',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /(buyer\.ru-preview|checkout\.smoke|catalog-live|header-navigation|product-detail|service-requests|map-overflow|project-content)\.spec\.ts/,
        },
        {
            name: 'buyer-cart-390',
            use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } },
            testMatch: /cart-mobile\.spec\.ts/,
        },
        {
            name: 'buyer-cart-320',
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } },
            testMatch: /cart-mobile\.spec\.ts/,
        },
        {
            name: 'admin-desktop',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /admin\.smoke\.spec\.ts/,
        },
        {
            name: 'admin-mobile-390',
            dependencies: ['admin-desktop'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } },
            testMatch: /admin\.p0\.spec\.ts/,
        },
        {
            name: 'admin-mobile-320',
            dependencies: ['admin-mobile-390'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } },
            testMatch: /admin\.p0\.spec\.ts/,
        },
    ],
    webServer: {
        command: `"${phpBinary}" -r "file_exists('${e2eDatabaseForPhp}') || touch('${e2eDatabaseForPhp}');" && "${phpBinary}" artisan migrate:fresh --force && "${phpBinary}" artisan e2e:seed-admin && "${phpBinary}" artisan db:seed --class=E2eStorefrontSeeder && "${phpBinary}" artisan storage:link && "${phpBinary}" artisan serve --host=127.0.0.1 --port=${serverPort}`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: e2eDatabase,
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            E2E_ADMIN_EMAIL: adminEmail,
            E2E_ADMIN_PASSWORD: adminPassword,
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
