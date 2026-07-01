import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30000,
    use: { baseURL: process.env.APP_URL || 'http://localhost', headless: true },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
