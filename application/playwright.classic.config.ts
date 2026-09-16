import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const modernBaseURL = process.env.CLASSIC_PREVIEW_MODERN_URL ?? 'http://127.0.0.1:8046';
const classicBaseURL = process.env.CLASSIC_PREVIEW_CLASSIC_URL ?? 'http://127.0.0.1:8047';
const allowRemoteReadOnly = process.env.CLASSIC_PREVIEW_ALLOW_REMOTE_READONLY === '1';
const ciAutostart = process.env.CLASSIC_PREVIEW_CI_AUTOSTART === '1';
const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';

for (const [profile, value] of Object.entries({ modern: modernBaseURL, classic: classicBaseURL })) {
    const url = new URL(value);
    const local = ['127.0.0.1', 'localhost', '::1'].includes(url.hostname);
    if (!local && !allowRemoteReadOnly) {
        throw new Error(`${profile} preview URL is remote; set CLASSIC_PREVIEW_ALLOW_REMOTE_READONLY=1 for the read-only smoke.`);
    }
}

const profiles = [
    { name: 'modern', baseURL: modernBaseURL },
    { name: 'classic', baseURL: classicBaseURL },
] as const;
const locales = ['nb', 'ru'] as const;
const viewports = [
    { name: 'desktop', viewport: { width: 1440, height: 1000 }, isMobile: false },
    { name: 'mobile-390', viewport: { width: 390, height: 844 }, isMobile: true },
    { name: 'mobile-320', viewport: { width: 320, height: 800 }, isMobile: true },
] as const;

const ciEnvironment = (profile: 'modern' | 'classic') => {
    const root = path.resolve(process.cwd(), 'tmp/classic-preview/ci', profile);
    const relativeRoot = `tmp/classic-preview/ci/${profile}`;
    return {
        APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: profile === 'modern' ? modernBaseURL : classicBaseURL,
        APP_KEY: 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
        APP_CONFIG_CACHE: `${relativeRoot}/cache/config.php`, APP_ROUTES_CACHE: `${relativeRoot}/cache/routes.php`,
        APP_EVENTS_CACHE: `${relativeRoot}/cache/events.php`, APP_SERVICES_CACHE: `${relativeRoot}/cache/services.php`,
        APP_PACKAGES_CACHE: `${relativeRoot}/cache/packages.php`,
        CLASSIC_PREVIEW_BOOTSTRAP: '1', DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(root, 'database.sqlite'), DB_URL: '',
        LARAVEL_STORAGE_PATH: path.join(root, 'storage'), VIEW_COMPILED_PATH: path.join(root, 'storage/framework/views'),
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
        NDI_CATALOG_MODE: 'snapshot', NDI_INBOX_PATH: '', NDI_MEDIA_HOSTS: 'example.invalid', NDI_SANDBOX_CATALOG_ENABLED: 'true',
        STOREFRONT_DESIGN_PROFILE: profile, STOREFRONT_RU_PREVIEW_ENABLED: 'true',
        NDI_INQUIRY_ENABLED: 'false', NDI_ORDER_ENABLED: 'false', NDI_C1_TEST_MODE: 'false',
        NDI_SANDBOX_INQUIRY_ENABLED: 'false', NDI_SANDBOX_ORDER_ENABLED: 'false', NDI_FULFILMENT_READ_ENABLED: 'false',
        NDI_MEDIA_SYNC_ENABLED: 'false', PAYMENTS_ENABLED: 'false', OUTBOX_NOTION_ENABLED: 'false',
        OUTBOX_TELEGRAM_ENABLED: 'false', CUSTOMER_EMAIL_DELIVERY_ENABLED: 'false', VEHICLE_FITMENT_ENABLED: 'false',
    };
};

export default defineConfig({
    testDir: './tests/E2E',
    testMatch: /classic-preview\.spec\.ts/,
    outputDir: './tmp/classic-preview/test-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: Boolean(process.env.CI),
    reporter: [
        ['list'],
        ['html', { outputFolder: './tmp/classic-preview/report', open: 'never' }],
    ],
    use: {
        ...devices['Desktop Chrome'],
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        actionTimeout: 10_000,
        navigationTimeout: 20_000,
    },
    projects: profiles.flatMap(profile => locales.flatMap(locale => viewports.map(viewport => ({
        name: `${profile.name}-${locale}-${viewport.name}`,
        metadata: {
            designProfile: profile.name,
            locale,
            remoteReadOnly: !['127.0.0.1', 'localhost', '::1'].includes(new URL(profile.baseURL).hostname),
        },
        use: {
            baseURL: profile.baseURL,
            viewport: viewport.viewport,
            isMobile: viewport.isMobile,
            hasTouch: viewport.isMobile,
        },
    })))),
    webServer: ciAutostart ? profiles.map(profile => ({
        command: `"${phpBinary}" tests/E2E/prepare-classic-preview.php ${profile.name} && "${phpBinary}" -S 127.0.0.1:${new URL(profile.baseURL).port} -t public tests/E2E/classic-preview-router.php`,
        url: profile.baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore' as const,
        stderr: 'pipe' as const,
        env: ciEnvironment(profile.name),
    })) : undefined,
});
