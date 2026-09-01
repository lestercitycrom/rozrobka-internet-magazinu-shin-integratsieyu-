import { defineConfig, devices } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8026';
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const database = `${process.cwd()}/database/issue-26.sqlite`;
const databaseForPhp = database.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

export default defineConfig({
    testDir: './tests/Audit',
    testMatch: /issue-26-local-states\.spec\.ts/,
    outputDir: './tmp/issue-26/local-test-results-corrected',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    reporter: 'list',
    use: {
        baseURL,
        browserName: 'chromium',
        colorScheme: 'light',
        locale: 'nb-NO',
        serviceWorkers: 'block',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
    },
    projects: [
        {
            name: 'local-desktop-1440x900',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'local-mobile-touch-390x844',
            use: {
                ...devices['iPhone 13'],
                browserName: 'chromium',
                viewport: { width: 390, height: 844 },
            },
        },
    ],
    webServer: {
        command: `"${phpBinary}" -r "file_exists('${databaseForPhp}') || touch('${databaseForPhp}');" && "${phpBinary}" artisan migrate:fresh --force && "${phpBinary}" artisan e2e:seed-admin && "${phpBinary}" artisan db:seed --class=E2eStorefrontSeeder && "${phpBinary}" artisan storage:link --force && "${phpBinary}" artisan serve --host=127.0.0.1 --port=8026`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            E2E_ADMIN_EMAIL: 'issue-26-admin@example.test',
            E2E_ADMIN_PASSWORD: 'issue-26-local-only-password',
            MAIL_MAILER: 'log',
            NDI_SUBMISSION_ENABLED: 'false',
            PAYMENTS_ENABLED: 'false',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
