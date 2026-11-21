import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests run against the production build served by Apache + PHP 8.3 + MariaDB in Docker.
 * Start the stack with `npm run e2e` (builds, starts Docker, runs these tests, tears everything down).
 * All specs share one database, so they run serially; every mutating spec resets it first.
 */
const baseURL = `http://localhost:${process.env['E2E_PORT'] ?? '8080'}`;

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  // The service-worker update test (update.spec.ts) is tagged @slow; `npm run e2e` includes it,
  // set E2E_SKIP_SLOW=1 to leave it out.
  grepInvert: process.env['E2E_SKIP_SLOW'] ? /@slow/ : undefined,
  use: {
    baseURL,
    locale: 'de-CH',
    timezoneId: 'Europe/Zurich',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    {
      name: 'mobile',
      testMatch: /(auth|member|guest)\.spec\.ts/,
      use: { ...devices['iPhone 13'], browserName: 'chromium', defaultBrowserType: 'chromium' },
    },
  ],
});
