import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { expectNoGlobalOverflow, overlapArea } from './helpers';

type Box = { x: number; y: number; width: number; height: number };
type FocalRegion = { left: number; top: number; right: number; bottom: number };

const viewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 1280, height: 800 },
    { width: 390, height: 844 },
    { width: 320, height: 800 },
];

const focalContracts: Record<string, { brand: FocalRegion; vehicle: FocalRegion }> = {
    '/brand/hero-service-desktop.webp': {
        brand: { left: .66, top: .21, right: .89, bottom: .42 },
        vehicle: { left: .21, top: .29, right: .59, bottom: .71 },
    },
    '/brand/hero-service-mobile.webp': {
        brand: { left: 0, top: .19, right: .37, bottom: .38 },
        vehicle: { left: .23, top: .18, right: 1, bottom: .82 },
    },
};

const evidenceRoot = path.resolve(process.cwd(), '..', 'evidence', 'issue-30');

test('home hero keeps the approved media focal regions clear at every required viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(180_000);

    if (process.env.CAPTURE_ISSUE_30 === '1') await mkdir(evidenceRoot, { recursive: true });
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        const hero = page.getByTestId('home-hero');
        const media = hero.locator('.et-v3-hero__photo');
        const finder = hero.getByTestId('quick-finder');
        const content = hero.locator('.et-v3-hero__content');
        const primaryAction = hero.locator('.et-v3-hero__primary-action');

        await expect(hero).toBeVisible();
        await expect(media).toBeVisible();
        await expect(finder).toBeVisible();
        await expect(primaryAction).toBeVisible();
        await expect.poll(() => media.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);

        const geometry = await media.evaluate((image: HTMLImageElement, contracts) => {
            const roundBox = (rect: DOMRect): Box => ({
                x: Math.round(rect.x),
                y: Math.round(rect.y),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            });
            const imageBox = roundBox(image.getBoundingClientRect());
            const currentPath = new URL(image.currentSrc || image.src, location.href).pathname;
            const contract = contracts[currentPath];
            const style = getComputedStyle(image);
            const position = style.objectPosition.split(/\s+/);
            const percentage = (value: string | undefined, fallback: number) => value?.endsWith('%')
                ? Number.parseFloat(value) / 100
                : fallback;
            const scale = Math.max(imageBox.width / image.naturalWidth, imageBox.height / image.naturalHeight);
            const renderedWidth = image.naturalWidth * scale;
            const renderedHeight = image.naturalHeight * scale;
            const offsetX = imageBox.x + ((imageBox.width - renderedWidth) * percentage(position[0], .5));
            const offsetY = imageBox.y + ((imageBox.height - renderedHeight) * percentage(position[1], .5));
            const project = (region?: FocalRegion): Box | null => region ? {
                x: Math.round(offsetX + region.left * renderedWidth),
                y: Math.round(offsetY + region.top * renderedHeight),
                width: Math.round((region.right - region.left) * renderedWidth),
                height: Math.round((region.bottom - region.top) * renderedHeight),
            } : null;

            return {
                asset: currentPath,
                contractFound: Boolean(contract),
                objectFit: style.objectFit,
                media: imageBox,
                brand: project(contract?.brand),
                vehicle: project(contract?.vehicle),
            };
        }, focalContracts);

        expect(geometry.contractFound, `approved focal contract missing for ${geometry.asset}`).toBeTruthy();
        expect(geometry.objectFit).toBe('cover');
        expect(geometry.brand).not.toBeNull();
        expect(geometry.vehicle).not.toBeNull();

        const finderBox = await finder.boundingBox();
        const contentBox = await content.boundingBox();
        const actionBox = await primaryAction.boundingBox();
        expect(finderBox).not.toBeNull();
        expect(contentBox).not.toBeNull();
        expect(actionBox).not.toBeNull();
        expect(overlapArea(finderBox!, geometry.brand!)).toBe(0);
        expect(overlapArea(finderBox!, geometry.vehicle!)).toBe(0);
        expect(overlapArea(finderBox!, contentBox!)).toBe(0);
        expect(actionBox!.y).toBeLessThan(viewport.height);

        measurements.push({
            viewport,
            asset: geometry.asset,
            media: geometry.media,
            brand: geometry.brand,
            vehicle: geometry.vehicle,
            finder: finderBox,
            content: contentBox,
            primaryAction: actionBox,
            finderBrandOverlapArea: overlapArea(finderBox!, geometry.brand!),
            finderVehicleOverlapArea: overlapArea(finderBox!, geometry.vehicle!),
            finderContentOverlapArea: overlapArea(finderBox!, contentBox!),
        });

        if (viewport.width >= 901) {
            const visibleBrand = overlapArea(geometry.media, geometry.brand!) / (geometry.brand!.width * geometry.brand!.height);
            const visibleVehicle = overlapArea(geometry.media, geometry.vehicle!) / (geometry.vehicle!.width * geometry.vehicle!.height);
            expect(visibleBrand, 'desktop crop must keep the EasyTire van branding visible').toBeGreaterThan(.9);
            expect(visibleVehicle, 'desktop crop must keep the vehicle/work focal region visible').toBeGreaterThan(.9);
            await expect(finder.locator('input[name="registration"]')).toBeInViewport();
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

test('home process renders four substantial customer steps without a mechanical connector', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'buyer-desktop', 'One serial viewport matrix is sufficient');
    test.setTimeout(120_000);

    if (process.env.CAPTURE_ISSUE_30 === '1') await mkdir(evidenceRoot, { recursive: true });
    const measurements: Record<string, unknown>[] = [];

    for (const viewport of viewports.filter(({ width }) => [1440, 1280, 390, 320].includes(width))) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        const processStrip = page.getByTestId('home-process');
        const steps = processStrip.locator('.et-v3-process__step');

        await expect(processStrip).toBeVisible();
        await expect(processStrip.locator('.et-v3-process-strip__title')).toBeVisible();
        await expect(processStrip.locator('.et-v3-process-strip__lead')).toBeVisible();
        await expect(steps).toHaveCount(4);
        expect(await processStrip.locator('.et-v3-process').evaluate((element) => getComputedStyle(element, '::before').content)).toBe('none');
        expect(await steps.first().locator('.et-v3-process__icon').evaluate((element) => getComputedStyle(element).borderTopWidth)).toBe('0px');

        const processBox = await processStrip.boundingBox();
        expect(processBox).not.toBeNull();
        const stepBoxes = await steps.evaluateAll((elements) => elements.map((element) => {
            const box = element.getBoundingClientRect();
            return { x: box.x, y: box.y, width: box.width, height: box.height };
        }));
        expect(Math.min(...stepBoxes.map(({ height }) => height))).toBeGreaterThanOrEqual(viewport.width <= 640 ? 270 : 320);
        expect(new Set(stepBoxes.map(({ y }) => Math.round(y))).size).toBe(viewport.width <= 640 ? 4 : 1);
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
