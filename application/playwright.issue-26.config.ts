import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://staging-easytire.bethub.fun';

export default defineConfig({
    testDir: './tests/Audit',
    testMatch: /issue-26-visual-audit\.spec\.ts/,
    outputDir: '../tmp/issue-26/test-results-final',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 180_000,
    reporter: [
        ['list'],
        ['json', { outputFile: '../tmp/issue-26/playwright-report-final.json' }],
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
        {
            name: 'desktop-1920x1080',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } },
        },
        {
            name: 'desktop-1440x900',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'desktop-1366x768',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } },
        },
        {
            name: 'desktop-1280x800',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
        },
        {
            name: 'mobile-touch-390x844',
            use: {
                ...devices['iPhone 13'],
                browserName: 'chromium',
                viewport: { width: 390, height: 844 },
            },
        },
        {
            name: 'mobile-narrow-320x800',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 320, height: 800 },
                hasTouch: true,
                isMobile: true,
            },
        },
    ],
});
