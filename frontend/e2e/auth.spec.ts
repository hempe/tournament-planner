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
