import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

type BalanceMetadata = {
    designProfile: 'modern' | 'classic';
    locale: 'nb' | 'ru';
    balanceWidth: number;
};

type InkMetrics = {
    lineHeight: number;
    inkHeight: number;
    sampleHeight: number;
    inkGap: number;
};

const evidenceRoot = path.resolve(process.cwd(), 'tmp/customer-balance/evidence');
const metadata = (info: TestInfo): BalanceMetadata => info.project.metadata as BalanceMetadata;
const localePrefix = (info: TestInfo): string => metadata(info).locale === 'ru' ? '/ru' : '';
const isDesktopNb = (info: TestInfo): boolean => metadata(info).locale === 'nb' && metadata(info).balanceWidth === 1440;
const isNarrowNb = (info: TestInfo): boolean => metadata(info).locale === 'nb' && [390, 320].includes(metadata(info).balanceWidth);

let unsafeRequests: string[] = [];

async function screenshot(locator: Locator | Page, info: TestInfo, label: string, fullPage = false): Promise<void> {
    await mkdir(evidenceRoot, { recursive: true });
    const target = path.join(evidenceRoot, `${info.project.name}-${label}.png`);
    if (fullPage) await (locator as Page).screenshot({ path: target, fullPage: true, animations: 'disabled' });
    else await (locator as Locator).screenshot({ path: target, animations: 'disabled' });
}

async function expectFinderControlsFit(page: Page, finder: Locator): Promise<void> {
    const controls = await finder.locator('input:visible, select:visible, button:visible').evaluateAll(nodes => nodes.map(node => {
        const rect = node.getBoundingClientRect();
        return { name: node.getAttribute('name') ?? node.getAttribute('aria-label') ?? node.tagName, left: rect.left, right: rect.right, width: rect.width, height: rect.height };
    }));
    const width = page.viewportSize()!.width;
    expect(controls, 'all visible finder controls should fit their viewport').not.toEqual([]);
    expect(controls.filter(control => control.left < -1 || control.right > width + 1 || control.width < 1 || control.height < 1)).toEqual([]);
}

async function expectModernCategoryTiles(finder: Locator, width: number): Promise<void> {
    const geometry = await finder.locator('.et-shop-categories').evaluate(root => {
        const rootRect = root.getBoundingClientRect();
        const rootStyle = getComputedStyle(root);
        const contentLeft = rootRect.left + Number.parseFloat(rootStyle.paddingLeft);
        const contentRight = rootRect.right - Number.parseFloat(rootStyle.paddingRight);
        return {
            contentLeft,
            contentRight,
            tiles: [...root.querySelectorAll('a')].map(tile => {
                const tileRect = tile.getBoundingClientRect();
                const text = tile.querySelector(':scope > span');
                const textRect = text?.getBoundingClientRect();
                return {
                    left: tileRect.left,
                    right: tileRect.right,
                    width: tileRect.width,
                    text: textRect ? { left: textRect.left, right: textRect.right, scrollWidth: text?.scrollWidth ?? 0, clientWidth: text?.clientWidth ?? 0 } : null,
                };
            }),
        };
    });

    expect(geometry.tiles).toHaveLength(4);
    for (const tile of geometry.tiles) {
        expect(tile.text, 'Each category tile needs its text wrapper.').not.toBeNull();
        expect(tile.text!.left, 'Category copy must start inside its own tile.').toBeGreaterThanOrEqual(tile.left - 1);
        expect(tile.text!.right, 'Category copy must end inside its own tile.').toBeLessThanOrEqual(tile.right + 1);
        expect(tile.text!.scrollWidth, 'Category copy must not overflow its own text wrapper.').toBeLessThanOrEqual(tile.text!.clientWidth + 1);
    }
    if (width === 320) {
        for (const tile of geometry.tiles) {
            expect(tile.left, 'Modern 320 category tiles share the content left edge.').toBeCloseTo(geometry.contentLeft, 0);
            expect(tile.right, 'Modern 320 category tiles fill the available content width.').toBeCloseTo(geometry.contentRight, 0);
        }
    }
}

async function typeWeight(locator: Locator): Promise<string> {
    await expect(locator).toBeVisible();
    return locator.evaluate(element => getComputedStyle(element).fontWeight);
}

async function typeReadability(locator: Locator) {
    await expect(locator).toBeVisible();
    return locator.evaluate(element => {
        const style = getComputedStyle(element);
        const size = Number.parseFloat(style.fontSize);
        const lineHeight = Number.parseFloat(style.lineHeight);
        const range = document.createRange();
        range.selectNodeContents(element);
        const inkRects = [...range.getClientRects()]
            .filter(rect => rect.width > 0 && rect.height > 0);
        const lines = new Set(inkRects
            .map(rect => Math.round(rect.top * 2) / 2));
        const heroRect = element.closest('.et-v3-hero')?.getBoundingClientRect();
        return {
            family: style.fontFamily,
            size,
            lineHeight,
            leadingRatio: lineHeight / size,
            lineCount: lines.size,
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
            clientHeight: element.clientHeight,
            scrollHeight: element.scrollHeight,
            overflowX: style.overflowX,
            overflowY: style.overflowY,
            inkLeft: Math.min(...inkRects.map(rect => rect.left)),
            inkRight: Math.max(...inkRects.map(rect => rect.right)),
            inkTop: Math.min(...inkRects.map(rect => rect.top)),
            inkBottom: Math.max(...inkRects.map(rect => rect.bottom)),
            viewportWidth: document.documentElement.clientWidth,
            heroTop: heroRect?.top ?? Number.NEGATIVE_INFINITY,
            heroBottom: heroRect?.bottom ?? Number.POSITIVE_INFINITY,
        };
    });
}

async function classicDiacriticMetrics(heroTitle: Locator): Promise<InkMetrics> {
    return heroTitle.evaluate((title) => {
        const sample = document.createElement('span');
        sample.dataset.balanceDiacriticSample = 'true';
        sample.className = title.className;
        sample.innerHTML = '<span>JÅ<i data-balance-baseline></i></span><br><span>ÅÆØ<i data-balance-baseline></i></span>';
        sample.setAttribute('aria-hidden', 'true');
        sample.style.cssText = 'position:absolute; left:-10000px; top:0; display:inline-block; width:max-content; white-space:nowrap; pointer-events:none;';
        title.parentElement?.append(sample);

        const style = getComputedStyle(sample);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas metrics are unavailable.');
        context.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
        // J provides the descending ink that is present in the real hero copy;
        // Å supplies the accented top-edge under review on the following line.
        const firstInk = context.measureText('JÅ');
        const secondInk = context.measureText('ÅÆØ');
        const baselines = [...sample.querySelectorAll<HTMLElement>('[data-balance-baseline]')].map(marker => {
            marker.style.cssText = 'display:inline-block; width:0; height:0; vertical-align:baseline;';
            return marker.getBoundingClientRect().top;
        });
        const result = {
            lineHeight: Number.parseFloat(style.lineHeight),
            inkHeight: secondInk.actualBoundingBoxAscent + secondInk.actualBoundingBoxDescent,
            sampleHeight: sample.getBoundingClientRect().height,
            // Markers locate each actual text baseline; unlike inline span boxes,
            // this measures the space between rendered glyph ink, not em boxes.
            inkGap: (baselines[1] - secondInk.actualBoundingBoxAscent) - (baselines[0] + firstInk.actualBoundingBoxDescent),
        };
        sample.remove();
        return result;
    });
}

test.beforeEach(async ({ page, baseURL }) => {
    unsafeRequests = [];
    const origin = new URL(baseURL!).origin;
    await page.route('**/*', route => {
        const request = route.request();
        const requestUrl = new URL(request.url());
        if (requestUrl.origin !== origin) return route.abort('blockedbyclient');
        if (!['GET', 'HEAD'].includes(request.method())) {
            unsafeRequests.push(`${request.method()} ${requestUrl.pathname}`);
            return route.abort('blockedbyclient');
        }
        return route.continue();
    });
});

test.afterEach(() => {
    expect(unsafeRequests, 'This visual/interaction suite must never issue a mutating request.').toEqual([]);
});

test('customer balance keeps scoped type, finder geometry and GET-only choices across profiles', async ({ page }, info) => {
    test.setTimeout(90_000);
    const { designProfile } = metadata(info);
    const prefix = localePrefix(info);
    await page.goto(prefix || '/');
    await page.evaluate(() => document.fonts.ready);

    await expect(page.locator('body')).toHaveAttribute('data-storefront-design', designProfile);
    const heroTitle = page.locator('main .et-v3-display');
    const sectionTitle = page.locator('main #home-logistics-title');
    const finder = page.getByTestId('home-hero').getByTestId('quick-finder');
    const finderTitle = finder.locator('.et-quick-finder__head h2');
    const expectedMarketingWeight = '800';
    const expectedFinderWeight = designProfile === 'modern' ? '700' : '800';

    // Modern's expressive marketing headings and its functional finder deliberately
    // use different weights. Classic retains its accepted 800-weight contracts.
    await expect(typeWeight(heroTitle)).resolves.toBe(expectedMarketingWeight);
    await expect(typeWeight(sectionTitle)).resolves.toBe(expectedMarketingWeight);
    await expect(typeWeight(finderTitle)).resolves.toBe(expectedFinderWeight);
    const bodyWeight = Number.parseFloat(await typeWeight(page.locator('body')));
    const leadWeight = Number.parseFloat(await typeWeight(page.locator('main .et-v3-hero__lead')));
    expect(bodyWeight, 'The balance layer must not make all body text bold.').toBeLessThan(600);
    expect(leadWeight, 'The representative hero body copy must remain normal-weight.').toBeLessThan(600);

    await expect(finder).toBeVisible();
    const finderSurface = await finder.evaluate(node => {
        const style = getComputedStyle(node);
        return { borderTop: Number.parseFloat(style.borderTopWidth), radius: Number.parseFloat(style.borderTopLeftRadius), background: style.backgroundColor };
    });
    if (designProfile === 'classic') {
        // The existing classic 1px-border/2px-radius finder frame is a regression boundary, not
        // part of the new design work; only its heading leading may move.
        expect(finderSurface.borderTop).toBeGreaterThanOrEqual(1);
        expect(finderSurface.radius).toBeGreaterThan(0);
        expect(finderSurface.radius).toBeLessThanOrEqual(2.1);
    } else {
        expect(finderSurface.borderTop, 'Modern finder grouping keeps its explicit frame.').toBeGreaterThanOrEqual(1);
        expect(finderSurface.background, 'Modern finder grouping keeps its surface.').not.toBe('rgba(0, 0, 0, 0)');
        const heroReadability = await typeReadability(heroTitle);
        expect(heroReadability.family, 'Modern marketing display type remains condensed.').toMatch(/Barlow Condensed/i);
        expect(heroReadability.leadingRatio, 'Modern hero leading stays compact without collapsing the line box.').toBeGreaterThanOrEqual(.8);
        expect(heroReadability.leadingRatio, 'Modern hero leading stays compact without opening into body-copy rhythm.').toBeLessThanOrEqual(1.05);
        expect(heroReadability.lineCount, 'Modern localized hero copy must render at least one readable line.').toBeGreaterThan(0);
        expect(heroReadability.overflowX, 'Modern hero must not clip condensed glyph ink horizontally.').toBe('visible');
        expect(heroReadability.overflowY, 'Modern hero must not clip condensed glyph ink vertically.').toBe('visible');
        expect(heroReadability.inkLeft, 'Modern localized hero ink must stay inside the viewport.').toBeGreaterThanOrEqual(-1);
        expect(heroReadability.inkRight, 'Modern localized hero ink must stay inside the viewport.').toBeLessThanOrEqual(heroReadability.viewportWidth + 1);
        expect(heroReadability.inkTop, 'Modern localized hero ink must stay inside the hero surface.').toBeGreaterThanOrEqual(heroReadability.heroTop - 1);
        expect(heroReadability.inkBottom, 'Modern localized hero ink must stay inside the hero surface.').toBeLessThanOrEqual(heroReadability.heroBottom + 1);
        await expectModernCategoryTiles(finder, metadata(info).balanceWidth);
    }

    const process = page.getByTestId('home-process').locator('.et-v3-process');
    const processSteps = process.locator('.et-v3-process__step');
    const processIcons = processSteps.locator('.et-v3-process__icon');
    if (designProfile === 'modern') {
        await expect(process).toHaveClass(/\bet-v3-process--timeline\b/);
        await expect(processSteps).toHaveCount(5);
        await expect(processIcons).toHaveCount(0);
    } else {
        await expect(process).not.toHaveClass(/\bet-v3-process--timeline\b/);
        await expect(processSteps).toHaveCount(4);
        await expect(processIcons).toHaveCount(4);
    }

    await expectFinderControlsFit(page, finder);
    await expectNoGlobalOverflow(page);

    if (isDesktopNb(info)) {
        await screenshot(page, info, 'home', true);
        await screenshot(finder, info, 'finder');
    }
    if (isNarrowNb(info)) await screenshot(finder, info, 'finder');

    if (designProfile === 'classic') {
        if (isDesktopNb(info) || (metadata(info).locale === 'nb' && metadata(info).balanceWidth === 320)) {
            await screenshot(heroTitle, info, 'hero-title-original');
        }
        const metrics = await classicDiacriticMetrics(heroTitle);
        expect(metrics.inkHeight, 'the loaded display font should expose Å/Æ/Ø ink metrics').toBeGreaterThan(0);
        expect(metrics.lineHeight, 'Classic display leading must clear Å/Æ/Ø across two lines.').toBeGreaterThanOrEqual(metrics.inkHeight - 0.5);
        expect(metrics.sampleHeight, 'The controlled two-line diacritic sample must retain two full line boxes.').toBeGreaterThanOrEqual(metrics.lineHeight * 2 - 1);
        expect(metrics.inkGap, 'The second Å/Æ/Ø line must clear the preceding line’s rendered ink.').toBeGreaterThanOrEqual(1);
    }

    const registrationTab = finder.getByRole('tab', { name: /Registreringsnummer|Госномер/ });
    const dimensionTab = finder.getByRole('tab', { name: /Dekkdimensjon|Размер шины/ });
    const registration = finder.locator('input[name="registration"]');
    await expect(registration).toBeVisible();
    await registration.fill('ÅB12345');
    await dimensionTab.click();
    await expect(dimensionTab).toHaveAttribute('aria-selected', 'true');

    const sizeForm = finder.locator('form.et-quick-finder__size');
    const width = sizeForm.locator('select[name="width"]');
    const aspect = sizeForm.locator('select[name="aspect"]');
    const rim = sizeForm.locator('select[name="rim"]');
    await expect(width).toBeVisible();
    await width.focus();
    await expect(width).toBeFocused();
    await width.selectOption('205');
    await aspect.selectOption('55');
    await rim.selectOption('16');
    await expectFinderControlsFit(page, finder);
    await expectNoGlobalOverflow(page);

    await registrationTab.click();
    await expect(registration).toHaveValue('ÅB12345');
    await dimensionTab.click();
    await Promise.all([
        page.waitForURL(new RegExp(`${prefix}/dekk\\?width=205&aspect=55&rim=16$`)),
        sizeForm.getByRole('button', { name: /Vis passende dekk|Показать подходящие шины/ }).click(),
    ]);
    await expect(page.getByTestId('catalog-results')).toBeVisible();
    await expectNoGlobalOverflow(page);

    // Category choices remain ordinary, focusable GET links; no registration form is submitted.
    await page.goto(prefix || '/');
    const categoryLinks = page.getByTestId('quick-finder').locator('.et-shop-categories a');
    await expect(categoryLinks).toHaveCount(4);
    const tyreHref = await categoryLinks.nth(0).getAttribute('href');
    const wheelHref = await categoryLinks.nth(1).getAttribute('href');
    expect(new URL(tyreHref!).pathname).toBe(`${prefix}/dekk`);
    expect(new URL(wheelHref!).pathname).toBe(`${prefix}/felger`);
    await categoryLinks.nth(0).focus();
    await expect(categoryLinks.nth(0)).toBeFocused();
    await page.goto(tyreHref!);
    await expect(page).toHaveURL(new RegExp(`${prefix}/dekk$`));
    await page.goto(wheelHref!);
    await expect(page).toHaveURL(new RegExp(`${prefix}/felger$`));
});
