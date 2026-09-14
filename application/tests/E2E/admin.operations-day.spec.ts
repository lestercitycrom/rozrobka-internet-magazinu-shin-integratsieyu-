import { expect, test, type Page, type Response } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginOperations } from './helpers';

const LOOKUP_QUERY = 'Issue 66 Lookup Client';
const LOOKUP_PAGE_TWO_TARGET = 'Issue 66 Lookup Client 0021';
const LOOKUP_DEEP_TARGET = 'Issue 66 Lookup Client 1001';
const REGISTRATION_QUERY = 'ISSUE66REG';
const INTERNAL_SENTINEL = 'ISSUE66_INTERNAL_NOTE_MUST_NOT_LEAK';
const DAY = '2030-10-15';
const NEXT_DAY = '2030-10-16';

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

test('operations day keeps chronology and multi-vehicle LAST context across dates and resources', async ({ page }, testInfo) => {
    expect(page.viewportSize()?.width).toBe(expectedViewportWidth(testInfo.project.name));
    await loginOperations(page);

    for (const locale of ['nb', 'ru'] as const) {
        await ensureAdminLocale(page, locale);
        await page.goto(`/admin/service-requests/day?date=${DAY}&resource=all`);

        const root = page.locator('[data-operations-day]');
        const items = root.locator('[data-operations-day-item]');
        await expect(root).toBeVisible();
        await expect(items).toHaveCount(3);

        const texts = await items.allTextContents();
        const references = ['SR-E2E-DAY-0900', 'SR-E2E-DAY-1030-VAN1', 'SR-E2E-DAY-1300-VAN2'];
        expect(texts.map((text) => references.find((reference) => text.includes(reference)))).toEqual(references);
        expect(texts[0]).toContain('09:00');
        expect(texts[1]).toContain('10:30');
        expect(texts[2]).toContain('13:00');

        const fiveVehicleVisit = items.filter({ hasText: 'SR-E2E-DAY-1030-VAN1' });
        await expect(fiveVehicleVisit).toContainText('Day vehicle 1');
        await expect(fiveVehicleVisit).toContainText('Day vehicle 5');
        await expect(fiveVehicleVisit).toContainText('00042-A');
        await expect(fiveVehicleVisit).toContainText(locale === 'ru'
            ? 'Выдать хранящийся комплект и не принимать снятый комплект обратно'
            : 'Hent lagret hjulsett; det avmonterte settet skal ikke lagres på nytt');
        await expectNoGlobalOverflow(page);

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
        await expectNoGlobalOverflow(page);

        await submitDayFilters(page, NEXT_DAY, 'all');
        await expect(page.locator('[data-operations-day-item]')).toHaveCount(1);
        await expect(page.locator('[data-operations-day-item]')).toContainText('SR-E2E-DAY-NEXTDATE');
        await expect(page.locator('[data-operations-day-item]')).not.toContainText('SR-E2E-DAY-0900');
        await expectNoGlobalOverflow(page);
    }
});
