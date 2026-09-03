import { defineConfig, devices } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8033';
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const database = `${process.cwd()}/database/issue-33.sqlite`;
const databaseForPhp = database.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const phpTempDirectory = `${process.cwd()}/storage/framework/playwright-tmp`.replace(/\\/g, '/');
const phpTempDirectoryForPhp = phpTempDirectory.replace(/'/g, "\\'");
const phpCommand = `"${phpBinary}" -d "sys_temp_dir=${phpTempDirectory}"`;

export default defineConfig({
    testDir: './tests/Audit',
    testMatch: /issue-33-evidence\.spec\.ts/,
    outputDir: './tmp/issue-33/test-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 600_000,
    reporter: 'list',
    use: {
        baseURL,
        ...devices['Desktop Chrome'],
        browserName: 'chromium',
        colorScheme: 'light',
        locale: 'nb-NO',
        serviceWorkers: 'block',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
    },
    webServer: {
        command: `${phpCommand} -r "is_dir('${phpTempDirectoryForPhp}') || mkdir('${phpTempDirectoryForPhp}', 0777, true); file_exists('${databaseForPhp}') || touch('${databaseForPhp}');" && ${phpCommand} artisan migrate:fresh --force && ${phpCommand} artisan e2e:seed-admin && ${phpCommand} artisan db:seed --class=E2eStorefrontSeeder && ${phpCommand} artisan storage:link --force && ${phpCommand} artisan serve --host=127.0.0.1 --port=8033`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            E2E_ADMIN_EMAIL: 'issue-33-admin@example.test',
            E2E_ADMIN_PASSWORD: 'issue-33-local-only-password',
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
