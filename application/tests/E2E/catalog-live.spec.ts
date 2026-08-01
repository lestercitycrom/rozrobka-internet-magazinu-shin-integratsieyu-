import { expect, test } from '@playwright/test';

test('catalog filters update without reload, preserve URL history, and show translated image controls', async ({ page }, testInfo) => {
    const isRussian = testInfo.project.name === 'buyer-ru-preview';
    const catalogPath = isRussian ? '/ru/felger' : '/felger';
    const boltLabel = isRussian ? 'Количество болтов' : 'Antall bolter';
    const imageLabel = isRussian ? 'Только с фото товара' : 'Kun med produktbilde';
    const loadingLabel = isRussian ? 'Обновляем товары…' : 'Oppdaterer produkter …';

    await page.goto(catalogPath);
    const liveRoot = page.locator('[data-catalog-live-root]');
    const filters = page.locator('[data-mobile-filter]');
    await expect(liveRoot).toHaveAttribute('data-catalog-live-ready', 'true');
    await expect(filters).toHaveAttribute('data-filter-ready', 'true');
    if (!(await page.getByTestId('catalog-filters').isVisible())) {
        await filters.locator('summary').click();
    }

    await expect(page.getByLabel(boltLabel)).toBeVisible();
    await expect(page.getByLabel(imageLabel)).toBeVisible();
    await expect(page.locator('[data-catalog-loading]')).toContainText(loadingLabel);
    await expect(page.locator('[data-catalog-loading]')).toBeHidden();
    await expect(page.locator('body')).not.toContainText('storefront.bolt_holes');
    await expect(page.locator('body')).not.toContainText('storefront.has_image');
    await expect(page.getByTestId('catalog-product')).toContainText('Demo 8x18 5x112');

    const documentMarker = await page.evaluate(() => {
        const browserWindow = window as Window & { __catalogLiveDocumentMarker?: string };
        browserWindow.__catalogLiveDocumentMarker = crypto.randomUUID();
        return browserWindow.__catalogLiveDocumentMarker;
    });

    let delayedLiveRequest = false;
    await page.route('**/*', async (route) => {
        const request = route.request();
        const requestUrl = new URL(request.url());
        if (!delayedLiveRequest
            && request.headers()['x-requested-with'] === 'XMLHttpRequest'
            && requestUrl.pathname === catalogPath) {
            delayedLiveRequest = true;
            await new Promise((resolve) => setTimeout(resolve, 200));
        }
        await route.continue();
    });

    await page.locator('select[name="diameter"]').selectOption('18');
    await expect(liveRoot).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-catalog-loading]')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`${catalogPath}\\?diameter=18`));
    await expect(liveRoot).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('[data-catalog-loading]')).toBeHidden();
    await expect(page.getByTestId('catalog-product')).toContainText('Demo 8x18 5x112');
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogLiveDocumentMarker?: string }).__catalogLiveDocumentMarker)).toBe(documentMarker);

    await page.getByLabel(isRussian ? 'Поиск' : 'Søk').fill('Demo');
    await expect(page).toHaveURL(/diameter=18&q=Demo|q=Demo&diameter=18/);
    await expect(page.getByTestId('catalog-product')).toContainText('Demo 8x18 5x112');

    await page.getByLabel(imageLabel).check();
    await expect(page).toHaveURL(/has_image=1/);
    await expect(page.getByTestId('catalog-empty')).toBeVisible();
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogLiveDocumentMarker?: string }).__catalogLiveDocumentMarker)).toBe(documentMarker);

    await page.goBack();
    await expect(page).not.toHaveURL(/has_image=1/);
    await expect(page.getByLabel(imageLabel)).not.toBeChecked();
    await expect(page.getByTestId('catalog-product')).toContainText('Demo 8x18 5x112');
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogLiveDocumentMarker?: string }).__catalogLiveDocumentMarker)).toBe(documentMarker);

    await page.goForward();
    await expect(page).toHaveURL(/has_image=1/);
    await expect(page.getByLabel(imageLabel)).toBeChecked();
    await expect(page.getByTestId('catalog-empty')).toBeVisible();
    await expect.poll(() => page.evaluate(() => (window as Window & { __catalogLiveDocumentMarker?: string }).__catalogLiveDocumentMarker)).toBe(documentMarker);
});
