import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-only-password';

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
            testMatch: /buyer\.smoke\.spec\.ts/,
        },
        {
            name: 'buyer-mobile',
            use: { ...devices['iPhone 13'], browserName: 'chromium' },
            testMatch: /buyer\.smoke\.spec\.ts/,
        },
        {
            name: 'admin-desktop',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /admin\.smoke\.spec\.ts/,
        },
    ],
    webServer: {
        command: 'php artisan migrate --force && php artisan e2e:seed-admin && php artisan serve --host=127.0.0.1 --port=8000',
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            APP_DEBUG: 'false',
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            E2E_ADMIN_EMAIL: adminEmail,
            E2E_ADMIN_PASSWORD: adminPassword,
        },
    },
});
