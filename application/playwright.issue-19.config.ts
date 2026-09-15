import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8019';
const serverPort = new URL(baseURL).port || '8019';
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const database = process.env.E2E_DB_DATABASE ?? `${process.cwd()}/database/issue-19-e2e.sqlite`;
const databaseForPhp = database.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const phpTempDirectory = (process.env.PLAYWRIGHT_PHP_TEMP_DIR ?? `${process.cwd()}/storage/framework/playwright-tmp`).replace(/\\/g, '/');
const phpTempDirectoryForPhp = phpTempDirectory.replace(/'/g, "\\'");
const phpCommand = `"${phpBinary}" -d "sys_temp_dir=${phpTempDirectory}" -d "upload_tmp_dir=${phpTempDirectory}"`;

export default defineConfig({
    testDir: './tests/E2E',
    testMatch: /issue-19-catalog-availability\.spec\.ts/,
    outputDir: './tmp/issue-19/playwright-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 180_000,
    reporter: 'list',
    use: {
        baseURL,
        browserName: 'chromium',
        colorScheme: 'light',
        serviceWorkers: 'block',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        { name: 'issue-19-nb-desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'issue-19-nb-mobile', use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 390, height: 844 } } },
        { name: 'issue-19-nb-mobile-320', use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 320, height: 720 } } },
        { name: 'issue-19-ru-desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'issue-19-ru-mobile', use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: { width: 390, height: 844 } } },
    ],
    webServer: {
        command: `${phpCommand} -r "is_dir('${phpTempDirectoryForPhp}') || mkdir('${phpTempDirectoryForPhp}', 0777, true); file_exists('${databaseForPhp}') || touch('${databaseForPhp}');" && ${phpCommand} artisan migrate:fresh --force && ${phpCommand} artisan db:seed --class=E2eStorefrontSeeder --force && ${phpCommand} -S 127.0.0.1:${serverPort} -t public tests/E2E/server.php`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            APP_URL: baseURL,
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            DB_URL: '',
            MAIL_MAILER: 'array',
            NDI_INQUIRY_ENABLED: 'false',
            NDI_ORDER_ENABLED: 'false',
            NDI_SANDBOX_CATALOG_ENABLED: 'true',
            NDI_SANDBOX_INQUIRY_ENABLED: 'false',
            NDI_SANDBOX_ORDER_ENABLED: 'false',
            PAYMENTS_ENABLED: 'false',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        },
    },
});
