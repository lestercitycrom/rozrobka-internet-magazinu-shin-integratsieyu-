import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isRussianProject, localePrefix, overlapArea } from './helpers';

const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-47');

const composition = [
    '[data-testid="home-hero"]',
    '[data-testid="home-dekkhotell"]',
    '[data-testid="home-prices"]',
    '[data-testid="homepage-promotions"]',
    '[data-testid="homepage-additional-services"]',
    '[data-testid="home-process"]',
    '[data-testid="homepage-wheel-services"]',
    '[data-testid="homepage-reviews"]',
    '.et-v3-contact',
    '.et-footer',
];

const viewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 1280, height: 800 },
    { width: 800, height: 900 },
    { width: 390, height: 844 },
    { width: 320, height: 800 },
];

async function tabToFromPageStart(page: import('@playwright/test').Page, selector: string, maximumTabs = 100): Promise<void> {
    for (let index = 0; index < maximumTabs; index += 1) {
        await page.keyboard.press('Tab');
        if (await page.locator(selector).evaluate((element) => element === document.activeElement)) return;
    }

    throw new Error(`Keyboard focus did not reach ${selector} from the document start.`);
}

test('customer-approved homepage composition, copy and local partner media render in both QA locales', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = isRussianProject(testInfo.project.name);
    await page.goto(prefix || '/');

    const positions = await page.evaluate((selectors) => selectors.map((selector) => {
        const element = document.querySelector<HTMLElement>(selector);
        return { selector, top: element?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY };
    }), composition);
    positions.forEach(({ top, selector }) => expect(top, `${selector} must exist`).toBeLessThan(Number.POSITIVE_INFINITY));
    for (let index = 1; index < positions.length; index += 1) {
        expect(positions[index].top, `${positions[index].selector} must follow ${positions[index - 1].selector}`).toBeGreaterThan(positions[index - 1].top);
    }

    await expect(page.getByTestId('catalog-foundation')).toHaveCount(0);
    await expect(page.getByTestId('home-dekkhotell')).toContainText(russian ? 'Шинный отель без хлопот' : 'Dekkhotell uten stress');
    await expect(page.getByTestId('home-process')).toContainText(russian ? 'Оплатите после выполнения' : 'Betal etterpå');
    await expect(page.getByTestId('home-process')).toContainText(russian ? 'Счастливого пути — берегите себя!' : 'Kjør trygt og ta godt vare på deg!');
    await expect(page.getByTestId('home-process')).toContainText(russian ? 'Заказать сейчас!' : 'Bestill nå!');
    await expect(page.getByTestId('home-prices')).toContainText('700 kr');
    await expect(page.getByTestId('business-contact-card')).toContainText(russian ? 'Локальный партнёр для компаний с автопарком' : 'En lokal partner for bedrifter med bilpark');
    await expect(page.locator('.et-v3-contact__line').filter({ hasText: 'Ringvegen 200' })).toContainText('Ringvegen 202');

    const partners = page.getByTestId('homepage-business-partners');
    await expect(partners.locator('a')).toHaveCount(4);
    await partners.scrollIntoViewIfNeeded();
    for (const [name, href] of [
        ['Nordic Spas', 'https://www.nordicspas.no/'],
        ['Element NOR', 'https://elementnor.no/'],
        ['Carsoe', 'https://carsoe.com/'],
        ['Avis', 'https://www.avis.no/'],
    ]) {
        const partner = partners.locator(`a[aria-label="${name}"]`);
        await expect(partner).toHaveAttribute('href', href);
        const image = partner.locator('img');
        await expect(image).toBeVisible();
        await expect.poll(
            () => image.evaluate((node: HTMLImageElement) => node.naturalWidth),
            { message: `${name} logo must load from local storage` },
        ).toBeGreaterThan(0);
        await expect(image).toHaveAttribute('src', /^\/storage\/media\/homepage\/issue-47\//);
    }
    await expectNoGlobalOverflow(page);
});

test('homepage card overlay is keyboard operable and returns focus', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    const russian = isRussianProject(testInfo.project.name);
    await page.goto(prefix || '/');
    const opener = page.locator('[data-dekkhotell-card-overlay-open]');
    const dialog = page.locator('[data-dekkhotell-card-overlay]');
    await tabToFromPageStart(page, '[data-dekkhotell-card-overlay-open]');
    await expect(opener).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(dialog).toHaveJSProperty('open', true);
    const [headerBox, dialogBox] = await Promise.all([
        page.locator('[data-site-header]').boundingBox(),
        dialog.boundingBox(),
    ]);
    expect(headerBox).not.toBeNull();
    expect(dialogBox).not.toBeNull();
    expect(dialogBox!.y).toBeGreaterThanOrEqual(headerBox!.y + headerBox!.height);
    await expect(dialog).toContainText(russian
        ? 'С шинным отелем EasyTire вам не придётся самостоятельно поднимать, перевозить или хранить колёса.'
        : 'Med dekkhotell hos EasyTire slipper du å løfte, frakte eller oppbevare hjulene selv.');
    await expect(dialog.locator('[data-card-overlay-close]')).toBeFocused();
    for (let index = 0; index < 5; index += 1) {
        await page.keyboard.press('Tab');
        expect(await dialog.evaluate((element) => element === document.activeElement || element.contains(document.activeElement))).toBeTruthy();
    }
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveJSProperty('open', false);
    await expect(opener).toBeFocused();
});

test('homepage video lazy-loads muted, can be paused and allows manual play with reduced motion', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic media pass is sufficient');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = page.locator('[data-homepage-video-element]');
    const control = page.locator('[data-homepage-video-control]');
    await expect(video).toBeVisible();
    await expect(video).toHaveJSProperty('muted', true);
    await expect(video).toHaveAttribute('preload', 'none');
    await expect(video).not.toHaveAttribute('autoplay', '');
    await expect(video).not.toHaveAttribute('src', /.+/);
    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.readyState)).toBeGreaterThanOrEqual(2);
    await expect.poll(() => root.evaluate((element) => element.classList.contains('is-video-playing'))).toBeTruthy();
    await control.click();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBeTruthy();
    await expect(control).toHaveAttribute('aria-pressed', 'true');
    await control.click();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBeFalsy();

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBeTruthy();
    await page.reload();
    await root.scrollIntoViewIfNeeded();
    await expect(video).not.toHaveAttribute('src', /.+/);
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBeTruthy();
    await expect(control).toHaveAttribute('aria-pressed', 'true');
    await control.click();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.readyState)).toBeGreaterThanOrEqual(2);
    await expect.poll(() => video.evaluate((node: HTMLVideoElement) => node.paused)).toBeFalsy();
});

test('homepage video failure keeps the poster and copy usable', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic media failure pass is sufficient');
    await page.route('**/homepage-loop.mp4*', (route) => route.abort('connectionfailed'));
    await page.goto('/');
    const root = page.locator('[data-homepage-video]');
    const video = root.locator('[data-homepage-video-element]');
    await root.scrollIntoViewIfNeeded();
    await expect.poll(() => video.getAttribute('src')).toBeTruthy();
    await expect(root).toHaveClass(/has-video-error/, { timeout: 10_000 });
    const poster = root.locator('.et-v3-logistics-media__poster');
    await expect(poster).toBeVisible();
    await expect.poll(() => poster.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
    await expect(video).toBeHidden();
    await expect(root.locator('[data-homepage-video-control]')).toBeHidden();
    await expect(page.getByTestId('home-dekkhotell')).toContainText('Vi ordner hele hjulskiftet.');
});

test('homepage cards and five-step timeline keep their intended responsive structure', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(180_000);
    const measurements: Record<string, unknown>[] = [];
    if (process.env.CAPTURE_ISSUE_47 === '1') await mkdir(evidenceRoot, { recursive: true });

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        const steps = page.locator('.et-v3-process__step');
        const processGrid = page.locator('.et-v3-process');
        const partnerGrid = page.getByTestId('homepage-business-partners').locator(':scope > div');
        await expect(steps).toHaveCount(5);
        await expectNoGlobalOverflow(page);
        const columns = await processGrid.evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
        expect(columns).toBe(viewport.width <= 900 ? 1 : 5);
        const partnerColumns = await partnerGrid.evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
        expect(partnerColumns).toBe(2);
        const stepGeometry = await steps.evaluateAll((nodes) => nodes.map((node) => {
            const rect = node.getBoundingClientRect();
            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                clientHeight: node.clientHeight,
                scrollHeight: node.scrollHeight,
            };
        }));
        const expectedRows = columns === 1 ? 5 : 1;
        expect(new Set(stepGeometry.map(({ y }) => Math.round(y))).size).toBe(expectedRows);
        expect(stepGeometry.every(({ height, clientHeight, scrollHeight }) => height > 0 && scrollHeight <= clientHeight + 1)).toBeTruthy();
        for (let first = 0; first < stepGeometry.length; first += 1) {
            for (let second = first + 1; second < stepGeometry.length; second += 1) {
                expect(overlapArea(stepGeometry[first], stepGeometry[second])).toBe(0);
            }
        }
        measurements.push({ viewport, columns, partnerColumns, stepGeometry });

        if (process.env.CAPTURE_ISSUE_47 === '1') {
            await page.screenshot({ path: path.join(evidenceRoot, `homepage-${viewport.width}x${viewport.height}.jpg`), type: 'jpeg', quality: 82, fullPage: true });
            if ([1440, 390, 320].includes(viewport.width)) {
                await page.getByTestId('business-contact-card').screenshot({ path: path.join(evidenceRoot, `business-partners-${viewport.width}.png`) });
            }
        }
    }
    if (process.env.CAPTURE_ISSUE_47 === '1') {
        await writeFile(path.join(evidenceRoot, 'homepage-layout.json'), `${JSON.stringify(measurements, null, 2)}\n`);
    }
});
