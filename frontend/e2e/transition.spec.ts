import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { login, resetDatabase, USERS } from './support';

test.beforeAll(() => resetDatabase());

test.describe('legacy URLs load the Angular app', () => {
  for (const url of ['/events/1', '/social-events/1', '/?date=2099-6-1']) {
    test(url, async ({ page }) => {
      await page.goto(url);
      await expect(page.locator('app-header')).toBeVisible();
      await expect(page.locator('app-footer')).toBeVisible();
    });
  }

  test('/?date= opens the right month with its events', async ({ page }) => {
    await page.goto('/?date=2099-6-1');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('2099');
    await expect(page.locator('[data-card]:visible', { hasText: 'E2E Turnier' }).first()).toBeVisible();
  });
});

test('a stale legacy form POST is redirected with 303 to the same URL', async ({ request }) => {
  const response = await request.post('/events/1/register', { form: { comment: '' }, maxRedirects: 0 });
  expect(response.status()).toBe(303);
  expect(new URL(response.headers()['location'], 'http://x').pathname).toBe('/events/1/register');

  const social = await request.post('/social-events/1/unregister', { form: {}, maxRedirects: 0 });
  expect(social.status()).toBe(303);
  expect(new URL(social.headers()['location'], 'http://x').pathname).toBe('/social-events/1/unregister');
});

test('after the 303 the browser lands on the page the form belonged to', async ({ page }) => {
  await login(page, USERS.member);
  for (const [stale, target] of [
    ['/events/1/register', '/events/1'],
    ['/social-events/1/unregister', '/social-events/1'],
  ]) {
    await page.goto(stale);
    await expect(page).toHaveURL(new RegExp(`${target}$`));
    await expect(page.locator('app-header')).toBeVisible();
  }
});

test('the web manifest is unchanged', async ({ request }) => {
  const repoManifest = JSON.parse(readFileSync(join(__dirname, '..', 'flavors', 'elfaro', 'assets', 'site.webmanifest'), 'utf8'));
  const response = await request.get('/site.webmanifest');
  expect(response.status()).toBe(200);
  const served = await response.json();
  expect(served.name).toBe(repoManifest.name);
  expect(served.icons).toEqual(repoManifest.icons);
  expect(served.start_url).toBe('/');
  expect(served).toEqual(repoManifest);
  for (const icon of served.icons as { src: string }[]) {
    expect((await request.get(icon.src)).status()).toBe(200);
  }
});

test.describe('internal files are never served', () => {
  for (const path of [
    '/src/Core/Config.php',
    '/vendor/autoload.php',
    '/logs/',
    '/php.ini',
    '/bootstrap.php',
    '/.env',
    '/config/production.php',
    '/storage/cache/.gitkeep',
  ]) {
    test(path, async ({ request }) => {
      expect((await request.get(path)).status()).toBe(404);
    });
  }

  // Apache's built-in ".ht*" protection answers before mod_rewrite, hence 403 instead of 404.
  test('/.htaccess', async ({ request }) => {
    expect([403, 404]).toContain((await request.get('/.htaccess')).status());
  });

  test('only index.php may run', async ({ request }) => {
    expect((await request.get('/nothing.php')).status()).toBe(404);
  });
});

test('/health is public and reveals nothing but the status', async ({ request }) => {
  const response = await request.get('/health');
  expect(response.status()).toBe(200);
  expect(await response.json()).toEqual({ status: 'ok' });
});

test('the API is routed to PHP (not blocked by the .php rule)', async ({ request }) => {
  const me = await request.get('/api/auth/me');
  expect(me.status()).toBe(200);
  expect(await me.json()).toEqual({ user: null });
  expect(me.headers()['cache-control']).toContain('no-store');
  expect((await request.get('/api/calendar/2099-06')).status()).toBe(200);
  expect((await request.get('/api/does-not-exist')).status()).toBe(404);
});

test('the service worker manifest is served and not cached', async ({ request }) => {
  const response = await request.get('/ngsw.json');
  expect(response.status()).toBe(200);
  expect(response.headers()['cache-control']).toContain('no-cache');
  expect(await response.json()).toHaveProperty('assetGroups');
});

test('caching headers: versioned assets are immutable, index.html is not cached', async ({ request }) => {
  const index = await request.get('/index.html');
  expect(index.status()).toBe(200);
  expect(index.headers()['cache-control']).toContain('no-cache');

  const html = await index.text();
  const main = /src="(\/app\/[^"]*\/main-[^"]+\.js)"/.exec(html)?.[1];
  expect(main, 'main bundle referenced from index.html').toBeTruthy();
  const asset = await request.get(main!);
  expect(asset.status()).toBe(200);
  expect(asset.headers()['cache-control']).toContain('immutable');
  expect(asset.headers()['cache-control']).toContain('max-age=31536000');

  expect((await request.get('/version.txt')).headers()['cache-control']).toContain('no-cache');
});

test('the font is hosted with the app: no request to Google Fonts', async ({ page }) => {
  const external: string[] = [];
  page.on('request', (request) => {
    const host = new URL(request.url()).hostname;
    if (host.endsWith('googleapis.com') || host.endsWith('gstatic.com')) external.push(request.url());
  });
  await page.goto('/');
  await expect(page.locator('app-header')).toBeVisible();
  await page.evaluate(() => document.fonts.ready);
  expect(await page.evaluate(() => document.fonts.check('16px "Hind Guntur"'))).toBe(true);
  expect(external).toEqual([]);
});

test('old Amigos links redirect permanently', async ({ request }) => {
  const event = await request.get('/event.php?id=1', { maxRedirects: 0 });
  expect(event.status()).toBe(301);
  expect(new URL(event.headers()['location'], 'http://x').pathname).toBe('/events/1');

  for (const path of ['/index.php', '/login.php', '/add-event.php?date=2099-01-01', '/admin/users.php']) {
    const res = await request.get(path, { maxRedirects: 0 });
    expect(res.status(), path).toBe(301);
    expect(new URL(res.headers()['location'], 'http://x').pathname, path).toBe('/');
  }

  // the API still runs through index.php (internal rewrite, no redirect)
  expect((await request.get('/api/auth/me')).status()).toBe(200);
});
