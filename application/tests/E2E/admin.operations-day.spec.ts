import { expect, test, type Page, type Response, type TestInfo } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { ensureAdminLocale, expectNoGlobalOverflow, loginOperations } from './helpers';

const LOOKUP_QUERY = 'Issue 66 Lookup Client';
const LOOKUP_PAGE_TWO_TARGET = 'Issue 66 Lookup Client 0021';
const LOOKUP_DEEP_TARGET = 'Issue 66 Lookup Client 1001';
const REGISTRATION_QUERY = 'ISSUE66REG';
const INTERNAL_SENTINEL = 'ISSUE66_INTERNAL_NOTE_MUST_NOT_LEAK';
const DAY = '2037-06-15';
const NEXT_DAY = '2037-06-16';

function expectedViewportWidth(projectName: string): number {
    if (projectName.includes('320')) return 320;
    if (projectName.includes('390')) return 390;
    return 1440;
}

function isClientLookupResponse(response: Response): boolean {
    return response.request().method() === 'GET'
        && new URL(response.url()).pathname === '/admin/clients/lookup';
}

function collectObjectKeys(value: unknown, keys = new Set<string>()): Set<string> {
    if (Array.isArray(value)) {
        value.forEach((item) => collectObjectKeys(item, keys));
        return keys;
    }

    if (value !== null && typeof value === 'object') {
        Object.entries(value as Record<string, unknown>).forEach(([key, item]) => {
            keys.add(key);
            collectObjectKeys(item, keys);
        });
    }

    return keys;
}

async function expectLookupPayloadIsBounded(response: Response): Promise<void> {
    expect(response.ok()).toBe(true);
    const payload: unknown = await response.json();
    const keys = collectObjectKeys(payload);

    for (const forbidden of [
        'created_by_user_id',
        'admin_note',
        'note',
        'submission_token',
        'vehicles',
        'consent_at',
        'password',
        'remember_token',
    ]) {
        expect(keys.has(forbidden), `lookup must not expose ${forbidden}`).toBe(false);
    }

    expect(JSON.stringify(payload)).not.toContain(INTERNAL_SENTINEL);
}

async function submitDayFilters(page: Page, date: string, resource: string): Promise<void> {
    const filters = page.locator('[data-operations-day-filters]');
    await expect(filters).toBeVisible();
    await filters.locator('[name="date"]').fill(date);
    await filters.locator('[name="resource"]').selectOption(resource);

    await Promise.all([
        page.waitForURL((url) => url.pathname === '/admin/service-requests/day'
            && url.searchParams.get('date') === date
            && url.searchParams.get('resource') === resource),
        filters.evaluate((element) => (element as HTMLFormElement).requestSubmit()),
    ]);
}

async function attachOperationsEvidence(page: Page, testInfo: TestInfo, label: string): Promise<void> {
    if (process.env.E2E_CAPTURE_OPERATIONS_ARTIFACTS !== '1') return;

    const safeLabel = label.replace(/[^a-z0-9-]+/gi, '-').toLowerCase();
    const evidenceDirectory = join(process.cwd(), 'test-results', 'operations-day-artifacts');
    await mkdir(evidenceDirectory, { recursive: true });
    const viewportImage = await page.screenshot({ fullPage: false });
    await writeFile(join(evidenceDirectory, `${safeLabel}-viewport.png`), viewportImage);
    await testInfo.attach(`${safeLabel}-viewport`, {
        body: viewportImage,
        contentType: 'image/png',
    });

    const summary = page.locator('[data-preparation-summary]');
    if (await summary.isVisible()) {
        const summaryImage = await summary.screenshot();
        const summaryText = `${await summary.innerText()}\n`;
        await writeFile(join(evidenceDirectory, `${safeLabel}-preparation.png`), summaryImage);
        await writeFile(join(evidenceDirectory, `${safeLabel}-preparation.txt`), summaryText, 'utf8');
        await testInfo.attach(`${safeLabel}-preparation`, {
            body: summaryImage,
            contentType: 'image/png',
        });
        await testInfo.attach(`${safeLabel}-preparation.txt`, {
            body: Buffer.from(summaryText, 'utf8'),
            contentType: 'text/plain',
        });
    }
}

test('operations remotely searches, paginates and selects clients without leaking internal fields', async ({ page }, testInfo) => {
    expect(page.viewportSize()?.width).toBe(expectedViewportWidth(testInfo.project.name));
    await loginOperations(page);

    for (const locale of ['nb', 'ru'] as const) {
        await ensureAdminLocale(page, locale);
        await page.goto('/admin/service-requests/create');

        const lookup = page.locator('[data-client-lookup]');
        const input = lookup.locator('[data-client-lookup-input]');
        const select = lookup.locator('[data-client-select]');
        const status = lookup.locator('[data-client-lookup-status]');
        const loadMore = lookup.locator('[data-client-load-more]');

        await expect(lookup).toBeVisible();
        await expect(status).toHaveAttribute('aria-live', /polite|assertive/);

        const firstLookup = page.waitForResponse(isClientLookupResponse);
        await input.fill(LOOKUP_QUERY);
        await expectLookupPayloadIsBounded(await firstLookup);

        await expect.poll(async () => select.locator('option').count()).toBeGreaterThanOrEqual(21);
        await expect(loadMore).toBeVisible();
        await expect(select.locator('option').filter({ hasText: LOOKUP_PAGE_TWO_TARGET })).toHaveCount(0);

        const secondLookup = page.waitForResponse(isClientLookupResponse);
        await loadMore.click();
        await expectLookupPayloadIsBounded(await secondLookup);

        await expect(select.locator('option').filter({ hasText: LOOKUP_PAGE_TWO_TARGET })).toHaveCount(1);

        const deepLookup = page.waitForResponse(isClientLookupResponse);
        await input.fill(LOOKUP_DEEP_TARGET);
        await expectLookupPayloadIsBounded(await deepLookup);
        const deepTarget = select.locator('option').filter({ hasText: LOOKUP_DEEP_TARGET });
        await expect(deepTarget).toHaveCount(1);
        const targetId = await deepTarget.getAttribute('value');
        expect(targetId).toBeTruthy();
        await select.selectOption(targetId!);
        await expect(select).toHaveValue(targetId!);
        await expect(lookup).not.toContainText(INTERNAL_SENTINEL);

        const snapshotName = `Issue 66 linked snapshot ${locale} ${testInfo.project.name}`;
        await page.locator('[name="service_slug"]').selectOption('bilbatteri');
        await page.locator('[name="fulfilment"]').selectOption('workshop');
        await page.locator('[name="booking_mode"]').selectOption('manual');
        await page.locator('[name="preferred_date"]').fill('2030-10-20');
        await page.locator('[name="registration_number"]:enabled').fill(`DEEP${locale.toUpperCase()}66`);
        await page.locator('[name="name"]').fill(snapshotName);
        await page.locator('[name="phone"]').fill('98 76 54 32');
        await page.locator('[name="note"]').fill('Synthetic lookup workflow.');
        await page.getByTestId('request-save').click();
        await expect(page).toHaveURL(/\/admin\/service-requests\/\d+$/);
        await expect(page.locator(`a[href$="/admin/clients/${targetId}"]`)).toBeVisible();
        await expect(page.getByText(snapshotName)).toBeVisible();
        await expectNoGlobalOverflow(page);

        await page.goto('/admin/clients');
        const clientFilters = page.getByTestId('client-filters');
        await clientFilters.locator('[name="q"]').fill(REGISTRATION_QUERY);
        await clientFilters.locator('button[type="submit"]').click();

        await expect(page.locator('a:visible').filter({ hasText: LOOKUP_PAGE_TWO_TARGET })).toHaveCount(1);
        await expect(page.getByText('Issue 66 Lookup Client 0001', { exact: true })).toHaveCount(0);
        await expect(page.locator('main')).not.toContainText(INTERNAL_SENTINEL);
        await expectNoGlobalOverflow(page);
    }
});

test('operations completes the filtered workday preparation and safe request-return flow', async ({ page }, testInfo) => {
    expect(page.viewportSize()?.width).toBe(expectedViewportWidth(testInfo.project.name));
    await loginOperations(page);

    for (const locale of ['nb', 'ru'] as const) {
        await ensureAdminLocale(page, locale);
        await page.goto('/admin');
        const workdayEntry = page.getByTestId('admin-nav-operations_day');
        if ((page.viewportSize()?.width ?? 1440) < 1024) await page.getByTestId('admin-menu-toggle').click();
        await workdayEntry.click();
        await expect(page).toHaveURL((url) => url.pathname === '/admin/service-requests/day');
        await expect(page.getByTestId('operations-day-today')).toBeVisible();
        await expect(page.locator('[data-day-resource]')).toHaveCount(5);

        await submitDayFilters(page, DAY, 'all');

        const root = page.locator('[data-operations-day]');
        const items = root.locator('[data-operations-day-item]');
        await expect(root).toBeVisible();
        await expect(items).toHaveCount(4);

        const texts = await items.allTextContents();
        const references = [
            'SR-E2E-DAY-0900',
            'SR-E2E-DAY-1030-VAN1',
            'SR-E2E-DAY-1300-VAN2',
            'SR-E2E-DAY-UNASSIGNED',
        ];
        expect(texts.map((text) => references.find((reference) => text.includes(reference)))).toEqual(references);
        expect(texts[0]).toContain('09:00');
        expect(texts[1]).toContain('10:30');
        expect(texts[2]).toContain('13:00');
        await expect(root).not.toContainText('SR-E2E-DAY-CLOSED-HISTORY');
        await expect(root).not.toContainText('SR-E2E-DAY-CANCELLED-HISTORY');

        const confirmedPreparation = root.locator('[data-preparation-group="confirmed"]');
        const pendingPreparation = root.locator('[data-preparation-group="pending"]');
        await expect(confirmedPreparation.locator('[data-preparation-action]')).toHaveCount(1);
        await expect(confirmedPreparation).toContainText('SR-E2E-DAY-1300-VAN2');
        await expect(confirmedPreparation).not.toContainText('SR-E2E-DAY-1030-VAN1');
        await expect(pendingPreparation.locator('[data-preparation-action]')).toHaveCount(2);
        await expect(pendingPreparation).toContainText('SR-E2E-DAY-1030-VAN1');
        await expect(pendingPreparation).toContainText('SR-E2E-DAY-UNASSIGNED');
        await expect(root.locator('[data-preparation-summary] [data-storage-number-warning]')).toHaveCount(2);
        await expect(root.locator('[data-preparation-summary]')).toContainText(locale === 'ru'
            ? 'Уточнить номер перед подготовкой.'
            : 'Avklar lagernummer før forberedelse.');

        const fiveVehicleVisit = items.filter({ hasText: 'SR-E2E-DAY-1030-VAN1' });
        await expect(fiveVehicleVisit).toContainText('Day vehicle 1');
        await expect(fiveVehicleVisit).toContainText('Day vehicle 5');
        await expect(fiveVehicleVisit).toContainText('00042-A');
        await expect(fiveVehicleVisit).toContainText(locale === 'ru'
            ? 'Выдать хранящийся комплект и не принимать снятый комплект обратно'
            : 'Hent lagret hjulsett; det avmonterte settet skal ikke lagres på nytt');
        await expectNoGlobalOverflow(page);
        await attachOperationsEvidence(page, testInfo, `${locale}-${testInfo.project.name}-active`);

        await submitDayFilters(page, DAY, 'van_1');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-1030-VAN1');
        await expect(page.locator('[data-operations-day-item]')).toContainText('00042-A');
        await expectNoGlobalOverflow(page);

        await submitDayFilters(page, DAY, 'workshop');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-0900');
        await expectNoGlobalOverflow(page);

        await submitDayFilters(page, DAY, 'van_2');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-1300-VAN2');
        await expect(page.locator('[data-operations-day-item]')).not.toContainText('SR-E2E-DAY-CANCELLED-HISTORY');
        await expectNoGlobalOverflow(page);

        const returnContext = `/admin/service-requests/day?date=${DAY}&resource=van_2`;
        await page.locator('[data-operations-day-item]').getByRole('link', {
            name: 'SR-E2E-DAY-1300-VAN2',
            exact: true,
        }).click();
        await expect(page).toHaveURL((url) => url.pathname.match(/^\/admin\/service-requests\/\d+$/) !== null
            && url.searchParams.get('return_to') === returnContext);
        await expect(page.locator('main')).toContainText('Issue 66 request snapshot');
        await expect(page.locator('main')).toContainText('90006642');

        await page.getByTestId('request-edit').click();
        await expect(page).toHaveURL((url) => url.pathname.match(/^\/admin\/service-requests\/\d+\/edit$/) !== null
            && url.searchParams.get('return_to') === returnContext);
        await expect(page.locator('[name="name"]')).toHaveValue('Issue 66 request snapshot');
        await page.locator('[name="phone"]').fill('not-a-phone');
        await page.getByTestId('request-save').click();
        await expect(page.getByTestId('request-form-errors')).toBeVisible();
        await expect(page).toHaveURL((url) => url.pathname.match(/^\/admin\/service-requests\/\d+\/edit$/) !== null
            && url.searchParams.get('return_to') === returnContext);
        await expect(page.locator('[name="name"]')).toHaveValue('Issue 66 request snapshot');

        await page.locator('[name="phone"]').fill('90006642');
        await page.locator('[name="status"]').selectOption('contacted');
        await page.getByTestId('request-save').click();
        await expect(page).toHaveURL((url) => url.pathname.match(/^\/admin\/service-requests\/\d+$/) !== null
            && url.searchParams.get('return_to') === returnContext);
        await expect(page.locator('main')).toContainText('Issue 66 request snapshot');
        await expect(page.locator('main')).toContainText('90006642');
        await page.getByTestId('request-back').click();
        await expect(page).toHaveURL((url) => url.pathname === '/admin/service-requests/day'
            && url.searchParams.get('date') === DAY
            && url.searchParams.get('resource') === 'van_2');
        await expectNoGlobalOverflow(page);

        await submitDayFilters(page, DAY, 'mobile_unassigned');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-UNASSIGNED');
        await expectNoGlobalOverflow(page);

        const filters = page.locator('[data-operations-day-filters]');
        await filters.locator('[name="include_history"]').check();
        await submitDayFilters(page, DAY, 'all');
        expect(new URL(page.url()).searchParams.get('include_history')).toBe('1');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(6);
        const preparation = page.locator('[data-preparation-summary]');
        await expect(preparation.locator('[data-preparation-action]')).toHaveCount(3);
        await expect(preparation).not.toContainText('SR-E2E-DAY-CLOSED-HISTORY');
        await expect(preparation).not.toContainText('SR-E2E-DAY-CANCELLED-HISTORY');
        const historyItems = page.locator('[data-operations-day-item][data-status="closed"], [data-operations-day-item][data-status="cancelled"]');
        await expect(historyItems).toHaveCount(2);
        await expect(historyItems.locator('[data-history-no-actions]')).toHaveCount(2);
        await expectNoGlobalOverflow(page);
        await attachOperationsEvidence(page, testInfo, `${locale}-${testInfo.project.name}-history`);

        await page.getByTestId('operations-day-undated').click();
        await expect(page).toHaveURL((url) => url.pathname === '/admin/service-requests'
            && url.searchParams.get('without_date') === '1');
        await expect(page.locator('main')).toContainText('SR-E2E-DAY-UNDATED');
        await expect(page.locator('main')).not.toContainText('SR-E2E-DAY-0900');
        await expectNoGlobalOverflow(page);

        await page.goto(`/admin/service-requests/day?date=${NEXT_DAY}&resource=all`);
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-NEXTDATE');
        await expect(page.locator('[data-operations-day-item]')).not.toContainText('SR-E2E-DAY-0900');
        await expectNoGlobalOverflow(page);
    }
});
