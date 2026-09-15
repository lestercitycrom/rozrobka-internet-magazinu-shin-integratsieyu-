import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8044';
const outputRoot = path.resolve(process.env.DESIGN_QA_OUTPUT ?? 'storage/app/qa-design-v2');
const profile = process.env.DESIGN_QA_PROFILE ?? 'full';

const pages = [
    { name: 'home', route: '/' },
    { name: 'catalog', route: '/dekk' },
    { name: 'service', route: '/tjenester/dekkverksted' },
    { name: 'form', route: '/tjenester/mobilt-dekkskift/foresporsel' },
];

const baseViewports = [
    { width: 1440, height: 900 },
    { width: 390, height: 844 },
    { width: 320, height: 568 },
    { width: 1280, height: 800 },
];

const extraViewports = {
    home: [{ width: 2560, height: 1440 }],
    service: [{ width: 768, height: 1024 }, { width: 1024, height: 768 }],
    form: [{ width: 768, height: 1024 }, { width: 1024, height: 768 }],
};

const focusedViewports = {
    home: [
        { width: 1440, height: 900 },
        { width: 390, height: 844 },
        { width: 320, height: 568 },
        { width: 1024, height: 768 },
        { width: 767, height: 1024 },
    ],
    catalog: [
        { width: 1440, height: 900 },
        { width: 390, height: 844 },
        { width: 320, height: 568 },
    ],
    service: [
        { width: 1440, height: 900 },
        { width: 390, height: 844 },
    ],
    form: [
        { width: 1440, height: 900 },
        { width: 390, height: 844 },
    ],
};

const browser = await chromium.launch({ headless: true });
const report = {
    generatedAt: new Date().toISOString(),
    baseURL,
    note: 'Playwright viewport emulation; not a claim of real-device testing.',
    captures: [],
};

await mkdir(outputRoot, { recursive: true });

try {
    for (const pageDefinition of pages) {
        const viewports = profile === 'focused'
            ? focusedViewports[pageDefinition.name]
            : [...baseViewports, ...(extraViewports[pageDefinition.name] ?? [])];

        for (const viewport of viewports) {
            const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
            const page = await context.newPage();
            const url = new URL(pageDefinition.route, baseURL).toString();
            const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
            await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});

            await page.evaluate(async () => {
                await document.fonts.ready;
                const imageDecodes = Promise.all([...document.images].map(async (image) => {
                    if (typeof image.decode === 'function') {
                        await image.decode().catch(() => {});
                    }
                }));
                await Promise.race([
                    imageDecodes,
                    new Promise((resolve) => setTimeout(resolve, 10_000)),
                ]);
            });

            await page.addStyleTag({ content: `
                *, *::before, *::after {
                    animation-duration: 0s !important;
                    animation-delay: 0s !important;
                    transition-duration: 0s !important;
                    scroll-behavior: auto !important;
                    caret-color: transparent !important;
                }
            ` });

            const metrics = await page.evaluate(() => {
                const roundedRect = (rect) => ({
                    x: Math.round(rect.x * 100) / 100,
                    y: Math.round(rect.y * 100) / 100,
                    width: Math.round(rect.width * 100) / 100,
                    height: Math.round(rect.height * 100) / 100,
                    top: Math.round(rect.top * 100) / 100,
                    right: Math.round(rect.right * 100) / 100,
                    bottom: Math.round(rect.bottom * 100) / 100,
                    left: Math.round(rect.left * 100) / 100,
                });

                const identity = (element) => {
                    const id = element.id ? `#${element.id}` : '';
                    const classes = [...element.classList].slice(0, 4).map((name) => `.${name}`).join('');
                    return `${element.tagName.toLowerCase()}${id}${classes}`;
                };

                const typography = (selector) => {
                    const element = [...document.querySelectorAll(selector)].find((candidate) => {
                        const rect = candidate.getBoundingClientRect();
                        const style = getComputedStyle(candidate);
                        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
                    });
                    if (!element) return null;
                    const style = getComputedStyle(element);
                    return {
                        element: identity(element),
                        text: element.textContent?.trim().replace(/\s+/g, ' ').slice(0, 160) ?? '',
                        rect: roundedRect(element.getBoundingClientRect()),
                        fontFamily: style.fontFamily,
                        fontSize: style.fontSize,
                        fontWeight: style.fontWeight,
                        lineHeight: style.lineHeight,
                        letterSpacing: style.letterSpacing,
                        color: style.color,
                    };
                };

                const viewportWidth = document.documentElement.clientWidth;
                const overflowElements = [...document.querySelectorAll('body *')]
                    .filter((element) => {
                        const rect = element.getBoundingClientRect();
                        const style = getComputedStyle(element);
                        return style.display !== 'none'
                            && style.visibility !== 'hidden'
                            && rect.width > 0
                            && (rect.left < -0.5 || rect.right > viewportWidth + 0.5);
                    })
                    .slice(0, 100)
                    .map((element) => ({
                        element: identity(element),
                        text: element.textContent?.trim().replace(/\s+/g, ' ').slice(0, 120) ?? '',
                        rect: roundedRect(element.getBoundingClientRect()),
                    }));

                const controls = [...document.querySelectorAll('button, input, select, textarea, a[href]')]
                    .filter((element) => {
                        const rect = element.getBoundingClientRect();
                        const style = getComputedStyle(element);
                        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
                    })
                    .slice(0, 80)
                    .map((element) => ({
                        element: identity(element),
                        label: (element.getAttribute('aria-label') || element.textContent || element.getAttribute('placeholder') || element.getAttribute('name') || '').trim().replace(/\s+/g, ' ').slice(0, 100),
                        rect: roundedRect(element.getBoundingClientRect()),
                    }));

                const firstCta = [...document.querySelectorAll('main .et-btn, main button[data-booking-next], main button[type="submit"]')]
                    .find((element) => {
                        const rect = element.getBoundingClientRect();
                        const style = getComputedStyle(element);
                        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
                    });
                const form = [...document.querySelectorAll('main form')].find((candidate) => {
                    const rect = candidate.getBoundingClientRect();
                    const style = getComputedStyle(candidate);
                    return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
                });

                return {
                    document: {
                        clientWidth: document.documentElement.clientWidth,
                        scrollWidth: document.documentElement.scrollWidth,
                        scrollHeight: document.documentElement.scrollHeight,
                        horizontalOverflowPx: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
                    },
                    overflowElements,
                    typography: {
                        body: typography('body'),
                        h1: typography('h1'),
                        h2: typography('h2'),
                        firstField: typography('main input:not([type="hidden"]), main select, main textarea'),
                    },
                    controls,
                    firstUsefulCta: firstCta ? {
                        element: identity(firstCta),
                        label: firstCta.textContent?.trim().replace(/\s+/g, ' ').slice(0, 160) ?? '',
                        rect: roundedRect(firstCta.getBoundingClientRect()),
                    } : null,
                    firstForm: form ? {
                        element: identity(form),
                        rect: roundedRect(form.getBoundingClientRect()),
                    } : null,
                };
            });

            const sizeName = `${viewport.width}x${viewport.height}`;
            const viewportFile = path.join(outputRoot, `${pageDefinition.name}-${sizeName}-viewport.png`);
            await page.screenshot({ path: viewportFile, fullPage: false });

            let fullPageFile = null;
            if (viewport.width === 1440 || viewport.width === 390) {
                fullPageFile = path.join(outputRoot, `${pageDefinition.name}-${sizeName}-fullpage.png`);
                await page.screenshot({ path: fullPageFile, fullPage: true });
            }

            report.captures.push({
                page: pageDefinition.name,
                route: pageDefinition.route,
                url,
                status: response?.status() ?? null,
                viewport,
                viewportFile,
                fullPageFile,
                metrics,
            });

            console.log(`${pageDefinition.name} ${sizeName}: ${viewportFile}${fullPageFile ? ` | ${fullPageFile}` : ''}`);
            await context.close();
        }
    }
} finally {
    await browser.close();
}

const reportPath = path.join(outputRoot, 'metrics.json');
await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
console.log(`Metrics: ${reportPath}`);
