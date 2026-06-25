import { test, expect } from '@playwright/test';

test('admin can log in and reach the dashboard', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input#email', 'admin@laranode.test');
    await page.fill('input#password', 'password');
    await page.click('button:has-text("Log in")');
    await expect(page).toHaveURL(/dashboard/);
});

test('the websites page renders for an authenticated admin', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input#email', 'admin@laranode.test');
    await page.fill('input#password', 'password');
    await page.click('button:has-text("Log in")');
    await page.waitForURL(/dashboard/);
    await page.goto('/websites');
    await expect(page.locator('body')).toContainText(/website/i);
});
