import { expect, test, type Locator, type Page } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expectNoGlobalOverflow } from './helpers';

const expectedServices = [
    'mobilt-dekkskift',
    'dekkverksted',
    'tesla-og-elbil',
    'bobil-og-varebil',
    'dekkhotell',
    'dekk-felg-og-hjulpakker',
    'omlegging-balansering',
    'dekkreparasjon',
    'tpms-sensor',
    'oljeskift',
    'bilbatteri',
    'bildiagnose',
    'lyspaerer-viskerblad',
] as const;

const captureEvidence = process.env.PLAYWRIGHT_CAPTURE_ISSUE_31 === '1';
const evidenceRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../evidence/issue-31');

async function settle(page: Page): Promise<void> {
    await page.waitForLoadState('domcontentloaded');
    await page.evaluate(() => document.fonts.ready);
}

async function capture(page: Page, file: string): Promise<void> {
    if (!captureEvidence) return;
    await mkdir(evidenceRoot, { recursive: true });
    await page.screenshot({ path: path.join(evidenceRoot, file), type: 'jpeg', quality: 84, fullPage: true });
}

async function captureViewport(page: Page, file: string): Promise<void> {
    if (!captureEvidence) return;
    await mkdir(evidenceRoot, { recursive: true });
    await page.screenshot({ path: path.join(evidenceRoot, file), type: 'jpeg', quality: 84 });
}

async function tabTo(page: Page, target: Locator, maximum = 80): Promise<number> {
    await expect(target).toBeVisible();

    for (let index = 1; index <= maximum; index += 1) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((element) => element === document.activeElement)) return index;
    }

    throw new Error(`Target was not reached by Tab traversal: ${await target.evaluate((element) => element.outerHTML)}`);
}

async function focusPresentation(target: Locator): Promise<{
    outlineWidth: string;
    outlineStyle: string;
    outlineColor: string;
    outlineOffset: string;
    boxShadow: string;
}> {
    return target.evaluate((element) => {
        const style = getComputedStyle(element);

        return {
            outlineWidth: style.outlineWidth,
            outlineStyle: style.outlineStyle,
            outlineColor: style.outlineColor,
            outlineOffset: style.outlineOffset,
            boxShadow: style.boxShadow,
        };
    });
}

function hasVisibleOutline(state: Awaited<ReturnType<typeof focusPresentation>>): boolean {
    return state.outlineStyle !== 'none' && Number.parseFloat(state.outlineWidth) > 0;
}

function hasVisibleShadow(state: Awaited<ReturnType<typeof focusPresentation>>): boolean {
    return state.boxShadow !== 'none';
}

async function documentBox(target: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
    return target.evaluate((element) => {
        const rect = element.getBoundingClientRect();

        return {
            x: rect.x + scrollX,
            y: rect.y + scrollY,
            width: rect.width,
            height: rect.height,
        };
    });
}

test('the thirteen-service index is image-led, deterministic, accessible and compact at every required viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop');
    test.setTimeout(180_000);

    const viewports = [
        { width: 1440, height: 900 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ];
    const records: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/tjenester');
        await settle(page);

        const catalog = page.getByTestId('services-catalog');
        const cards = page.getByTestId('service-card');
        await expect(catalog).toBeVisible();
        await expect(cards).toHaveCount(13);
        await expectNoGlobalOverflow(page);

        const inventory = await cards.evaluateAll((elements) => elements.map((element) => {
            const link = element.querySelector<HTMLAnchorElement>('.et-service-card__content h2 > a');
            const rect = element.getBoundingClientRect();

            return {
                slug: element.getAttribute('data-service-slug'),
                kind: element.getAttribute('data-media-kind'),
                source: element.getAttribute('data-media-source'),
                href: link?.getAttribute('href'),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            };
        }));

        expect(inventory.map((item) => item.slug)).toEqual(expectedServices);
        expect(new Set(inventory.map((item) => item.href)).size).toBe(13);
        expect(inventory.filter((item) => item.kind === 'repository')).toHaveLength(9);
        expect(inventory.filter((item) => item.kind === 'fallback')).toHaveLength(4);
        expect(inventory.every((item) => Boolean(item.source))).toBeTruthy();

        if (viewport.width <= 390) {
            expect(Math.max(...inventory.map((item) => item.height))).toBeLessThanOrEqual(230);
            expect(Math.min(...inventory.map((item) => item.width))).toBeGreaterThanOrEqual(viewport.width - 34);
        }

        const firstCard = cards.first();
        const firstLink = firstCard.locator('.et-service-card__content h2 > a');
        const before = await firstCard.boundingBox();
        await firstLink.hover();
        const after = await firstCard.boundingBox();
        expect(after?.width).toBe(before?.width);
        expect(after?.height).toBe(before?.height);

        records.push({ viewport, inventory });
        await capture(page, `after-services-index-${viewport.width}x${viewport.height}.jpg`);
    }

    if (captureEvidence) {
        await writeFile(path.join(evidenceRoot, 'service-media-inventory.json'), JSON.stringify(records, null, 2));
    }
});

test('service offers expose exactly one focus treatment through real keyboard traversal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop');
    test.setTimeout(120_000);

    const records: Record<string, unknown>[] = [];
    const cases = [
        { viewport: { width: 1440, height: 900 }, type: 'featured', selector: '.et-service-offer--feature' },
        { viewport: { width: 1440, height: 900 }, type: 'regular', selector: '.et-service-offer:not(.et-service-offer--feature)' },
        { viewport: { width: 390, height: 844 }, type: 'featured', selector: '.et-service-offer--feature' },
        { viewport: { width: 390, height: 844 }, type: 'regular', selector: '.et-service-offer:not(.et-service-offer--feature)' },
    ];

    for (const focusCase of cases) {
        await page.setViewportSize(focusCase.viewport);
        await page.goto('/tjenester');
        await settle(page);

        const card = page.locator(focusCase.selector).first();
        const link = card.locator('.et-service-card__content h2 > a');
        const before = await documentBox(card);
        const keyboardTabs = await tabTo(page, link);
        await expect(link).toBeFocused();
        await expectNoGlobalOverflow(page);

        const after = await documentBox(card);
        const linkState = await focusPresentation(link);
        const cardState = await focusPresentation(card);
        const visibleTreatments = [
            hasVisibleOutline(linkState),
            hasVisibleShadow(linkState),
            hasVisibleOutline(cardState),
            hasVisibleShadow(cardState),
        ].filter(Boolean);

        expect(linkState.outlineWidth).toBe('3px');
        expect(linkState.outlineStyle).toBe('solid');
        expect(linkState.outlineColor).toBe('rgb(159, 0, 0)');
        expect(linkState.outlineOffset).toBe('3px');
        expect(linkState.boxShadow).toBe('none');
        expect(cardState.outlineStyle).toBe('none');
        expect(hasVisibleOutline(cardState)).toBe(false);
        expect(cardState.boxShadow).toBe('none');
        expect(visibleTreatments).toHaveLength(1);
        expect(after.x).toBeCloseTo(before.x, 2);
        expect(after.y).toBeCloseTo(before.y, 2);
        expect(after.width).toBeCloseTo(before.width, 2);
        expect(after.height).toBeCloseTo(before.height, 2);

        const evidenceName = `focus-service-${focusCase.type}-${focusCase.viewport.width}x${focusCase.viewport.height}.jpg`;
        await link.scrollIntoViewIfNeeded();
        await expect(link).toBeFocused();
        await captureViewport(page, evidenceName);
        records.push({ ...focusCase, keyboardTabs, linkState, cardState, before, after, evidenceName });
    }

    if (captureEvidence) {
        await writeFile(path.join(evidenceRoot, 'service-focus-contract.json'), JSON.stringify(records, null, 2));
    }
});

test('service detail and request transitions keep one CTA hierarchy, balanced reading widths and localized form behaviour', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop');
    test.setTimeout(240_000);

    const detailViewports = [
        { width: 1440, height: 900 },
        { width: 1366, height: 768 },
        { width: 1280, height: 800 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ];
    const geometry: Record<string, unknown>[] = [];

    for (const viewport of detailViewports) {
        await page.setViewportSize(viewport);
        await page.goto('/bilbatteri-tromso');
        await settle(page);
        await expectNoGlobalOverflow(page);

        const top = page.locator('.et-service-detail__top');
        const intro = page.locator('.et-service-detail__intro');
        const aside = page.locator('.et-service-conversion');
        const continuation = page.locator('.et-service-detail__continuation');
        const finalAction = page.locator('.et-service-final-action');
        await expect(top).toBeVisible();
        await expect(intro).toBeVisible();
        await expect(aside).toBeVisible();
        await expect(continuation).toBeVisible();
        await expect(finalAction).toBeVisible();
        await expect(page.locator('.et-service-anchor .et-btn')).toHaveCount(0);
        await expect(page.locator('main a.et-btn--primary[href$="/tjenester/bilbatteri/foresporsel"]')).toHaveCount(2);
        await expect(page.locator('.et-service-conversion a.et-btn--outline[href$="/tjenester/bilbatteri/foresporsel"]')).toHaveCount(1);

        const boxes = await page.evaluate(() => {
            const box = (selector: string) => {
                const rect = document.querySelector<HTMLElement>(selector)?.getBoundingClientRect();
                return rect ? { x: rect.x, y: rect.y + scrollY, width: rect.width, height: rect.height } : null;
            };
            const primaryPositions = Array.from(document.querySelectorAll<HTMLElement>('main a.et-btn--primary[href$="/tjenester/bilbatteri/foresporsel"]'))
                .map((element) => element.getBoundingClientRect().y + scrollY);

            return {
                top: box('.et-service-detail__top'),
                intro: box('.et-service-detail__intro'),
                aside: box('.et-service-conversion'),
                continuation: box('.et-service-detail__continuation'),
                finalAction: box('.et-service-final-action'),
                primaryPositions,
            };
        });

        expect(boxes.top).not.toBeNull();
        expect(boxes.intro).not.toBeNull();
        expect(boxes.aside).not.toBeNull();
        expect(boxes.continuation).not.toBeNull();
        if (viewport.width >= 1280) {
            expect((boxes.intro?.width ?? 0) / (boxes.aside?.width ?? 1)).toBeGreaterThan(1.6);
            expect(Math.abs((boxes.intro?.y ?? 0) - (boxes.aside?.y ?? 0))).toBeLessThanOrEqual(2);
            expect((boxes.continuation?.width ?? 0) / (boxes.top?.width ?? 1)).toBeGreaterThan(.64);
            expect(boxes.primaryPositions.filter((y) => y < viewport.height)).toHaveLength(1);
        } else {
            expect(boxes.intro?.y ?? 0).toBeLessThan(boxes.aside?.y ?? 0);
            expect(boxes.aside?.y ?? 0).toBeLessThan(boxes.continuation?.y ?? 0);
        }

        geometry.push({ viewport, ...boxes });
        await capture(page, `after-service-detail-${viewport.width}x${viewport.height}.jpg`);
        await page.evaluate(() => scrollTo(0, 0));
        await captureViewport(page, `after-battery-first-fold-${viewport.width}x${viewport.height}.jpg`);
        await finalAction.scrollIntoViewIfNeeded();
        await captureViewport(page, `after-battery-final-action-${viewport.width}x${viewport.height}.jpg`);

        await page.goto('/tjenester/dekkverksted');
        await settle(page);
        await expect(page.locator('.et-service-detail__continuation')).toHaveCount(0);
        await expect(page.locator('.et-service-final-action')).toBeVisible();
        await expectNoGlobalOverflow(page);
    }

    const requestCases = [
        { path: '/tjenester/bilbatteri/foresporsel', locale: 'nb', width: 1440, height: 900 },
        { path: '/ru/tjenester/bilbatteri/foresporsel', locale: 'ru', width: 1440, height: 900 },
        { path: '/tjenester/bilbatteri/foresporsel', locale: 'nb', width: 390, height: 844 },
        { path: '/ru/tjenester/bilbatteri/foresporsel', locale: 'ru', width: 320, height: 800 },
    ];

    for (const requestCase of requestCases) {
        await page.setViewportSize({ width: requestCase.width, height: requestCase.height });
        await page.goto(requestCase.path);
        await settle(page);

        const context = page.getByTestId('request-service-context');
        const form = page.getByTestId('generic-service-request');
        const card = page.locator('.et-request-form-card');
        await expect(context).toBeVisible();
        await expect(context).toContainText(requestCase.locale === 'ru' ? /аккумулятор/i : /Bilbatteri/i);
        await expect(context).toHaveAttribute('data-media-source', /.+/);
        await expect(form).toBeVisible();
        await expect(card).toBeVisible();
        await expectNoGlobalOverflow(page);

        const contextBox = await context.boundingBox();
        const cardBox = await card.boundingBox();
        if (requestCase.width >= 1200) {
            expect(contextBox?.x ?? 0).toBeGreaterThan((cardBox?.x ?? 0) + (cardBox?.width ?? 0));
            expect(Math.abs((contextBox?.y ?? 0) - (cardBox?.y ?? 0))).toBeLessThanOrEqual(2);
        } else {
            expect(contextBox?.y ?? 0).toBeGreaterThanOrEqual((cardBox?.y ?? 0) + (cardBox?.height ?? 0));
        }

        await capture(page, `after-request-${requestCase.locale}-${requestCase.width}x${requestCase.height}-default.jpg`);
        await form.locator('[name="vehicle"]').fill(requestCase.locale === 'ru' ? 'Тестовый автомобиль' : 'Testbil');
        await form.evaluate((element) => {
            const formElement = element as HTMLFormElement;
            formElement.noValidate = true;
            formElement.requestSubmit();
        });
        await expect(page.getByTestId('request-errors')).toBeVisible();
        await expect(form.locator('[name="vehicle"]')).toHaveValue(requestCase.locale === 'ru' ? 'Тестовый автомобиль' : 'Testbil');
        await expect(form.locator('[name="registration_number"]')).toBeFocused();
        await expectNoGlobalOverflow(page);
        await capture(page, `after-request-${requestCase.locale}-${requestCase.width}x${requestCase.height}-error.jpg`);
    }

    if (captureEvidence) {
        await writeFile(path.join(evidenceRoot, 'service-detail-geometry.json'), JSON.stringify(geometry, null, 2));
    }
});
