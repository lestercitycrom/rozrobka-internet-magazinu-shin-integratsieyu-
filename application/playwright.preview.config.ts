import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Preview',
    fullyParallel: true,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:4173',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'preview-desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'preview-mobile-390', use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } } },
        { name: 'preview-mobile-320', use: { ...devices['Desktop Chrome'], viewport: { width: 320, height: 844 } } },
    ],
    webServer: {
        command: 'python3 -m http.server 4173 --bind 127.0.0.1 --directory ../../preview',
        url: 'http://127.0.0.1:4173/catalog.html',
        reuseExistingServer: true,
        timeout: 30_000,
    },
});
