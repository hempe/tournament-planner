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
  // E2E_FLAVOR=amigos (set by e2e/run.sh for its second run) runs only amigos.spec.ts against the Amigos build.
  projects:
    process.env['E2E_FLAVOR'] === 'amigos'
      ? [
          { name: 'amigos-desktop', testMatch: /amigos\.spec\.ts/, use: { ...devices['Desktop Chrome'] } },
          {
            name: 'amigos-mobile',
            testMatch: /amigos\.spec\.ts/,
            use: { ...devices['iPhone 13'], browserName: 'chromium', defaultBrowserType: 'chromium', colorScheme: 'dark' },
          },
        ]
      : [
          { name: 'desktop', testIgnore: /amigos\.spec\.ts/, use: { ...devices['Desktop Chrome'] } },
          {
            name: 'mobile',
            testMatch: /(auth|member|guest)\.spec\.ts/,
            use: { ...devices['iPhone 13'], browserName: 'chromium', defaultBrowserType: 'chromium' },
          },
        ],
});
