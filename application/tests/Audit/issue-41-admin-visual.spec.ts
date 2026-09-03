import { expect, test, type Locator, type Page } from '@playwright/test';
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

async function resetKeyboardFocus(page: Page): Promise<void> {
    await page.locator('body').click({ position: { x: 2, y: 2 } });
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
}

async function activeElementIs(target: Locator): Promise<boolean> {
    return target.evaluate((element) => element === document.activeElement);
}

async function tabUntil(page: Page, target: Locator, maximum = 80, backwards = false): Promise<number> {
    if (await activeElementIs(target)) return 0;

    for (let index = 1; index <= maximum; index += 1) {
        await page.keyboard.press(backwards ? 'Shift+Tab' : 'Tab');
        if (await activeElementIs(target)) return index;
    }

    return -1;
}

async function assertKeyboardFocus(page: Page, target: Locator): Promise<void> {
    await expect(target).toBeFocused();
    await expect.poll(() => page.locator(':focus-visible').count()).toBe(1);
    await target.scrollIntoViewIfNeeded();

    const focus = await target.evaluate((element) => {
        const style = getComputedStyle(element);
        const box = element.getBoundingClientRect();
        const topbar = document.querySelector<HTMLElement>('.et-admin-topbar');

        return {
            width: Math.round(box.width),
            height: Math.round(box.height),
            top: Math.round(box.top),
            bottom: Math.round(box.bottom),
            outlineStyle: style.outlineStyle,
            outlineWidth: Number.parseFloat(style.outlineWidth),
            boxShadow: style.boxShadow,
            transform: style.transform,
            insideSidebar: Boolean(element.closest('[data-admin-sidebar]')),
            insideTopbar: Boolean(element.closest('.et-admin-topbar')),
            isTableRegion: element.matches('[role="region"][tabindex="0"]'),
            topbarBottom: topbar ? Math.round(topbar.getBoundingClientRect().bottom) : 0,
            viewportHeight: window.innerHeight,
        };
    });

    expect(focus.outlineStyle).not.toBe('none');
    expect(focus.outlineWidth).toBeGreaterThanOrEqual(2);
    if (focus.boxShadow !== 'none') expect(focus.isTableRegion).toBeTruthy();
    expect(focus.transform).toBe('none');
    expect(focus.width).toBeGreaterThan(0);
    expect(focus.height).toBeGreaterThan(0);
    expect(focus.bottom).toBeLessThanOrEqual(focus.viewportHeight + 1);
    if (!focus.insideSidebar && !focus.insideTopbar) expect(focus.top).toBeGreaterThanOrEqual(focus.topbarBottom - 1);
}

async function ensureLocale(page: Page, locale: 'nb' | 'ru'): Promise<void> {
    if (await page.locator('html').getAttribute('lang') === locale) return;

    await page.getByTestId(`switch-language-${locale}`).click();
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
}

async function verifyDashboardStateMatrix(page: Page, rawProject: string, project: string) {
    const requiredProjects = ['desktop-1440x900', 'tablet-1024x768', 'mobile-390x844', 'mobile-320x800'];
    if (!requiredProjects.includes(project)) return [];

    const stateContracts = {
        zero: [0, 0, 0, 0],
        low: [1, 3, 1, 2],
        large: [987, 1234, 876, 4321],
    } as const;
    const results = [];

    for (const locale of ['nb', 'ru'] as const) {
        const stateHeights: number[] = [];
        for (const [state, expectedValues] of Object.entries(stateContracts)) {
            await page.goto(`/admin/_e2e/dashboard/${state}`);
            await ensureLocale(page, locale);
            await expect(page.getByTestId('dashboard-kpis')).toHaveAttribute('data-e2e-dashboard-state', state);

            const cards = page.locator('[data-testid="dashboard-kpis"] .et-kpi');
            await expect(cards).toHaveCount(4);
            await expect(page.locator('[data-dashboard-tone="attention"]')).toHaveCount(2);
            await expect(page.locator('[data-dashboard-tone="neutral"]')).toHaveCount(2);
            const values = await cards.locator('[data-testid="kpi-value"]').allTextContents();
            expect(values.map((value) => Number.parseInt(value.trim(), 10))).toEqual([...expectedValues]);

            const geometry = await cards.evaluateAll((elements) => elements.map((element) => {
                const card = element as HTMLElement;
                const value = card.querySelector<HTMLElement>('[data-testid="kpi-value"]')!;
                const valueBox = value.getBoundingClientRect();
                const label = card.querySelector<HTMLElement>('.et-kpi__label')!;
                const cardBox = card.getBoundingClientRect();

                return {
                    left: Math.round(cardBox.left),
                    height: Math.round(cardBox.height),
                    cardOverflow: card.scrollWidth > card.clientWidth + 1 || card.scrollHeight > card.clientHeight + 1,
                    valueLineCount: value.getClientRects().length,
                    valueInside: valueBox.left >= cardBox.left - 1 && valueBox.right <= cardBox.right + 1,
                    labelText: label.innerText.trim(),
                };
            }));

            expect(geometry.every((item) => !item.cardOverflow)).toBeTruthy();
            expect(geometry.every((item) => item.valueLineCount === 1 && item.valueInside)).toBeTruthy();
            expect(geometry.every((item) => item.labelText.length > 0)).toBeTruthy();
            expect(Math.max(...geometry.map((item) => item.height)) - Math.min(...geometry.map((item) => item.height))).toBeLessThanOrEqual(1);

            const columns = new Set(geometry.map((item) => item.left)).size;
            const expectedColumns = test.info().project.use.viewport!.width > 1180
                ? 4
                : test.info().project.use.viewport!.width > 360 ? 2 : 1;
            expect(columns).toBe(expectedColumns);
            stateHeights.push(geometry[0].height);

            await page.screenshot({ path: path.join(rawProject, `dashboard-state-${state}-${locale}.png`), fullPage: true });
            results.push({ state, locale, expectedValues, tones: ['attention', 'neutral', 'attention', 'neutral'], columns, geometry });
        }

        expect(Math.max(...stateHeights) - Math.min(...stateHeights)).toBeLessThanOrEqual(1);
    }

    return results;
}

async function verifyKeyboardContracts(page: Page, rawProject: string, project: string) {
    const results: Record<string, unknown> = {};

    if (project === 'desktop-1440x900') {
        await page.goto('/admin');
        await ensureLocale(page, 'nb');
        await resetKeyboardFocus(page);

        const brand = page.getByTestId('admin-brand-link');
        const dashboardNavigation = page.getByTestId('admin-nav-dashboard');
        const requestsNavigation = page.getByTestId('admin-nav-service_requests');
        expect(await tabUntil(page, brand, 5)).toBeGreaterThan(0);
        await page.keyboard.press('Tab');
        await expect(dashboardNavigation).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(requestsNavigation).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(dashboardNavigation).toBeFocused();

        const healthNavigation = page.getByTestId('admin-nav-health');
        expect(await tabUntil(page, healthNavigation, 40)).toBeGreaterThan(0);
        await page.keyboard.press('Tab');
        await assertKeyboardFocus(page, page.getByTestId('switch-language-nb'));
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('switch-language-ru')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('admin-logout')).toBeFocused();
        await page.keyboard.press('Tab');
        await assertKeyboardFocus(page, page.getByTestId('dashboard-primary-action'));
        await page.keyboard.press('Tab');
        await assertKeyboardFocus(page, page.getByTestId('dashboard-work-catalog'));
        await page.screenshot({ path: path.join(rawProject, 'keyboard-dashboard-order.png'), fullPage: true });

        await page.goto('/admin/services/create');
        await resetKeyboardFocus(page);
        const slug = page.locator('input[name="slug"]');
        const save = page.locator('#main-content form button').last();
        const slugBefore = await slug.evaluate((element) => ({ width: (element as HTMLElement).offsetWidth, height: (element as HTMLElement).offsetHeight }));
        const slugSteps = await tabUntil(page, slug, 60);
        expect(slugSteps).toBeGreaterThan(0);
        await assertKeyboardFocus(page, slug);
        const slugAfter = await slug.evaluate((element) => ({ width: (element as HTMLElement).offsetWidth, height: (element as HTMLElement).offsetHeight }));
        expect(slugAfter).toEqual(slugBefore);
        const saveSteps = await tabUntil(page, save, 60);
        expect(saveSteps).toBeGreaterThan(0);
        await assertKeyboardFocus(page, save);
        await page.screenshot({ path: path.join(rawProject, 'keyboard-form-submit.png'), fullPage: true });

        await page.goto('/admin/services/create');
        const disabledSave = page.locator('#main-content form button').last();
        await disabledSave.evaluate((button: HTMLButtonElement) => { button.disabled = true; });
        const lastField = page.locator('textarea[name="seo_description_ru"]');
        await resetKeyboardFocus(page);
        expect(await tabUntil(page, lastField, 80)).toBeGreaterThan(0);
        await page.keyboard.press('Tab');
        await expect(disabledSave).not.toBeFocused();
        const disabledStyle = await disabledSave.evaluate((element) => {
            const style = getComputedStyle(element);
            return { cursor: style.cursor, opacity: Number.parseFloat(style.opacity) };
        });
        expect(disabledStyle.cursor).toBe('not-allowed');
        expect(disabledStyle.opacity).toBeLessThan(1);

        await page.goto('/admin/orders');
        await resetKeyboardFocus(page);
        const tableRegion = page.locator('[role="region"][tabindex="0"]', { has: page.locator('table') }).first();
        expect(await tabUntil(page, tableRegion, 80)).toBeGreaterThan(0);
        await assertKeyboardFocus(page, tableRegion);
        await page.keyboard.press('Tab');
        const rowAction = page.locator('table tbody a').first();
        await assertKeyboardFocus(page, rowAction);
        await page.screenshot({ path: path.join(rawProject, 'keyboard-table-row-action.png'), fullPage: true });

        results.desktop = {
            sidebarOrder: ['brand', 'dashboard', 'service_requests', 'health'],
            topbarOrder: ['NB', 'RU', 'logout'],
            dashboardOrder: ['primary-action', 'work-catalog'],
            form: { slugSteps, saveSteps, disabledSubmitSkipped: true },
            table: ['region', 'first-row-action'],
        };
    }

    if (project === 'mobile-390x844') {
        await page.goto('/admin');
        await ensureLocale(page, 'nb');
        const sidebar = page.getByTestId('admin-sidebar');
        const toggle = page.getByTestId('admin-menu-toggle');
        await expect(sidebar).toHaveAttribute('inert', '');
        await resetKeyboardFocus(page);
        expect(await tabUntil(page, toggle, 5)).toBeGreaterThan(0);
        await assertKeyboardFocus(page, toggle);
        await page.keyboard.press('Enter');
        await expect(sidebar).toHaveClass(/is-open/);
        await expect(sidebar).not.toHaveAttribute('inert', '');
        await expect(page.getByTestId('admin-brand-link')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('admin-nav-dashboard')).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(page.getByTestId('admin-brand-link')).toBeFocused();
        await page.screenshot({ path: path.join(rawProject, 'keyboard-drawer-entry.png'), fullPage: true });
        await page.keyboard.press('Escape');
        await expect(sidebar).not.toHaveClass(/is-open/);
        await expect(sidebar).toHaveAttribute('inert', '');
        await assertKeyboardFocus(page, toggle);
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('switch-language-nb')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('switch-language-ru')).toBeFocused();
        results.mobile = { drawer: ['toggle', 'brand', 'dashboard', 'brand', 'Escape', 'toggle'], topbarAfterClose: ['NB', 'RU'] };
    }

    return results;
}

async function verifyRequiredStateEvidence(page: Page, rawProject: string, project: string) {
    if (project !== 'desktop-1440x900' && project !== 'mobile-390x844') return {};

    const results: Record<string, unknown> = {};
    await ensureLocale(page, 'nb');

    if (project === 'desktop-1440x900') {
        await page.goto('/admin/content-pages/create');
        const contentForm = page.locator('#main-content form').first();
        const contentSubmit = contentForm.locator('button').last();
        await resetKeyboardFocus(page);
        expect(await tabUntil(page, contentSubmit, 80)).toBeGreaterThan(0);
        await page.keyboard.press('Enter');
        const invalidSlug = page.locator('#content-slug:invalid');
        await assertKeyboardFocus(page, invalidSlug);
        const validationMessage = await invalidSlug.evaluate((element: HTMLInputElement) => element.validationMessage);
        expect(validationMessage.length).toBeGreaterThan(0);
        await page.screenshot({ path: path.join(rawProject, 'state-client-validation.png'), fullPage: true });
        results.clientValidation = { selector: '#content-slug:invalid', validationMessagePresent: true };

        await page.locator('#content-slug').fill('INVALID SLUG');
        await page.locator('#content-title_nb').fill('Synthetic NB title');
        await page.locator('#content-title_ru').fill('Синтетический русский заголовок');
        await contentForm.evaluate((form: HTMLFormElement) => { form.noValidate = true; });
        await contentSubmit.click();
        await expect(page.getByRole('alert')).toBeVisible();
        await expect(page.locator('#content-slug')).toHaveValue('INVALID SLUG');
        await page.screenshot({ path: path.join(rawProject, 'state-server-validation.png'), fullPage: true });
        results.serverValidation = { selector: '[role="alert"]', retainedValue: 'INVALID SLUG' };

        await page.goto('/admin/homepage-promotions/create');
        await page.locator('#promotion-title_nb').fill('Ulagret syntetisk tittel');
        let dirtyDialog = '';
        page.once('dialog', async (dialog) => {
            dirtyDialog = dialog.message();
            await dialog.dismiss();
        });
        await page.getByTestId('switch-language-ru').click();
        await expect.poll(() => dirtyDialog).not.toBe('');
        await expect(page.locator('html')).toHaveAttribute('lang', 'nb');
        await expect(page.locator('#promotion-title_nb')).toHaveValue('Ulagret syntetisk tittel');
        await page.screenshot({ path: path.join(rawProject, 'state-dirty-guard.png'), fullPage: true });
        results.dirtyGuard = { selector: '[data-admin-locale-form]', dialog: dirtyDialog, navigationCancelled: true };

        await page.goto('/admin/media');
        const removableCard = page.locator('article', { has: page.locator('input[name="alt_nb"][value="Syntetisk slettbar mediefil"]') });
        await expect(removableCard).toBeVisible();
        let destructiveDialog = '';
        page.once('dialog', async (dialog) => {
            destructiveDialog = dialog.message();
            await dialog.dismiss();
        });
        await removableCard.locator('[data-testid^="media-delete-"]').click();
        await expect.poll(() => destructiveDialog).not.toBe('');
        await expect(removableCard).toBeVisible();
        await page.screenshot({ path: path.join(rawProject, 'state-destructive-confirmation.png'), fullPage: true });
        results.destructiveConfirmation = { selector: '[data-testid^="media-delete-"]', dialog: destructiveDialog, deletionCancelled: true };

        await page.goto('/admin/imports');
        await expect(page.getByTestId('imports-empty-state')).toBeVisible();
        await page.screenshot({ path: path.join(rawProject, 'state-empty-imports.png'), fullPage: true });
        results.emptyState = { selector: '[data-testid="imports-empty-state"]', visible: true };
    }

    await page.goto('/admin/service-requests');
    await page.getByRole('link', { name: 'SR-VISUAL-0001' }).click();
    const longName = 'Visuell Testkunde med et bevisst langt navn';
    const longEmail = 'langt-administrativt-testnavn@synthetic.example.test';
    await expect(page.getByText(longName, { exact: true })).toBeVisible();
    await expect(page.getByText(longEmail, { exact: true })).toBeVisible();
    const nbWidth = await page.evaluate(() => ({ document: document.documentElement.scrollWidth, viewport: window.innerWidth }));
    expect(nbWidth.document).toBeLessThanOrEqual(nbWidth.viewport + 1);
    await page.screenshot({ path: path.join(rawProject, 'state-long-values-nb.png'), fullPage: true });
    await ensureLocale(page, 'ru');
    await expect(page.getByText(longName, { exact: true })).toBeVisible();
    await expect(page.getByText(longEmail, { exact: true })).toBeVisible();
    const ruWidth = await page.evaluate(() => ({ document: document.documentElement.scrollWidth, viewport: window.innerWidth }));
    expect(ruWidth.document).toBeLessThanOrEqual(ruWidth.viewport + 1);
    await page.screenshot({ path: path.join(rawProject, 'state-long-values-ru.png'), fullPage: true });
    results.longValues = { selectors: [longName, longEmail], locales: ['nb', 'ru'], widths: { nb: nbWidth, ru: ruWidth } };

    return results;
}

async function verifyInteractionStates(page: Page, rawProject: string, project: string) {
    await page.goto('/admin');
    const primaryAction = page.locator('#main-content .et-btn--primary').first();
    const initialBox = await primaryAction.boundingBox();
    await primaryAction.hover();
    const hoverBox = await primaryAction.boundingBox();
    expect(hoverBox).toEqual(initialBox);

    return {
        keyboard: await verifyKeyboardContracts(page, rawProject, project),
        states: await verifyRequiredStateEvidence(page, rawProject, project),
        dashboardStates: await verifyDashboardStateMatrix(page, rawProject, project),
    };
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

    const acceptanceEvidence = evidenceMode === 'after'
        ? await verifyInteractionStates(page, rawProject, project)
        : null;

    await writeFile(
        path.join(root, evidenceMode, `${project}-measurements.json`),
        `${JSON.stringify(measurements, null, 2)}\n`,
        'utf8',
    );

    if (acceptanceEvidence) {
        await writeFile(
            path.join(root, evidenceMode, `${project}-acceptance-states.json`),
            `${JSON.stringify(acceptanceEvidence, null, 2)}\n`,
            'utf8',
        );
    }

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
