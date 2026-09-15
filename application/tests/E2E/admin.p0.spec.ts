import { expect, test } from '@playwright/test';
import { ensureAdminLocale, expectNoGlobalOverflow, loginAdmin } from './helpers';

async function openDrawer(page: import('@playwright/test').Page): Promise<void> {
    const toggle = page.getByTestId('admin-menu-toggle');
    const sidebar = page.getByTestId('admin-sidebar');
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(sidebar).toHaveClass(/is-open/);
    await expect(sidebar.getByRole('navigation')).toBeVisible();
}

async function closeDrawerWithEscape(page: import('@playwright/test').Page): Promise<void> {
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('admin-menu-toggle')).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByTestId('admin-sidebar')).not.toHaveClass(/is-open/);
}

test('mobile admin drawer works in NB and RU without horizontal overflow', async ({ page }) => {
    await loginAdmin(page);
    await ensureAdminLocale(page, 'nb');
    await expectNoGlobalOverflow(page);

    await openDrawer(page);
    const nbSidebar = page.getByTestId('admin-sidebar');
    const catalogLink = nbSidebar.locator('a[href$="/admin/catalog"]');
    await expect(catalogLink).toContainText('Katalog');
    await catalogLink.click();
    await expect(page).toHaveURL(/\/admin\/catalog/);
    await expectNoGlobalOverflow(page);

    await openDrawer(page);
    await closeDrawerWithEscape(page);
    await expect(page.getByTestId('admin-menu-toggle')).toBeFocused();

    await page.getByTestId('switch-language-ru').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ru');

    await page.goto('/admin/clients/create');
    const menuBox = await page.getByTestId('admin-menu-toggle').boundingBox();
    expect(menuBox?.width).toBeGreaterThanOrEqual(44);
    const logout = page.getByTestId('admin-logout');
    await expect(logout).toBeVisible();
    expect(await logout.evaluate((element) => Number.parseFloat(getComputedStyle(element).fontSize))).toBeGreaterThanOrEqual(11);
    await expectNoGlobalOverflow(page);

    await openDrawer(page);
    const ruSidebar = page.getByTestId('admin-sidebar');
    await expect(ruSidebar.locator('a[href$="/admin/catalog"]')).toContainText('Каталог');
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('admin-menu-toggle')).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByTestId('admin-sidebar')).not.toHaveClass(/is-open/);
    await expectNoGlobalOverflow(page);
});
