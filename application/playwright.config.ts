import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const serverPort = new URL(baseURL).port || '8000';
const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';
const operationsEmail = process.env.E2E_OPERATIONS_EMAIL ?? 'e2e-operations@example.test';
const operationsPassword = process.env.E2E_OPERATIONS_PASSWORD ?? 'e2e-operations-password';
const e2eDatabase = process.env.E2E_DB_DATABASE ?? `${process.cwd()}/database/e2e.sqlite`;
const e2eDatabaseForPhp = e2eDatabase.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const phpTempDirectory = (process.env.PLAYWRIGHT_PHP_TEMP_DIR ?? `${process.cwd()}/storage/framework/playwright-tmp`).replace(/\\/g, '/');
const phpTempDirectoryForPhp = phpTempDirectory.replace(/'/g, "\\'");
const phpCommand = `"${phpBinary}" -d "sys_temp_dir=${phpTempDirectory}" -d "upload_tmp_dir=${phpTempDirectory}"`;
const buyerSpecs = /(?:^|[\\/])(buyer\.smoke|checkout\.smoke|cart-composition|catalog-live|catalog-mobile-layout|customer-navigation|design-foundation|ui-polish|header-navigation|home-hero-process|homepage-feedback|homepage-content-collections|homepage-composition|interaction-shell|product-detail|service-composition|service-requests|map-overflow|project-content|storefront-style)\.spec\.ts$/;

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
            use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 320, height: 844 } },
            // Keep admin.service-requests in its dedicated dependency-ordered admin matrices.
            testMatch: /(?:^|[\\/])(catalog-live|catalog-mobile-layout|customer-navigation|design-foundation|header-navigation|homepage-content-collections|homepage-composition|interaction-shell|map-overflow|product-detail|project-content|service-requests|storefront-style)\.spec\.ts$/,
        },
        {
            name: 'buyer-ru-preview',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /(?:^|[\\/])(buyer\.ru-preview|checkout\.smoke|catalog-live|customer-navigation|header-navigation|homepage-content-collections|homepage-composition|product-detail|service-requests|map-overflow|project-content)\.spec\.ts$/,
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
            name: 'admin-service-requests-desktop',
            dependencies: ['admin-desktop'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } },
            testMatch: /admin\.(service-requests|clients|operations-day)\.spec\.ts/,
        },
        {
            name: 'admin-service-requests-mobile-390',
            dependencies: ['admin-service-requests-desktop'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } },
            testMatch: /admin\.(service-requests|clients|operations-day)\.spec\.ts/,
        },
        {
            name: 'admin-service-requests-mobile-320',
            dependencies: ['admin-service-requests-mobile-390'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } },
            testMatch: /admin\.(service-requests|clients|operations-day)\.spec\.ts/,
        },
        {
            name: 'admin-homepage-content',
            dependencies: ['admin-service-requests-mobile-320'],
            use: { ...devices['Desktop Chrome'] },
            testMatch: /admin-homepage-content\.spec\.ts/,
        },
        {
            name: 'homepage-content-edge-cases',
            dependencies: ['buyer-desktop', 'buyer-mobile', 'buyer-mobile-320', 'buyer-ru-preview', 'admin-homepage-content'],
            use: { ...devices['Desktop Chrome'] },
            testMatch: /homepage-content-edge-cases\.spec\.ts/,
        },
        {
            name: 'admin-mobile-390',
            dependencies: ['admin-homepage-content'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } },
            testMatch: /admin\.p0\.spec\.ts/,
        },
        {
            name: 'admin-mobile-320',
            dependencies: ['admin-mobile-390'],
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } },
            testMatch: /admin\.p0\.spec\.ts/,
        },
        {
            name: 'issue-44-release-desktop',
            dependencies: ['homepage-content-edge-cases', 'admin-mobile-320'],
            use: { ...devices['Desktop Chrome'] },
            testMatch: /issue-44-release-qa\.spec\.ts/,
        },
        {
            name: 'issue-44-release-touch-390',
            dependencies: ['issue-44-release-desktop'],
            use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 390, height: 844 } },
            testMatch: /issue-44-release-qa\.spec\.ts/,
        },
        {
            name: 'issue-44-release-touch-320',
            dependencies: ['issue-44-release-touch-390'],
            use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 320, height: 800 } },
            testMatch: /issue-44-release-qa\.spec\.ts/,
        },
    ],
    webServer: {
        command: `${phpCommand} -r "is_dir('${phpTempDirectoryForPhp}') || mkdir('${phpTempDirectoryForPhp}', 0777, true); file_exists('${e2eDatabaseForPhp}') || touch('${e2eDatabaseForPhp}');" && ${phpCommand} artisan migrate:fresh --force && ${phpCommand} artisan e2e:seed-admin && ${phpCommand} artisan db:seed --class=E2eStorefrontSeeder && ${phpCommand} artisan storage:link && ${phpCommand} -S 127.0.0.1:${serverPort} -t public tests/E2E/server.php`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: process.env.CI ? 'pipe' : 'ignore',
        env: {
            TEMP: phpTempDirectory,
            TMP: phpTempDirectory,
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            APP_URL: baseURL,
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: e2eDatabase,
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            E2E_ADMIN_EMAIL: adminEmail,
            E2E_ADMIN_PASSWORD: adminPassword,
            E2E_OPERATIONS_EMAIL: operationsEmail,
            E2E_OPERATIONS_PASSWORD: operationsPassword,
            NDI_SANDBOX_CATALOG_ENABLED: 'true',
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
