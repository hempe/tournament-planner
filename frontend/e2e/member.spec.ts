import { expect, test } from '@playwright/test';
import { confirmDialog, login, resetDatabase, toast, USERS } from './support';

test.beforeEach(async ({ page }) => {
  resetDatabase();
  await login(page, USERS.member);
});

test('register with a comment from the detail page', async ({ page }) => {
  await page.goto('/events/1');
  await page.getByLabel('Kommentar').fill('Bringe einen Gast mit');
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click();
  await confirmDialog(page);
  await expect(toast(page, 'Erfolgreich angemeldet')).toBeVisible();
  await expect(page.getByText('Angemeldet', { exact: true }).first()).toBeVisible();

  await page.reload();
  await expect(page.getByLabel('Kommentar')).toHaveValue('Bringe einen Gast mit');
  await expect(page.getByRole('button', { name: 'Turnier abmelden' })).toBeVisible();
});

test('registering for a tournament with a dinner offers the dinner and registers for it with menu and table', async ({ page }) => {
  await page.goto('/events/4');
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click();
  await confirmDialog(page);
  await expect(page.getByRole('alertdialog')).toContainText("Möchtest du auch an 'E2E Abendessen' teilnehmen?");
  await confirmDialog(page, 'Ja, anmelden');
  await expect(page).toHaveURL(/\/social-events\/1$/);

  await page.getByLabel('Menü').selectOption({ label: 'Fisch' });
  await page.getByLabel('Tisch', { exact: true }).selectOption({ label: 'Tisch 2' });
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click();
  await confirmDialog(page);

  await expect(page.getByText('Angemeldet', { exact: true })).toBeVisible();
  await expect(page.getByText('Fisch')).toBeVisible();
  await expect(page.getByText('Tisch 2').first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Anlass abmelden' })).toBeVisible();
});

test('declining the dinner prompt stays on the tournament', async ({ page }) => {
  await page.goto('/events/4');
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click();
  await confirmDialog(page);
  await page.getByRole('alertdialog').getByRole('button', { name: 'Nein danke' }).click();
  await expect(page).toHaveURL(/\/events\/4$/);
  await expect(page.getByRole('button', { name: 'Turnier abmelden' })).toBeVisible();
});

test('unregistering from "Turnier & Abendessen" removes both registrations', async ({ page }) => {
  await page.goto('/events/6');
  await page.getByRole('button', { name: 'Turnier abmelden' }).click();
  await expect(page.getByRole('alertdialog')).toContainText('Von welchem Anlass möchtest du dich abmelden?');
  await confirmDialog(page, 'Turnier & Abendessen');
  await expect(toast(page, 'Erfolgreich abgemeldet')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Anmelden', exact: true })).toBeVisible();

  await page.goto('/social-events/2');
  await expect(page.getByRole('button', { name: 'Anlass abmelden' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Anmelden', exact: true })).toBeVisible();
});

test('registering for a full tournament puts the member on the waitlist', async ({ page }) => {
  await page.goto('/events/2');
  await page.getByRole('button', { name: 'Warteliste', exact: true }).click();
  await expect(page.getByRole('alertdialog')).toContainText('Dieser Anlass ist ausgebucht');
  await confirmDialog(page);
  await expect(toast(page, 'Erfolgreich angemeldet')).toBeVisible();
  await expect(page.getByText('Auf Warteliste', { exact: true })).toBeVisible();
});

test('quick registration from the calendar card', async ({ page }) => {
  await page.goto('/?month=2099-06');
  const card = page.locator('[data-card]:visible').filter({ hasText: 'E2E Turnier' }).filter({ hasNotText: /Voll|Gesperrt|Dinner|Admin/ }).first();
  await card.getByRole('button', { name: 'Anmelden' }).click();
  await confirmDialog(page);
  await expect(toast(page, 'Erfolgreich angemeldet')).toBeVisible();
  await expect(card).toContainText('Angemeldet');
});
