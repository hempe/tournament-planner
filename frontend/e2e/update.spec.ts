import { expect, test } from '@playwright/test';
import { deploy, VERSION_1, VERSION_2 } from './support';

// Deploys a second build into the running site and expects the open app to offer the update.
// Needs the two builds made by e2e/run.sh. Excluded with E2E_SKIP_SLOW=1.
test.describe('@slow service worker update', () => {
  test.afterAll(() => deploy('.dist-v1'));

  test('registers the service worker, offers the new version and loads it', async ({ page }) => {
    test.setTimeout(120_000);

    await page.goto('/');
    await expect(page.locator('app-footer')).toContainText(VERSION_1);

    // The worker registers once the app is stable and then caches the release; wait until it controls the page.
    await expect
      .poll(async () => page.evaluate(async () => (await navigator.serviceWorker.getRegistration())?.active?.state ?? null), {
        timeout: 60_000,
      })
      .toBe('activated');
    await page.reload();
    await expect.poll(() => page.evaluate(() => navigator.serviceWorker.controller !== null)).toBe(true);
    await expect(page.locator('app-footer')).toContainText(VERSION_1);
    await expect(page.getByText('Eine neue Version ist verfügbar.')).toHaveCount(0);

    // "Upload" the new release, then reload: the worker still serves v1 but detects v2 in the background.
    deploy('.dist-v2');
    await page.reload();
    const toast = page.locator('[data-sonner-toast]').filter({ hasText: 'Eine neue Version ist verfügbar.' });
    await expect(toast).toBeVisible({ timeout: 30_000 });

    await toast.getByRole('button', { name: 'Neu laden' }).click();
    await expect(page.locator('app-footer')).toContainText(VERSION_2, { timeout: 30_000 });
    await expect(page.getByText('Eine neue Version ist verfügbar.')).toHaveCount(0);
  });
});
