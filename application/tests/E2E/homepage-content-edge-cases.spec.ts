import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { expectNoGlobalOverflow } from './helpers';

const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
const e2eDatabase = process.env.E2E_DB_DATABASE ?? path.resolve(process.cwd(), 'database', 'e2e.sqlite');
const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-47');

function installDatabaseFixtures(): void {
    execFileSync(phpBinary, ['artisan', 'db:seed', '--class=E2eHomepagePromotionEdgeCasesSeeder', '--force'], {
        cwd: process.cwd(),
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: e2eDatabase,
        },
        stdio: 'pipe',
    });
}

async function tabTo(page: Page, target: Locator, maximumTabs = 100): Promise<void> {
    for (let attempt = 0; attempt < maximumTabs; attempt += 1) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((element) => document.activeElement === element)) return;
    }

    throw new Error('Promotion control was not reached through real keyboard traversal.');
}

async function expectContained(outer: Locator, inner: Locator): Promise<void> {
    const outerElement = await outer.elementHandle();
    if (!outerElement) throw new Error('Expected containment parent to exist.');
    const geometry = await inner.evaluate((innerElement, outerElement) => {
        const outerBox = (outerElement as Element).getBoundingClientRect();
        const innerBox = innerElement.getBoundingClientRect();
        return { right: innerBox.right - outerBox.right, bottom: innerBox.bottom - outerBox.bottom };
    }, outerElement);
    await outerElement.dispose();
    expect(geometry.right).toBeLessThanOrEqual(2);
    expect(geometry.bottom).toBeLessThanOrEqual(2);
}

test('stored long short copy and a stored legacy offer without short copy remain fully reachable in NB and RU', async ({ page }) => {
    test.setTimeout(180_000);
    installDatabaseFixtures();

    for (const viewport of [
        { width: 1440, height: 900 },
        { width: 390, height: 844 },
        { width: 320, height: 800 },
    ]) {
        await page.setViewportSize(viewport);

        for (const locale of [
            { route: '/', shortEnd: 'SLUTTEN PÅ DEN LANGE KORTTEKSTEN', legacyEnd: 'SLUTTEN PÅ DET ELDRE TILBUDET' },
            { route: '/ru', shortEnd: 'КОНЕЦ ДЛИННОГО КРАТКОГО ОПИСАНИЯ', legacyEnd: 'КОНЕЦ СТАРОГО ПРЕДЛОЖЕНИЯ' },
        ]) {
            await page.goto(locale.route);
            const cards = page.getByTestId('homepage-promotion');
            const longShortCard = cards.nth(0);
            const longShort = longShortCard.locator('.et-v3-promotion__short');
            const longShortCta = longShortCard.locator('.et-v3-promotion__cta');

            await tabTo(page, longShort);
            await page.keyboard.press('End');
            await expect.poll(() => longShort.evaluate((element) => element.scrollTop + element.clientHeight >= element.scrollHeight - 2)).toBe(true);
            await expect(longShort).toContainText(locale.shortEnd);
            await expect(longShortCta).toBeVisible();
            await expectContained(longShortCard, longShortCta);
            if (process.env.CAPTURE_ISSUE_47 === '1') {
                mkdirSync(evidenceRoot, { recursive: true });
                const language = locale.route === '/' ? 'nb' : 'ru';
                await longShortCard.screenshot({ path: path.join(evidenceRoot, `promotion-long-short-${viewport.width}-${language}.png`) });
            }

            if (viewport.width < 768) {
                await page.locator('[data-promotion-next]').click();
            }

            const legacyCard = cards.nth(1);
            const disclosure = legacyCard.locator('[data-promotion-disclosure]');
            const details = legacyCard.locator('[data-promotion-details]');
            const legacyCta = legacyCard.locator('.et-v3-promotion__cta');
            await expect(legacyCard.locator('.et-v3-promotion__short')).toHaveCount(0);

            await tabTo(page, disclosure);
            await page.keyboard.press('Enter');
            await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
            await expect(details).toHaveAttribute('aria-hidden', 'false');
            await page.keyboard.press('Tab');
            await expect(details).toBeFocused();
            await page.keyboard.press('End');
            await expect.poll(() => details.evaluate((element) => element.scrollTop + element.clientHeight >= element.scrollHeight - 2)).toBe(true);
            await expect(details).toContainText(locale.legacyEnd);
            await expect(legacyCta).toBeVisible();
            await expectContained(legacyCard, legacyCta);
            await expectNoGlobalOverflow(page);
            if (process.env.CAPTURE_ISSUE_47 === '1') {
                const language = locale.route === '/' ? 'nb' : 'ru';
                await legacyCard.screenshot({ path: path.join(evidenceRoot, `promotion-legacy-body-${viewport.width}-${language}.png`) });
            }
        }
    }
});
