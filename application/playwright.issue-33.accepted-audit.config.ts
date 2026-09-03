import { defineConfig, devices } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8034';
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const database = `${process.cwd()}/database/issue-33-audit.sqlite`;
const databaseForPhp = database.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const phpTempDirectory = `${process.cwd()}/storage/framework/playwright-tmp`.replace(/\\/g, '/');
const phpTempDirectoryForPhp = phpTempDirectory.replace(/'/g, "\\'");
const phpCommand = `"${phpBinary}" -d "sys_temp_dir=${phpTempDirectory}"`;
process.env.PLAYWRIGHT_AUDIT_OUTPUT_ROOT ??= `${process.cwd()}/tmp/issue-33/accepted-raw`;

export default defineConfig({
    testDir: './tests/Audit',
    testMatch: /issue-26-(visual-audit|interaction-matrix|local-states)\.spec\.ts/,
    outputDir: './tmp/issue-33/accepted-audit-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 180_000,
    reporter: [
        ['list'],
        ['json', { outputFile: './tmp/issue-33/accepted-audit-report.json' }],
    ],
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
        { name: 'desktop-1920x1080', use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } } },
        { name: 'desktop-1440x900', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'desktop-1366x768', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } } },
        { name: 'desktop-1280x800', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
        {
            name: 'mobile-touch-390x844',
            use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 390, height: 844 } },
        },
        {
            name: 'mobile-narrow-320x800',
            use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 800 }, hasTouch: true, isMobile: true },
        },
    ],
    webServer: {
        command: `${phpCommand} -r "is_dir('${phpTempDirectoryForPhp}') || mkdir('${phpTempDirectoryForPhp}', 0777, true); file_exists('${databaseForPhp}') || touch('${databaseForPhp}');" && ${phpCommand} artisan migrate:fresh --force && ${phpCommand} artisan e2e:seed-admin && ${phpCommand} artisan db:seed --class=E2eStorefrontSeeder && ${phpCommand} artisan storage:link --force && ${phpCommand} artisan serve --host=127.0.0.1 --port=8034`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            E2E_ADMIN_EMAIL: 'issue-33-audit-admin@example.test',
            E2E_ADMIN_PASSWORD: 'issue-33-audit-local-only-password',
            MAIL_MAILER: 'log',
            NDI_SANDBOX_CATALOG_ENABLED: 'true',
            NDI_SANDBOX_INQUIRY_ENABLED: 'false',
            NDI_SANDBOX_ORDER_ENABLED: 'false',
            NDI_SUBMISSION_ENABLED: 'false',
            PAYMENTS_ENABLED: 'false',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
