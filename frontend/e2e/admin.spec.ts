import { expect, test } from '@playwright/test';
import { confirmDialog, login, resetDatabase, toast, USERS } from './support';

test.beforeEach(async ({ page }) => {
  resetDatabase();
  await login(page, USERS.admin);
});

test('create an event', async ({ page }) => {
  await page.goto('/events/new');
  await page.locator('#name').fill('E2E Neu');
  await page.getByLabel('Datum').fill('2099-08-03');
  await page.getByLabel('Max. Teilnehmer').fill('12');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(toast(page, 'Anlass erfolgreich aktualisiert')).toBeVisible();
  await expect(page).toHaveURL(/\/events\/\d+\/admin$/);

  await page.goto('/events');
  await expect(page.getByRole('link', { name: 'E2E Neu' })).toBeVisible();
});

test('bulk create with preview', async ({ page }) => {
  // Saturdays in September 2099, counted the same way the server does.
  let saturdays = 0;
  for (let d = 1; d <= 30; d++) {
    if (new Date(Date.UTC(2099, 8, d)).getUTCDay() === 6) saturdays++;
  }

  await page.goto('/events/bulk/new');
  await page.getByLabel('Startdatum').fill('2099-09-01');
  await page.getByLabel('Enddatum').fill('2099-09-30');
  await page.getByLabel('Wochentag').selectOption({ label: 'Samstag' });
  await page.locator('#name').fill('E2E Bulk');
  await page.getByLabel('Max. Teilnehmer').fill('8');
  await page.getByRole('button', { name: 'Vorschau anzeigen' }).click();

  await expect(page.getByRole('heading', { name: `Vorschau: ${saturdays} Termine werden erstellt` })).toBeVisible();
  await expect(page.locator('[data-preview-row]')).toHaveCount(saturdays);
  await page.getByRole('button', { name: 'Alle erstellen' }).click();

  await expect(toast(page, `${saturdays} Termine erfolgreich erstellt`)).toBeVisible();
  await expect(page).toHaveURL(/\/events$/);
  await expect(page.getByRole('link', { name: 'E2E Bulk' })).toHaveCount(saturdays);
});

test('lock and unlock an event', async ({ page }) => {
  await page.goto('/events/5/admin');
  await page.getByRole('button', { name: 'Sperren', exact: true }).click();
  await confirmDialog(page);
  await expect(toast(page, 'Anlass gesperrt')).toBeVisible();

  await page.getByRole('button', { name: 'Entsperren', exact: true }).click();
  await confirmDialog(page);
  await expect(toast(page, 'Anlass entsperrt')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Sperren', exact: true })).toBeVisible();
});

test('export the participants as CSV', async ({ page }) => {
  await page.goto('/events/2/admin');
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Exportieren' }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toMatch(/\.csv$/);
  const stream = await download.createReadStream();
  const chunks: Buffer[] = [];
  for await (const chunk of stream) chunks.push(chunk as Buffer);
  const csv = Buffer.concat(chunks).toString('utf8');
  expect(csv).toContain('Olga Other');
});

test('create a user', async ({ page }) => {
  await page.goto('/users/new');
  await page.getByLabel('Anrede').selectOption({ label: 'Frau' });
  await page.getByLabel('Benutzername').fill('e2e-neu');
  await page.getByLabel('Passwort').fill('Sup3rSecret!pass');
  await page.getByLabel('Vorname').fill('Nora');
  await page.getByLabel('Nachname').fill('Neu');
  await page.locator('form button[type=submit]').click();
  await expect(toast(page, 'Benutzer erfolgreich erstellt')).toBeVisible();
  await expect(page).toHaveURL(/\/users$/);
  await expect(page.locator('[data-username]', { hasText: 'e2e-neu' })).toBeVisible();
});
