import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, overlapArea } from './helpers';

type Box = { x: number; y: number; width: number; height: number };

const viewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 1280, height: 800 },
    { width: 390, height: 844 },
    { width: 320, height: 800 },
];

const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-30');

test('home hero keeps full-area media and primary paths clear at every required viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(180_000);

    if (process.env.CAPTURE_ISSUE_30 === '1') await mkdir(evidenceRoot, { recursive: true });
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        const hero = page.getByTestId('home-hero');
        const picture = hero.locator(':scope > picture');
        const media = hero.locator('.et-v3-hero__photo');
        const finder = hero.getByTestId('quick-finder');
        const content = hero.locator('.et-v3-hero__content');
        const heading = hero.locator('.et-v3-display');
        const primaryAction = hero.locator('.et-v3-hero__primary-action');

        await expect(hero).toBeVisible();
        await expect(picture).toBeVisible();
        await expect(media).toBeVisible();
        await expect(finder).toBeVisible();
        await expect(primaryAction).toBeVisible();
        await expect.poll(() => media.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);

        const geometry = await media.evaluate((image: HTMLImageElement) => {
            const roundBox = (rect: DOMRect): Box => ({
                x: Math.round(rect.x),
                y: Math.round(rect.y),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            });
            const imageBox = roundBox(image.getBoundingClientRect());
            const currentPath = new URL(image.currentSrc || image.src, location.href).pathname;
            const style = getComputedStyle(image);

            return {
                asset: currentPath,
                objectFit: style.objectFit,
                media: imageBox,
            };
        });

        expect(geometry.asset).toBe(viewport.width <= 767
            ? '/brand/hero-service-mobile.webp'
            : '/brand/hero-service-desktop.webp');
        expect(geometry.objectFit).toBe('cover');

        const heroBox = await hero.boundingBox();
        const pictureBox = await picture.boundingBox();
        const finderBox = await finder.boundingBox();
        const contentBox = await content.boundingBox();
        const actionBox = await primaryAction.boundingBox();
        const headingLines = await heading.evaluate((element) => {
            const style = getComputedStyle(element);
            const lineHeight = Number.parseFloat(style.lineHeight);

            return Math.round(element.getBoundingClientRect().height / lineHeight);
        });
        const headingLineText = await heading.evaluate((element) => {
            const text = element.textContent ?? '';
            const textNode = element.firstChild;
            if (!(textNode instanceof Text)) return [];

            const lines: string[] = [];
            let currentLine = '';
            let currentTop: number | null = null;
            for (let index = 0; index < text.length; index += 1) {
                const range = document.createRange();
                range.setStart(textNode, index);
                range.setEnd(textNode, index + 1);
                const top = Math.round(range.getBoundingClientRect().top);
                if (currentTop !== null && top !== currentTop) {
                    lines.push(currentLine.trim());
                    currentLine = '';
                }
                currentTop = top;
                currentLine += text[index];
            }
            if (currentLine.trim()) lines.push(currentLine.trim());

            return lines;
        });
        expect(heroBox).not.toBeNull();
        expect(pictureBox).not.toBeNull();
        expect(finderBox).not.toBeNull();
        expect(contentBox).not.toBeNull();
        expect(actionBox).not.toBeNull();
        expect(overlapArea(finderBox!, contentBox!)).toBe(0);
        expect(actionBox!.y + actionBox!.height).toBeLessThanOrEqual(viewport.height);

        measurements.push({
            viewport,
            asset: geometry.asset,
            hero: heroBox,
            picture: pictureBox,
            media: geometry.media,
            finder: finderBox,
            content: contentBox,
            primaryAction: actionBox,
            finderContentOverlapArea: overlapArea(finderBox!, contentBox!),
        });

        if (viewport.width >= 1200) {
            expect(headingLines).toBe(2);
            expect(headingLineText).toEqual(['Vi skifter hjul der', 'bilen står.']);
            expect(Math.abs(pictureBox!.x - heroBox!.x)).toBeLessThanOrEqual(1);
            expect(Math.abs(pictureBox!.y - heroBox!.y)).toBeLessThanOrEqual(1);
            expect(Math.abs(pictureBox!.width - heroBox!.width)).toBeLessThanOrEqual(1);
            expect(Math.abs(pictureBox!.height - heroBox!.height)).toBeLessThanOrEqual(1);
            expect(finderBox!.x).toBeGreaterThanOrEqual(contentBox!.x + contentBox!.width);
            expect(finderBox!.width).toBeGreaterThanOrEqual(360);
            expect(finderBox!.width).toBeLessThanOrEqual(400);
            expect(finderBox!.y + finderBox!.height).toBeLessThanOrEqual(viewport.height);
            await expect(finder.locator('input[name="registration"]')).toBeInViewport();
        } else if (viewport.width <= 767) {
            expect(pictureBox!.height).toBeGreaterThanOrEqual(180);
            expect(pictureBox!.height).toBeLessThanOrEqual(220);
            expect(contentBox!.y).toBeGreaterThanOrEqual(pictureBox!.y + pictureBox!.height - 1);
            expect(actionBox!.y).toBeLessThan(finderBox!.y);
            expect(finderBox!.y).toBeGreaterThanOrEqual(contentBox!.y + contentBox!.height - 1);
        }

        await expectNoGlobalOverflow(page);

        if (process.env.CAPTURE_ISSUE_30 === '1') {
            await page.screenshot({
                path: path.join(evidenceRoot, `after-hero-${viewport.width}x${viewport.height}.jpg`),
                type: 'jpeg',
                quality: 82,
            });
        }
    }

    if (process.env.CAPTURE_ISSUE_30 === '1') {
        await writeFile(path.join(evidenceRoot, 'hero-geometry.json'), `${JSON.stringify(measurements, null, 2)}\n`);
    }
});

test('home process renders five readable customer steps on an adaptive timeline', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(120_000);

    if (process.env.CAPTURE_ISSUE_30 === '1') await mkdir(evidenceRoot, { recursive: true });
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of [
        ...viewports.filter(({ width }) => [1440, 1280, 390, 320].includes(width)),
        { width: 800, height: 900 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        const processStrip = page.getByTestId('home-process');
        const steps = processStrip.locator('.et-v3-process__step');

        await expect(processStrip).toBeVisible();
        await expect(processStrip.locator('.et-v3-process-strip__title')).toBeVisible();
        await expect(processStrip.locator('.et-v3-process-strip__lead')).toBeVisible();
        await expect(steps).toHaveCount(5);
        expect(await processStrip.locator('.et-v3-process').evaluate((element) => getComputedStyle(element, '::before').content)).toBe('\"\"');
        await expect(steps.first().locator('.et-v3-process__icon')).toHaveCount(0);

        const processBox = await processStrip.boundingBox();
        expect(processBox).not.toBeNull();
        const stepBoxes = await steps.evaluateAll((elements) => elements.map((element) => {
            const box = element.getBoundingClientRect();
            return {
                x: box.x,
                y: box.y,
                width: box.width,
                height: box.height,
                clientHeight: element.clientHeight,
                scrollHeight: element.scrollHeight,
            };
        }));
        const columns = await processStrip.locator('.et-v3-process').evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
        const expectedColumns = viewport.width <= 900 ? 1 : 5;
        expect(columns).toBe(expectedColumns);
        expect(new Set(stepBoxes.map(({ y }) => Math.round(y))).size).toBe(expectedColumns === 1 ? 5 : 1);
        expect(stepBoxes.every(({ height, clientHeight, scrollHeight }) => height > 0 && scrollHeight <= clientHeight + 1)).toBeTruthy();
        for (let first = 0; first < stepBoxes.length; first += 1) {
            for (let second = first + 1; second < stepBoxes.length; second += 1) {
                expect(overlapArea(stepBoxes[first], stepBoxes[second])).toBe(0);
            }
        }
        measurements.push({ viewport, processStrip: processBox, steps: stepBoxes });
        await expectNoGlobalOverflow(page);

        if (process.env.CAPTURE_ISSUE_30 === '1') {
            const stickyHeader = page.locator('[data-site-header]');
            await stickyHeader.evaluate((element: HTMLElement) => element.style.visibility = 'hidden');
            await processStrip.screenshot({
                path: path.join(evidenceRoot, `after-process-${viewport.width}x${viewport.height}.jpg`),
                type: 'jpeg',
                quality: 82,
            });
            await stickyHeader.evaluate((element: HTMLElement) => element.style.removeProperty('visibility'));
        }
    }


    if (process.env.CAPTURE_ISSUE_30 === '1') {
        await writeFile(path.join(evidenceRoot, 'process-geometry.json'), `${JSON.stringify(measurements, null, 2)}\n`);
    }
});
