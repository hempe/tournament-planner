import { expect, test, type Page } from '@playwright/test';
import { confirmDialog, login, resetDatabase, USERS } from './support';

// Runs only against the Amigos build (E2E_FLAVOR=amigos, seed e2e/seed-amigos.sql — see e2e/run.sh).
test.beforeEach(() => resetDatabase());

const MEMBER_BY_NAME = { username: 'Muster Max', password: USERS.member.password };

async function joinWaitlist(page: Page): Promise<void> {
  await page.goto('/events/1');
  await page.getByRole('button', { name: 'Warteliste', exact: true }).click();
  await confirmDialog(page);
  await expect(page.getByText('Auf Warteliste', { exact: true })).toBeVisible();
}

function waitlist(page: Page) {
  return page.locator('[data-participant-group]', { hasText: 'Warteliste' }).locator('li');
}

test('Amigos branding; no El Faro, no guest or dinner API', async ({ page, request }) => {
  await page.goto('/');
  await expect(page).toHaveTitle('Amigos de Tauro');
  await expect(page.getByTestId('site-name')).toContainText('AMIGOS DE TAURO');
  await expect(page.locator('body')).not.toContainText('EL FARO');
  expect(await (await request.get('/site.webmanifest')).json()).toMatchObject({ name: 'Amigos de Tauro' });
  expect((await request.get('/api/events/1/guest-info')).status()).toBe(404);
  expect((await request.get('/api/social-events/1')).status()).toBe(404);
});

test('login with "Lastname Firstname"; handicap shown in the participant list', async ({ page }) => {
  await login(page, MEMBER_BY_NAME);
  await joinWaitlist(page);

  await expect(waitlist(page)).toHaveText(['Max Muster · HCP 12.4']);
});

test('the waitlist is ordered by priority, not by registration time', async ({ page }) => {
  await login(page, MEMBER_BY_NAME); // priority 2000, registers first
  await joinWaitlist(page);
  await page.context().clearCookies();

  await login(page, USERS.other); // priority 1000, registers later
  await joinWaitlist(page);

  await expect(waitlist(page)).toHaveText(['Olga Other', 'Max Muster · HCP 12.4']);
});

test('locked tournament shows the Amigos text', async ({ page }) => {
  await login(page, USERS.member);
  await page.goto('/events/2');
  await expect(page.getByText(/Arno anrufen/)).toBeVisible();
});

test('dark mode on phones shows the background photo', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'amigos-mobile', 'dark mode is checked on the phone project');
  await page.goto('/');
  const background = await page.evaluate(() => getComputedStyle(document.body).backgroundImage);
  expect(background).toContain('background_low_res.png');
});
