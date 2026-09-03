import { expect, test, type Page } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const evidenceMode = process.env.ISSUE_41_EVIDENCE_MODE ?? 'before';
const root = path.resolve(process.cwd(), '..', 'evidence', 'issue-41');
const rawRoot = path.resolve(process.cwd(), 'tmp', 'issue-41', evidenceMode);
const adminEmail = 'issue-41-admin@example.test';
const adminPassword = 'issue-41-local-only-password';

type Surface = {
    key: string;
    path: string;
    family: string;
};

const surfaces: Surface[] = [
    { key: 'dashboard', path: '/admin', family: 'dashboard' },
    { key: 'requests-list', path: '/admin/service-requests', family: 'requests' },
    { key: 'booking-calendar', path: '/admin/booking-calendar', family: 'booking' },
    { key: 'orders-list', path: '/admin/orders', family: 'orders' },
    { key: 'catalog-list', path: '/admin/catalog', family: 'catalogue' },
    { key: 'imports-list', path: '/admin/imports', family: 'imports' },
    { key: 'services-list', path: '/admin/services', family: 'services' },
    { key: 'service-addons-list', path: '/admin/service-addons', family: 'services' },
    { key: 'public-prices-list', path: '/admin/public-service-prices', family: 'services' },
    { key: 'content-list', path: '/admin/content-pages', family: 'content' },
    { key: 'promotions-list', path: '/admin/homepage-promotions', family: 'content' },
    { key: 'reviews-list', path: '/admin/homepage-reviews', family: 'content' },
    { key: 'ratings-list', path: '/admin/homepage-rating-summaries', family: 'content' },
    { key: 'partners-list', path: '/admin/homepage-business-partners', family: 'content' },
    { key: 'media-library', path: '/admin/media', family: 'media' },
    { key: 'pricing-list', path: '/admin/pricing-rules', family: 'pricing' },
    { key: 'storefront-settings', path: '/admin/storefront-settings', family: 'settings' },
    { key: 'health', path: '/admin/health', family: 'operations' },
    { key: 'service-form', path: '/admin/services/create', family: 'forms' },
    { key: 'content-form', path: '/admin/content-pages/create', family: 'forms' },
    { key: 'promotion-form', path: '/admin/homepage-promotions/create', family: 'forms' },
    { key: 'pricing-form', path: '/admin/pricing-rules/create', family: 'forms' },
    { key: 'booking-form', path: '/admin/booking-calendar/intervals/create', family: 'forms' },
];

async function login(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.getByLabel(/e-postadresse/i).fill(adminEmail);
    await page.getByLabel(/passord/i).fill(adminPassword);
    await page.getByRole('button', { name: /logg inn/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
    if (await page.locator('html').getAttribute('lang') !== 'nb') {
        await page.getByTestId('switch-language-nb').click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
    }
}

async function measure(page: Page, surface: Surface) {
    return page.evaluate(({ key, family }) => {
        const documentWidth = Math.ceil(document.documentElement.scrollWidth);
        const viewportWidth = window.innerWidth;
        const main = document.querySelector<HTMLElement>('#main-content');
        const topbar = document.querySelector<HTMLElement>('.et-admin-topbar');
        const sidebar = document.querySelector<HTMLElement>('[data-admin-sidebar]');
        const tables = [...document.querySelectorAll<HTMLTableElement>('table')].map((table) => {
            let owner: HTMLElement | null = table.parentElement;
            while (owner && owner !== document.body) {
                const style = getComputedStyle(owner);
                if (['auto', 'scroll'].includes(style.overflowX)) break;
                owner = owner.parentElement;
            }
            return {
                width: Math.round(table.getBoundingClientRect().width),
                scrollWidth: table.scrollWidth,
                overflowOwner: owner && owner !== document.body ? owner.className : null,
                overflowOwnerTabIndex: owner && owner !== document.body ? owner.getAttribute('tabindex') : null,
                overflowOwnerRole: owner && owner !== document.body ? owner.getAttribute('role') : null,
                overflowOwnerLabel: owner && owner !== document.body
                    ? owner.getAttribute('aria-label') ?? owner.getAttribute('aria-labelledby')
                    : null,
            };
        });
        const controls = [...document.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea')]
            .filter((element) => !element.hasAttribute('disabled') && getComputedStyle(element).display !== 'none')
            .map((element) => {
                const box = element.getBoundingClientRect();
                const inClosedSidebar = Boolean(element.closest('[data-admin-sidebar]:not(.is-open)'))
                    && getComputedStyle(document.querySelector<HTMLElement>('[data-admin-sidebar]')!).position === 'fixed';
                let scrollOwner: HTMLElement | null = element.parentElement;
                while (scrollOwner && scrollOwner !== document.body) {
                    if (['auto', 'scroll'].includes(getComputedStyle(scrollOwner).overflowX)) break;
                    scrollOwner = scrollOwner.parentElement;
                }
                const outsideViewport = box.right > viewportWidth + 1 || box.left < -1;
                return {
                    tag: element.tagName.toLowerCase(),
                    text: (element.innerText || element.getAttribute('aria-label') || element.getAttribute('name') || '').trim().slice(0, 90),
                    left: Math.round(box.left),
                    right: Math.round(box.right),
                    top: Math.round(box.top),
                    width: Math.round(box.width),
                    clippedInline: outsideViewport && !inClosedSidebar && (!scrollOwner || scrollOwner === document.body),
                    inHorizontalScroller: outsideViewport && Boolean(scrollOwner && scrollOwner !== document.body),
                };
            });
        return {
            key,
            family,
            locale: document.documentElement.lang,
            viewport: { width: viewportWidth, height: window.innerHeight },
            document: { width: documentWidth, overflow: documentWidth > viewportWidth + 1 },
            main: main ? { width: Math.round(main.getBoundingClientRect().width), left: Math.round(main.getBoundingClientRect().left) } : null,
            topbar: topbar ? { height: Math.round(topbar.getBoundingClientRect().height) } : null,
            sidebar: sidebar ? { width: Math.round(sidebar.getBoundingClientRect().width), position: getComputedStyle(sidebar).position } : null,
            headings: [...document.querySelectorAll('h1, h2')].map((heading) => heading.textContent?.trim()).filter(Boolean).slice(0, 20),
            unresolvedKeys: (document.body.innerText.match(/\b(?:admin|pro|project|service_requests|orders|dashboard|content_ui|health_ui)\.[a-z0-9_.-]+\b/gi) ?? []).slice(0, 20),
            tables,
            clippedControls: controls.filter((control) => control.clippedInline),
            controlCount: controls.length,
        };
    }, surface);
}

test('login, error and authenticated admin surface inventory', async ({ page }, testInfo) => {
    const project = testInfo.project.name;
    const rawProject = path.join(rawRoot, project);
    await mkdir(rawProject, { recursive: true });
    await mkdir(path.join(root, evidenceMode), { recursive: true });

    await page.goto('/admin/login');
    await expect(page.getByRole('heading', { name: /logg inn/i })).toBeVisible();
    if (['desktop-1440x900', 'mobile-390x844', 'mobile-320x800'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'login.png'), fullPage: true });
    }
    await page.getByLabel(/e-postadresse/i).fill('invalid@example.test');
    await page.getByLabel(/passord/i).fill('invalid-password');
    await page.getByRole('button', { name: /logg inn/i }).click();
    await expect(page.getByRole('alert')).toBeVisible();
    if (['desktop-1440x900', 'mobile-390x844'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'login-error.png'), fullPage: true });
    }

    await login(page);
    const measurements = [];
    for (const surface of surfaces) {
        await page.goto(surface.path);
        await expect(page.locator('#main-content')).toBeVisible();
        measurements.push(await measure(page, surface));

        const keepRaw = surface.key === 'dashboard'
            || ['desktop-1440x900', 'tablet-1024x768', 'mobile-390x844'].includes(project);
        if (keepRaw) {
            await page.screenshot({ path: path.join(rawProject, `${surface.key}.png`), fullPage: true });
        }
    }

    await page.goto('/admin/service-requests');
    const requestLink = page.locator('a[href*="/admin/service-requests/"]').first();
    const requestHref = await requestLink.getAttribute('href');
    expect(requestHref).toBeTruthy();
    await page.goto(requestHref!);
    const requestDetail: Surface = { key: 'request-detail', path: page.url(), family: 'requests' };
    measurements.push(await measure(page, requestDetail));
    if (['desktop-1440x900', 'tablet-1024x768', 'mobile-390x844'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'request-detail.png'), fullPage: true });
    }

    await page.goto('/admin/orders');
    const orderLink = page.locator('a', { hasText: 'ET-E2E-PAYMENT' }).first();
    const orderHref = await orderLink.getAttribute('href');
    expect(orderHref).toBeTruthy();
    await page.goto(orderHref!);
    const orderDetail: Surface = { key: 'order-detail', path: page.url(), family: 'orders' };
    measurements.push(await measure(page, orderDetail));
    if (['desktop-1440x900', 'tablet-1024x768', 'mobile-390x844'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'order-detail.png'), fullPage: true });
    }

    await page.goto('/admin/catalog');
    const catalogLink = page.locator('a[href*="/admin/catalog/"]').first();
    const catalogHref = await catalogLink.getAttribute('href');
    expect(catalogHref).toBeTruthy();
    await page.goto(catalogHref!);
    const catalogDetail: Surface = { key: 'catalog-detail', path: page.url(), family: 'catalogue' };
    measurements.push(await measure(page, catalogDetail));
    if (['desktop-1440x900', 'tablet-1024x768', 'mobile-390x844'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'catalog-detail.png'), fullPage: true });
    }

    await page.goto('/admin');
    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
    const ruDashboard: Surface = { key: 'dashboard-ru', path: page.url(), family: 'dashboard' };
    measurements.push(await measure(page, ruDashboard));
    if (['desktop-1440x900', 'mobile-390x844', 'mobile-320x800'].includes(project)) {
        await page.screenshot({ path: path.join(rawProject, 'dashboard-ru.png'), fullPage: true });
    }

    if (project.startsWith('mobile-')) {
        const toggle = page.getByTestId('admin-menu-toggle');
        await toggle.click();
        await expect(page.getByTestId('admin-sidebar')).toHaveClass(/is-open/);
        await page.screenshot({ path: path.join(rawProject, 'drawer-open.png'), fullPage: true });
        await page.keyboard.press('Escape');
        await expect(toggle).toBeFocused();
    }

    await writeFile(
        path.join(root, evidenceMode, `${project}-measurements.json`),
        `${JSON.stringify(measurements, null, 2)}\n`,
        'utf8',
    );

    if (evidenceMode === 'after') {
        expect(measurements.every((result) => !result.document.overflow)).toBeTruthy();
        expect(measurements.flatMap((result) => result.clippedControls)).toHaveLength(0);
        expect(measurements.flatMap((result) => result.unresolvedKeys)).toHaveLength(0);
        const tableRegions = measurements.flatMap((result) => result.tables)
            .filter((table) => table.overflowOwner);
        expect(tableRegions.every((table) => table.overflowOwnerTabIndex === '0')).toBeTruthy();
        expect(tableRegions.every((table) => table.overflowOwnerRole === 'region')).toBeTruthy();
        expect(tableRegions.every((table) => Boolean(table.overflowOwnerLabel))).toBeTruthy();
    }
});
