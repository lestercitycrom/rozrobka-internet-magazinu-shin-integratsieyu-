import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, isMobileProject, isRussianProject, localePrefix } from './helpers';

const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-45');
const viewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 1280, height: 800 },
    { width: 390, height: 844 },
    { width: 320, height: 800 },
];

test('customer header, finder and footer stay coherent across the required viewport matrix', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One deterministic viewport matrix is sufficient');
    test.setTimeout(180_000);
    const capture = process.env.CAPTURE_ISSUE_45 === '1';
    if (capture) await mkdir(evidenceRoot, { recursive: true });
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        await expectNoGlobalOverflow(page);

        const desktop = viewport.width > 1180;
        if (desktop) {
            const navigation = page.getByTestId('desktop-navigation');
            await expect(navigation).toBeVisible();
            await navigation.locator('[data-mega-menu-toggle]').click();
            await expect(navigation.locator('[data-mega-menu-panel]')).toHaveAttribute('aria-hidden', 'false');
        } else {
            await expect(page.getByTestId('desktop-navigation')).toBeHidden();
            await page.locator('[data-menu-toggle]').click();
            await page.getByTestId('mobile-navigation').locator('summary').click();
            await expect(page.getByTestId('mobile-navigation').getByText(isRussianProject(testInfo.project.name) ? 'Шины и колёса' : 'Dekk og hjul')).toBeVisible();
        }

        const menuGeometry = await page.locator(desktop ? '[data-mega-menu-panel]' : '[data-mobile-menu]').evaluate((node) => {
            const rect = node.getBoundingClientRect();
            return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height };
        });
        expect(menuGeometry.left).toBeGreaterThanOrEqual(0);
        expect(menuGeometry.right).toBeLessThanOrEqual(viewport.width + 1);

        if (capture) {
            await page.screenshot({
                path: path.join(evidenceRoot, `header-menu-${viewport.width}x${viewport.height}.png`),
                clip: { x: 0, y: 0, width: viewport.width, height: Math.min(viewport.height, desktop ? 650 : viewport.height) },
            });
        }

        if (desktop) await page.locator('[data-mega-menu-toggle]').press('Escape');
        else await page.locator('[data-menu-toggle]').press('Escape');

        const finder = page.getByTestId('home-hero').getByTestId('quick-finder');
        await finder.getByRole('tab', { name: /Dekkdimensjon|Размер шины/ }).click();
        const selects = finder.locator('select');
        await expect(selects).toHaveCount(3);
        await expect(selects.nth(0)).toHaveValue('');
        await expect(selects.nth(1)).toHaveValue('');
        await expect(selects.nth(2)).toHaveValue('');
        const finderBox = await finder.boundingBox();
        const heroContentBox = await page.locator('.et-v3-hero__content').boundingBox();
        expect(finderBox).not.toBeNull();
        expect(heroContentBox).not.toBeNull();
        if (desktop) expect(finderBox!.y).toBeGreaterThanOrEqual(heroContentBox!.y + heroContentBox!.height);
        expect(finderBox!.x).toBeGreaterThanOrEqual(0);
        expect(finderBox!.x + finderBox!.width).toBeLessThanOrEqual(viewport.width + 1);

        if (capture) {
            await page.screenshot({ path: path.join(evidenceRoot, `finder-${viewport.width}x${viewport.height}.png`), fullPage: false });
        }

        const footer = page.locator('footer');
        await footer.scrollIntoViewIfNeeded();
        const logoStyle = await footer.locator('.et-footer__logo').evaluate((logo) => ({
            filter: getComputedStyle(logo).filter,
            src: (logo as HTMLImageElement).getAttribute('src'),
        }));
        expect(logoStyle.filter).toBe('none');
        expect(logoStyle.src).toBe('/brand/easytire-logo.png');
        await expectNoGlobalOverflow(page);

        if (capture) await footer.screenshot({ path: path.join(evidenceRoot, `footer-${viewport.width}x${viewport.height}.png`) });
        measurements.push({ viewport, desktop, menuGeometry, finder: finderBox, heroContent: heroContentBox, footerLogo: logoStyle });
    }

    if (capture) await writeFile(path.join(evidenceRoot, 'viewport-measurements.json'), JSON.stringify(measurements, null, 2));
});

test('desktop mega menu supports pointer, keyboard, Escape and focus return without moving the header', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo.project.name) || isRussianProject(testInfo.project.name));
    await page.goto('/');
    const root = page.locator('[data-mega-menu]');
    const toggle = root.locator('[data-mega-menu-toggle]');
    const panel = root.locator('[data-mega-menu-panel]');
    const headerBefore = await page.getByRole('banner').boundingBox();

    await root.hover();
    await expect(panel).toHaveAttribute('aria-hidden', 'false');
    const headerAfterHover = await page.getByRole('banner').boundingBox();
    expect(headerAfterHover).toEqual(headerBefore);
    await page.locator('main').hover({ position: { x: 5, y: 5 } });
    await expect(panel).toHaveAttribute('aria-hidden', 'true');

    await toggle.focus();
    const focusStyle = await toggle.evaluate((element) => {
        const style = getComputedStyle(element);
        return { outlineWidth: style.outlineWidth, boxShadow: style.boxShadow };
    });
    expect(focusStyle.outlineWidth !== '0px' || focusStyle.boxShadow !== 'none').toBeTruthy();
    await toggle.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Tab');
    await expect(root.locator('.et-nav__all-services')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(root.locator('.et-nav__mega-grid a').first()).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(root.locator('.et-nav__all-services')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

test('mobile navigation preserves service group order and returns focus after Escape', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo.project.name));
    await page.goto('/');
    const toggle = page.locator('[data-menu-toggle]');
    await toggle.click();
    const navigation = page.getByTestId('mobile-navigation');
    const services = navigation.locator('details').first();
    await services.locator('summary').click();

    const headings = await services.locator('.et-mobile-menu__children > p').allTextContents();
    expect(headings.map((text) => text.trim())).toEqual(['Dekk og hjul', 'Biltjenester']);
    await expect(services.locator('a').filter({ hasText: 'Dekkskift hjemme hos deg' })).toBeVisible();
    const lastService = services.locator('a').filter({ hasText: 'Utelåsing – åpning av bil' });
    await lastService.scrollIntoViewIfNeeded();
    await expect(lastService).toBeVisible();
    expect(await page.locator('[data-mobile-menu]').evaluate((menu) => menu.scrollTop)).toBeGreaterThan(0);

    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(navigation).toBeHidden();
});

test('catalogue search is explicitly bounded and submits the existing q contract', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(prefix || '/');
    const mobile = isMobileProject(testInfo.project.name);
    if (mobile) await page.locator('[data-menu-toggle]').click();
    const search = mobile ? page.locator('.et-mobile-menu__search') : page.locator('[data-catalog-search]');
    if (!mobile) await search.locator('[data-catalog-search-toggle]').click();
    const query = search.locator('input[name="q"]');
    if (!mobile) await expect(query).toBeFocused();
    const suggestionsResponse = page.waitForResponse((response) => response.url().includes('/dekk/suggestions?q=Demo'));
    await query.fill('Demo');
    await expect((await suggestionsResponse).status()).toBe(200);
    await expect(search.locator('datalist option').first()).toHaveAttribute('value', /.+/);
    await search.getByRole('button', { name: /Søk|Найти/, exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`${prefix || ''}/dekk\\?q=Demo$`));
});

test('dimension selections require an explicit choice and preserve the existing catalogue keys', async ({ page }, testInfo) => {
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(prefix || '/');
    const finder = page.getByTestId('home-hero').getByTestId('quick-finder');
    await finder.getByRole('tab', { name: /Dekkdimensjon|Размер шины/ }).click();
    const form = finder.locator('form.et-quick-finder__size');
    for (const name of ['width', 'aspect', 'rim']) {
        await expect(form.locator(`select[name="${name}"]`)).toHaveValue('');
        await expect(form.locator(`select[name="${name}"]`)).toHaveAttribute('required', '');
    }

    await form.getByRole('button', { name: /Vis passende dekk|Показать подходящие шины/ }).click();
    await expect(form.locator('select[name="width"]')).toBeFocused();
    expect(await form.locator('select[name="width"]').evaluate((select: HTMLSelectElement) => select.validity.valueMissing)).toBeTruthy();
    await expect(page).toHaveURL(new RegExp(`${prefix || ''}/?$`));

    await form.locator('select[name="width"]').selectOption('205');
    await form.locator('select[name="aspect"]').selectOption('55');
    await form.locator('select[name="rim"]').selectOption('16');
    await form.getByRole('button', { name: /Vis passende dekk|Показать подходящие шины/ }).click();
    await expect(page).toHaveURL(new RegExp(`${prefix || ''}/dekk\\?width=205&aspect=55&rim=16$`));
});

test('phone and booking actions retain their separate business meanings', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo.project.name));
    const prefix = localePrefix(testInfo.project.name);
    await page.goto(prefix || '/');

    await expect(page.getByTestId('header-call')).toHaveAttribute('href', 'tel:+4793958270');
    const heroBooking = page.locator('.et-v3-hero__primary-action');
    const footerBooking = page.getByTestId('footer-booking');
    await expect(heroBooking).toHaveAttribute('href', new RegExp(`${prefix || ''}/tjenester/.+/foresporsel$`));
    await expect(footerBooking).toHaveAttribute('href', new RegExp(`${prefix || ''}/tjenester/.+/foresporsel$`));
    await expect(heroBooking).not.toHaveAttribute('href', /^tel:/);
    await expect(footerBooking).not.toHaveAttribute('href', /^tel:/);
});

test('withdrawal PDF responds and required footer links remain available without JavaScript', async ({ browser, request }, testInfo) => {
    test.skip(isMobileProject(testInfo.project.name));
    const prefix = localePrefix(testInfo.project.name);
    const pdfResponse = await request.get('/dokumenter/angreskjema-easytire.pdf');
    expect(pdfResponse.status()).toBe(200);

    const baseURL = String(testInfo.project.use.baseURL ?? 'http://127.0.0.1:8000');
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    await page.goto(prefix || '/');
    for (const groupId of ['footer-services-links', 'footer-store-links', 'footer-company-links']) {
        const links = page.locator(`#${groupId} a`);
        expect(await links.count()).toBeGreaterThan(0);
        await expect(links.first()).toBeVisible();
        await expect(links.last()).toBeVisible();
    }
    await context.close();
});
