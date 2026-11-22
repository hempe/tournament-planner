import { expect, test } from '@playwright/test';
import { login, resetDatabase, toast, USERS } from './support';

test.beforeEach(() => resetDatabase());

test('anonymous visitors see a login link and no logout button', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('link', { name: 'Mitglieder Anmeldung' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Abmelden' })).toHaveCount(0);
});

test('wrong credentials show an error and keep the user on the login page', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Benutzername').fill('member');
  await page.getByLabel('Passwort').fill('wrong-password');
  await page.getByRole('button', { name: 'Mitglieder Anmeldung' }).click();
  await expect(page.getByRole('alert')).toHaveText('Anmeldung fehlgeschlagen');
  await expect(page).toHaveURL(/\/login$/);
});

test('login and logout', async ({ page }) => {
  await login(page, USERS.member);
  await expect(page).toHaveURL('/');
  await expect(page.locator('header')).toContainText('Max Muster');

  await page.getByRole('button', { name: 'Abmelden' }).click();
  await expect(toast(page, 'Erfolgreich abgemeldet')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Mitglieder Anmeldung' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Abmelden' })).toHaveCount(0);
});

test('a protected page redirects to the login and returns after signing in', async ({ page }) => {
  await page.goto('/events/1');
  await expect(page).toHaveURL(/\/login/);
  await page.getByLabel('Benutzername').fill('member');
  await page.getByLabel('Passwort').fill('Member123!');
  await page.getByRole('button', { name: 'Mitglieder Anmeldung' }).click();
  await expect(page).toHaveURL(/\/events\/1$/);
  await expect(page.getByRole('heading', { name: 'E2E Turnier' })).toBeVisible();
});

test('a member cannot open admin pages', async ({ page }) => {
  await login(page, USERS.member);
  await page.goto('/users');
  await expect(page.getByRole('heading', { name: 'Benutzer', exact: true })).toHaveCount(0);
});

test('branding header: green account bar above the logo and club name', async ({ page, isMobile }) => {
  await page.goto('/');
  const logo = page.locator('header img[src="/favicon.svg"]');
  await expect(logo).toBeVisible();
  await expect(page.getByTestId('site-name')).toContainText('EL FARO DE MASPALOMAS');

  const logoBox = (await logo.boundingBox())!;
  expect(logoBox.width).toBe(isMobile ? 75 : 100);

  // Login lives in the green bar at the top (as the utility bar on www.golfelfaro.es), right-aligned
  const login = page.getByRole('link', { name: 'Mitglieder Anmeldung' });
  const loginBox = (await login.boundingBox())!;
  const viewport = page.viewportSize()!;
  expect(loginBox.y + loginBox.height).toBeLessThanOrEqual(logoBox.y);
  expect(loginBox.x + loginBox.width).toBeGreaterThan(viewport.width - 40);
  const barColour = await login.evaluate((el) => getComputedStyle(el.closest('.bg-primary')!).backgroundColor);
  expect(barColour).toBe('rgb(135, 183, 35)');
});
