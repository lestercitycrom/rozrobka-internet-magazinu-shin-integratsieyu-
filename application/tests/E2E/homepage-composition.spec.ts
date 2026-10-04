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

test('homepage visual review contracts and five-step timeline keep their intended responsive structure', async ({ page }, testInfo) => {
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
        const priceEntries = page.locator('.et-home-prices__list > a');
        await expect(steps).toHaveCount(5);
        await expect(processGrid).toHaveClass(/\bet-v3-process--timeline\b/);
        await expect(steps.locator('.et-v3-process__icon')).toHaveCount(0);
        await expect(priceEntries).toHaveCount(3);
        await expectNoGlobalOverflow(page);
        if (viewport.width === 320) {
            await expect.poll(() => page.locator('body.storefront[data-storefront-design="modern"]').evaluate(
                (element) => getComputedStyle(element).minWidth,
            )).toBe('0px');
        }
        const columns = await processGrid.evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
        expect(columns).toBe(viewport.width <= 900 ? 1 : 5);
        const partnerColumns = await partnerGrid.evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
        expect(partnerColumns).toBe(2);
        const reviewMetrics = await page.evaluate(() => {
            const required = <T extends Element>(selector: string): T => {
                const element = document.querySelector<T>(selector);
                if (!element) throw new Error(`Missing homepage review-contract element: ${selector}`);
                return element;
            };
            const renderedLineCount = (element: Element): number => {
                const range = document.createRange();
                range.selectNodeContents(element);
                const lines = new Set([...range.getClientRects()]
                    .filter(rect => rect.width > 0 && rect.height > 0)
                    .map(rect => Math.round(rect.top * 2) / 2));
                return lines.size;
            };
            const typeAndFit = (element: HTMLElement) => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return {
                    family: style.fontFamily,
                    size: Number.parseFloat(style.fontSize),
                    weight: Number.parseFloat(style.fontWeight),
                    lineHeight: Number.parseFloat(style.lineHeight),
                    overflowX: style.overflowX,
                    overflowY: style.overflowY,
                    whiteSpace: style.whiteSpace,
                    lines: renderedLineCount(element),
                    left: rect.left,
                    right: rect.right,
                    width: rect.width,
                    clientWidth: element.clientWidth,
                    scrollWidth: element.scrollWidth,
                    clientHeight: element.clientHeight,
                    scrollHeight: element.scrollHeight,
                };
            };

            const hero = required<HTMLElement>('.et-v3-hero .et-v3-display');
            const priceHeading = required<HTMLElement>('.et-home-prices header h2');
            const priceKicker = required<HTMLElement>('.et-home-prices header .et-eyebrow');
            const firstPrice = required<HTMLElement>('.et-home-prices__list strong');
            const firstPriceLabel = required<HTMLElement>('.et-home-prices__list > a > span');
            const priceList = required<HTMLElement>('.et-home-prices__list');
            const reviewHeading = required<HTMLElement>("[data-testid='homepage-reviews'] .et-h2");
            const reviewCards = [...document.querySelectorAll<HTMLElement>("[data-testid='homepage-review']")];
            const businessHeading = required<HTMLElement>('.et-v3-contact__business-copy h3');
            const businessBody = required<HTMLElement>('.et-v3-contact__business-copy > p:not(.et-eyebrow)');
            const listStyle = getComputedStyle(priceList);

            return {
                viewportWidth: document.documentElement.clientWidth,
                hero: typeAndFit(hero),
                priceHeading: typeAndFit(priceHeading),
                priceKicker: typeAndFit(priceKicker),
                firstPrice: typeAndFit(firstPrice),
                firstPriceLabel: typeAndFit(firstPriceLabel),
                priceList: {
                    display: listStyle.display,
                    overflowX: listStyle.overflowX,
                    columns: listStyle.gridTemplateColumns.split(' ').filter(Boolean).length,
                    clientWidth: priceList.clientWidth,
                    scrollWidth: priceList.scrollWidth,
                },
                priceEntries: [...document.querySelectorAll<HTMLAnchorElement>('.et-home-prices__list > a')].map(link => ({
                    href: link.getAttribute('href'),
                    amount: link.querySelector('strong')?.textContent?.replace(/\s+/g, ' ').trim() ?? '',
                })),
                reviewHeading: typeAndFit(reviewHeading),
                reviewCards: reviewCards.map(card => {
                    const style = getComputedStyle(card);
                    const channels = (style.borderTopColor.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
                    return {
                        width: Number.parseFloat(style.borderTopWidth),
                        style: style.borderTopStyle,
                        dark: channels.length === 3 && channels.every(channel => channel <= 32),
                    };
                }),
                businessHeading: typeAndFit(businessHeading),
                businessBody: typeAndFit(businessBody),
            };
        });

        expect(reviewMetrics.priceHeading.family).toBe(reviewMetrics.hero.family);
        expect(reviewMetrics.priceHeading.weight).toBe(800);
        expect(reviewMetrics.priceHeading.size).toBeGreaterThan(reviewMetrics.priceKicker.size);
        expect(reviewMetrics.firstPrice.family).toBe(reviewMetrics.hero.family);
        expect(reviewMetrics.firstPrice.weight).toBeGreaterThanOrEqual(700);
        expect(reviewMetrics.firstPrice.size).toBeGreaterThan(reviewMetrics.firstPriceLabel.size);
        expect(reviewMetrics.priceEntries.every(entry => entry.href && /\d[\d\s]*kr/i.test(entry.amount))).toBe(true);

        expect(reviewMetrics.reviewHeading.family).toBe(reviewMetrics.hero.family);
        expect(reviewMetrics.reviewHeading.weight).toBe(800);
        expect(reviewMetrics.reviewHeading.whiteSpace).not.toBe('nowrap');
        expect(reviewMetrics.reviewHeading.scrollWidth).toBeLessThanOrEqual(reviewMetrics.reviewHeading.clientWidth + 1);
        expect(reviewMetrics.reviewHeading.right).toBeLessThanOrEqual(reviewMetrics.viewportWidth + 1);
        expect(reviewMetrics.reviewCards.length).toBeGreaterThan(0);
        expect(reviewMetrics.reviewCards.every(card => card.width === 3 && card.style === 'solid' && card.dark)).toBe(true);

        expect(reviewMetrics.businessHeading.family).toBe(reviewMetrics.hero.family);
        expect(reviewMetrics.businessHeading.weight).toBe(800);
        expect(reviewMetrics.businessHeading.size).toBeGreaterThan(reviewMetrics.businessBody.size * 1.5);
        expect(reviewMetrics.businessHeading.scrollWidth).toBeLessThanOrEqual(reviewMetrics.businessHeading.clientWidth + 1);
        expect(reviewMetrics.businessHeading.lineHeight).toBeGreaterThan(reviewMetrics.businessHeading.size * .9);
        expect(reviewMetrics.businessHeading.overflowX).toBe('visible');
        expect(reviewMetrics.businessHeading.overflowY).toBe('visible');
        expect(reviewMetrics.businessHeading.left).toBeGreaterThanOrEqual(-1);
        expect(reviewMetrics.businessHeading.right).toBeLessThanOrEqual(reviewMetrics.viewportWidth + 1);

        if (viewport.width >= 1440) {
            expect(reviewMetrics.priceList.display).toBe('grid');
            expect(reviewMetrics.priceList.columns).toBe(3);
            expect(reviewMetrics.reviewHeading.lines).toBe(1);
            expect(reviewMetrics.businessHeading.lines).toBe(2);
        } else if (viewport.width <= 390) {
            expect(reviewMetrics.priceList.display).toBe('flex');
            expect(reviewMetrics.priceList.overflowX).toBe('auto');
            expect(reviewMetrics.priceList.scrollWidth).toBeGreaterThan(reviewMetrics.priceList.clientWidth);
        }
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

        // Exercise native lazy loading before recording review evidence. A full-page
        // screenshot alone is not a reliable loading signal at very narrow widths.
        const businessCard = page.getByTestId('business-contact-card');
        const partnerLogos = page.getByTestId('homepage-business-partners').locator('img');
        await expect(partnerLogos).toHaveCount(4);
        await businessCard.scrollIntoViewIfNeeded();
        await expect.poll(() => partnerLogos.evaluateAll((images) => images.every((image) => (
            image instanceof HTMLImageElement && image.complete && image.naturalWidth > 0
        ))), { message: `Partner logos did not finish loading at ${viewport.width}px` }).toBe(true);

        if (process.env.CAPTURE_ISSUE_47 === '1') {
            await page.screenshot({ path: path.join(evidenceRoot, `homepage-${viewport.width}x${viewport.height}.jpg`), type: 'jpeg', quality: 82, fullPage: true });
            if ([1440, 390, 320].includes(viewport.width)) {
                await businessCard.screenshot({ path: path.join(evidenceRoot, `business-partners-${viewport.width}.png`) });
            }
        }
    }
    if (process.env.CAPTURE_ISSUE_47 === '1') {
        await writeFile(path.join(evidenceRoot, 'homepage-layout.json'), `${JSON.stringify(measurements, null, 2)}\n`);
    }
});
