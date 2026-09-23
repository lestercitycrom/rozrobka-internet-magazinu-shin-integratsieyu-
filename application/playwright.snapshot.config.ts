import { defineConfig, devices } from '@playwright/test';

const liveURL = process.env.SNAPSHOT_LIVE_URL;
if (liveURL && liveURL !== 'https://staging-easytire.bethub.fun') throw new Error('Snapshot smoke is restricted to the approved staging.');
const baseURL = liveURL ?? 'http://127.0.0.1:8195';
const php = `"${process.env.PLAYWRIGHT_PHP_BINARY ?? 'php'}"`;
const database = `${process.cwd()}/database/snapshot-e2e.sqlite`;

export default defineConfig({
    testDir: './tests/E2E',
    testMatch: /catalog-snapshot\.spec\.ts/,
    outputDir: './tmp/snapshot/playwright-results',
    workers: 1,
    fullyParallel: false,
    retries: 0,
    timeout: 90_000,
    reporter: 'list',
    use: { baseURL, browserName: 'chromium', serviceWorkers: 'block', trace: 'retain-on-failure', screenshot: 'only-on-failure', video: 'off' },
    projects: ['nb', 'ru'].flatMap(locale => [1440, 390, 320].map(width => ({
        name: `snapshot-${locale}-${width}`,
        use: { ...devices['Desktop Chrome'], viewport: { width, height: width === 1440 ? 1000 : 844 } },
    }))),
    webServer: liveURL ? undefined : {
        command: `${php} tests/E2E/prepare-catalog-snapshot.php && ${php} -S 127.0.0.1:8195 -t public tests/E2E/server.php`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: baseURL,
            APP_KEY: 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
            DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
            CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
            NDI_CATALOG_MODE: 'snapshot', NDI_SANDBOX_CATALOG_ENABLED: 'true',
            NDI_INQUIRY_ENABLED: 'false', NDI_ORDER_ENABLED: 'false', NDI_C1_TEST_MODE: 'false',
            NDI_SANDBOX_INQUIRY_ENABLED: 'false', NDI_SANDBOX_ORDER_ENABLED: 'false',
            NDI_FULFILMENT_READ_ENABLED: 'false', NDI_MEDIA_SYNC_ENABLED: 'false',
            PAYMENTS_ENABLED: 'false', OUTBOX_NOTION_ENABLED: 'false', OUTBOX_TELEGRAM_ENABLED: 'false',
            VEHICLE_FITMENT_ENABLED: 'false', STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
