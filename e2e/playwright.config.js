// Playwright config for Amelia's by EAT E2E smoke specs (Task 7.3).
//
// Base URL comes from E2E_BASE_URL (default local dev). Set it to the staging
// mount when running against staging:
//   E2E_BASE_URL=https://parityrfp.com/cs/amelias npx playwright test
//
// These specs assert the page renders and the happy path runs up to the Stripe
// step. The Stripe-card portion is documented test data (see README); steps that
// require a live Stripe test mode are marked test.skip until creds exist.

// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';

module.exports = defineConfig({
  testDir: __dirname,
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
