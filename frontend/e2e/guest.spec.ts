import { expect, test } from '@playwright/test';
import { resetDatabase, toast } from './support';

test.beforeEach(() => resetDatabase());

test('anonymous guest registers from the calendar', async ({ page }) => {
  await page.goto('/?month=2099-06');
  await page.locator('[data-card]:visible').filter({ hasText: 'E2E Turnier' }).filter({ hasNotText: /Voll|Gesperrt|Dinner|Admin/ }).first().getByRole('link').click();
  await expect(page).toHaveURL(/\/events\/1\/guests\/new$/);

  await page.getByLabel('Anrede').selectOption({ label: 'Herr' });
  await page.getByLabel('Vorname').fill('Gustav');
  await page.getByLabel('Nachname').fill('Gast');
  await page.getByLabel('eMail').fill('gustav@example.com');
  await page.getByLabel('Handicap').fill('18.5');
  await page.getByRole('button', { name: 'Als Gast anmelden' }).click();

  await expect(toast(page, 'Sie wurden erfolgreich auf die Warteliste gesetzt')).toBeVisible();
  await expect(page).toHaveURL('/');
});

test('required guest fields are validated', async ({ page }) => {
  await page.goto('/events/1/guests/new');
  await page.getByRole('button', { name: 'Als Gast anmelden' }).click();
  await expect(page.locator('#firstName-error')).toBeVisible();
  await expect(page).toHaveURL(/\/guests\/new$/);
});

test('a locked event shows the locked message instead of the form', async ({ page }) => {
  await page.goto('/events/3/guests/new');
  await expect(page.getByRole('heading', { name: 'E2E Gesperrt' })).toBeVisible();
  await expect(page.getByText('Anmeldung geschlossen, bitte bei kurzfristigen Änderungen')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Als Gast anmelden' })).toHaveCount(0);
});
