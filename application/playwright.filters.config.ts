import { defineConfig, devices } from '@playwright/test';

const liveURL = process.env.FILTERS_LIVE_URL;
if (liveURL && liveURL !== 'https://staging-easytire.bethub.fun') {
    throw new Error('Read-only filter audit is restricted to the approved staging.');
}
const baseURL = liveURL ?? 'http://127.0.0.1:8197';
const root = process.cwd().replace(/\\/g, '/');
const isolated = `${root}/tmp/filters`;
const php = `"${process.env.PLAYWRIGHT_PHP_BINARY ?? 'php'}"`;

export default defineConfig({
    testDir: './tests/E2E',
    testMatch: /catalog-filter-consistency\.spec\.ts/,
    outputDir: liveURL ? './tmp/filters/live-results' : './tmp/filters/playwright-results',
    workers: 1,
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 12_000 },
    reporter: 'list',
    use: { baseURL, browserName: 'chromium', serviceWorkers: 'block', actionTimeout: 12_000, trace: 'retain-on-failure', screenshot: 'only-on-failure', video: 'off' },
    projects: ['nb', 'ru'].flatMap(locale => [1440, 390, 320].map(width => ({
        name: `filters-${locale}-${width === 1440 ? 'desktop' : 'mobile'}-${width}`,
        use: { ...devices['Desktop Chrome'], viewport: { width, height: width === 1440 ? 1000 : 844 } },
    }))),
    webServer: liveURL ? undefined : {
        command: `${php} tests/E2E/prepare-catalog-filters.php && ${php} -S 127.0.0.1:8197 -t public tests/E2E/server.php`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: baseURL,
            APP_KEY: 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
            // Laravel's cache-path resolver recognizes / and \\, but not a Windows drive prefix.
            APP_CONFIG_CACHE: 'tmp/filters/cache/config.php', APP_ROUTES_CACHE: 'tmp/filters/cache/routes.php',
            APP_EVENTS_CACHE: 'tmp/filters/cache/events.php', APP_SERVICES_CACHE: 'tmp/filters/cache/services.php',
            APP_PACKAGES_CACHE: 'tmp/filters/cache/packages.php', VIEW_COMPILED_PATH: `${isolated}/storage/framework/views`,
            LARAVEL_STORAGE_PATH: `${isolated}/storage`,
            DB_CONNECTION: 'sqlite', DB_DATABASE: `${root}/database/filters-e2e.sqlite`, DB_URL: '',
            CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
            NDI_CATALOG_MODE: 'snapshot', NDI_INBOX_PATH: '', NDI_SANDBOX_CATALOG_ENABLED: 'true',
            NDI_INQUIRY_ENABLED: 'false', NDI_ORDER_ENABLED: 'false', NDI_C1_TEST_MODE: 'false',
            NDI_SANDBOX_INQUIRY_ENABLED: 'false', NDI_SANDBOX_ORDER_ENABLED: 'false',
            NDI_FULFILMENT_READ_ENABLED: 'false', NDI_MEDIA_SYNC_ENABLED: 'false',
            PAYMENTS_ENABLED: 'false', OUTBOX_NOTION_ENABLED: 'false', OUTBOX_TELEGRAM_ENABLED: 'false',
            CUSTOMER_EMAIL_DELIVERY_ENABLED: 'false', VEHICLE_FITMENT_ENABLED: 'false',
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
