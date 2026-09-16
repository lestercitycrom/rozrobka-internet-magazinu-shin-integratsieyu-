// Read-only public-page evidence. No account, cart, order or integration mutations.
// Run from application/: QA_BASE=http://127.0.0.1:8045 node tests/Audit/ui-polish-capture.mjs
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const base = process.env.QA_BASE ?? 'http://127.0.0.1:8045';
const origin = new URL(base).origin;
if (!['http://127.0.0.1:8045', 'https://staging-easytire.bethub.fun'].includes(origin)) throw new Error('Unapproved audit target');
const run = process.env.QA_RUN ?? 'public-after';
if (!/^[a-z0-9-]+$/.test(run)) throw new Error('Invalid artifact directory name');
const out = path.resolve('storage/app/qa-ui-polish', run);
await mkdir(out, { recursive: true });
const report = { base, generatedAt: new Date().toISOString(), marker: null, pages: [], blocked: [] };
const browser = await chromium.launch({ headless: true });
report.browser = `Chromium ${browser.version()}`;
const viewports = process.env.QA_MATRIX === 'extended'
    ? [[320, 568], [390, 844], [440, 956], [768, 1024], [1024, 768], [1440, 900], [1920, 1080], [844, 390], [2560, 1080]]
    : [[1440, 900], [390, 844], [320, 568]];
const context = await browser.newContext({ serviceWorkers: 'block' });
await context.route('**/*', async route => {
    const request = route.request();
    if (!['GET', 'HEAD'].includes(request.method())) {
        report.blocked.push({ method: request.method(), url: request.url() });
        return route.abort();
    }
    // External map/social/video providers are not exercised by this visual audit.
    if (new URL(request.url()).origin !== origin) return route.abort();
    return route.continue();
});
const page = await context.newPage();
let errors = [];
page.on('pageerror', error => errors.push(String(error)));
const routes = [
    ['home', '/'], ['contact', '/kontakt'], ['store', '/dekk-og-felger'],
    ['tires', '/dekk'], ['wheels', '/felger'], ['services', '/tjenester'],
    ['service', '/tjenester/dekkverksted'], ['seo', '/dekkhotell-tromso'],
    ['request', '/tjenester/dekkverksted/foresporsel'],
    ['seasonal', '/tjenester/mobilt-dekkskift/foresporsel'],
    ['prices', '/priser'], ['about', '/om-oss'], ['legal', '/kjopsvilkar'],
    ['privacy', '/personvern'], ['cart-empty', '/handlekurv'], ['404', '/polish-unknown-page'],
];
async function metrics() {
    return page.evaluate(() => {
        const visible = node => {
            const r = node.getBoundingClientRect(), s = getComputedStyle(node);
            return r.width > 0 && r.height > 0 && s.visibility !== 'hidden';
        };
        const type = node => {
            const s = getComputedStyle(node), r = node.getBoundingClientRect();
            return { selector: node.tagName.toLowerCase() + '.' + node.className,
                text: node.textContent.trim().replace(/\s+/g, ' ').slice(0, 100),
                font: s.fontFamily, size: s.fontSize, weight: s.fontWeight, lineHeight: s.lineHeight,
                color: s.color, background: s.backgroundColor, borderColor: s.borderColor,
                rect: { x: r.x, y: r.y, width: r.width, height: r.height } };
        };
        const textNodes = [...document.querySelectorAll('main h1, main h2, main h3, main p, main a, main button, main label, main dt, main dd, main small, main strong, main input, main select, main summary, main span')]
            .filter(visible).filter(n => n.textContent.trim() || n.matches('input,select'));
        return {
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            typography: textNodes.filter(n => n.matches('h1,h2,h3,.et-action-link,.et-btn,.et-label,.et-help,.et-badge')).map(type),
            heavy: textNodes.filter(n => Number(getComputedStyle(n).fontWeight) > 700).map(type),
            condensed: textNodes.filter(n => getComputedStyle(n).fontFamily.includes('Condensed')).map(type),
            shortControls: [...document.querySelectorAll('main .et-btn, main .et-action-link, main input:not([type=checkbox]):not([type=radio]):not([type=hidden]), main select, main [role=tab]')]
                .filter(visible).filter(n => n.getBoundingClientRect().height < 47).map(type),
            product: document.querySelector('[data-testid=catalog-product-link]')?.getAttribute('href') ?? null,
            badImages: [...document.images].filter(visible).filter(img => img.complete && img.naturalWidth === 0).map(img => img.currentSrc),
        };
    });
}
try {
    if (origin.startsWith('https:')) {
        const marker = await context.request.get(`${origin}/design-revision.txt`);
        report.marker = { status: marker.status(), value: (await marker.text()).trim() };
    }
    let product;
    const selectedNames = process.env.QA_NAMES?.split(',');
    for (const [name, route] of routes.filter(([name]) => !selectedNames || selectedNames.includes(name))) {
        for (const [width, height] of viewports) {
            errors = [];
            await page.setViewportSize({ width, height });
            const response = await page.goto(base + route, { waitUntil: 'domcontentloaded' });
            await page.evaluate(async () => {
                await document.fonts.ready;
                // Trigger each lazy media region, then return instantly before capture.
                for (let y = 0; y < document.documentElement.scrollHeight; y += 750) {
                    window.scrollTo({ top: y, behavior: 'instant' });
                    await new Promise(resolve => requestAnimationFrame(resolve));
                }
                window.scrollTo({ top: 0, behavior: 'instant' });
            });
            await page.waitForTimeout(180);
            const facts = await metrics();
            if (name === 'tires') product = facts.product;
            const filename = `${name}-${width}.png`;
            await page.screenshot({ path: path.join(out, filename), fullPage: true, animations: 'disabled' });
            if (['home', 'contact', 'tires', 'store'].includes(name)) {
                await page.screenshot({ path: path.join(out, `${name}-${width}-viewport.png`), animations: 'disabled' });
            }
            report.pages.push({ name, route, width, height, status: response?.status(), screenshot: filename, errors: [...errors], ...facts });
        }
    }
    if (product) {
        for (const [width, height] of viewports) {
            errors = [];
            await page.setViewportSize({ width, height });
            const response = await page.goto(product, { waitUntil: 'domcontentloaded' });
            await page.evaluate(() => document.fonts.ready);
            await page.screenshot({ path: path.join(out, `product-${width}.png`), fullPage: true, animations: 'disabled' });
            await page.screenshot({ path: path.join(out, `product-${width}-viewport.png`), animations: 'disabled' });
            report.pages.push({ name: 'product', route: new URL(product).pathname, width, height, status: response?.status(), errors: [...errors], ...await metrics() });
        }
    }
} finally {
    await writeFile(path.join(out, 'report.json'), JSON.stringify(report, null, 2) + '\n');
    await context.close();
    await browser.close();
}
const failures = report.pages.filter(p => p.overflow || p.errors.length || p.status !== (p.name === '404' ? 404 : 200));
console.log(JSON.stringify({ out, pages: report.pages.length, failures: failures.map(p => ({ name: p.name, width: p.width, status: p.status, overflow: p.overflow, errors: p.errors })),
    heavy: [...new Set(report.pages.flatMap(p => p.heavy.map(n => n.selector)))],
    condensed: [...new Set(report.pages.flatMap(p => p.condensed.map(n => n.selector)))],
    shortControls: [...new Set(report.pages.flatMap(p => p.shortControls.map(n => n.selector)))],
}, null, 2));
if (failures.length) process.exitCode = 1;
