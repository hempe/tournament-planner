import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, type Page } from '@playwright/test';

const root = join(__dirname, '..', '..');

export const DB_CONTAINER = 'tp-e2e-db';

/** Drops the database and recreates it from database/init.sql + the seed (E2E_SEED, default e2e/seed.sql), about one second. */
export function resetDatabase(): void {
  const seed = process.env['E2E_SEED'] ?? 'e2e/seed.sql';
  const sql = ['database/init.sql', seed, 'e2e/grant.sql'].map((f) => readFileSync(join(root, f), 'utf8')).join('\n');
  execSync(`docker exec -i ${DB_CONTAINER} mariadb -uroot -pe2e-root -e "DROP DATABASE IF EXISTS TPDb"`, { stdio: 'pipe' });
  execSync(`docker exec -i ${DB_CONTAINER} mariadb -uroot -pe2e-root`, { input: sql, stdio: ['pipe', 'pipe', 'pipe'] });
}

export const USERS = {
  admin: { username: 'admin', password: 'Admin123!' },
  member: { username: 'member', password: 'Member123!' },
  other: { username: 'other', password: 'Other123!' },
} as const;

export async function login(page: Page, user: { username: string; password: string }): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Benutzername').fill(user.username);
  await page.getByLabel('Passwort').fill(user.password);
  await page.getByRole('button', { name: 'Mitglieder Anmeldung' }).click();
  await expect(page.getByRole('button', { name: 'Abmelden' })).toBeVisible();
}

/** Confirms the open confirmation dialog (default action label "Bestätigen"). */
export async function confirmDialog(page: Page, label = 'Bestätigen'): Promise<void> {
  const dialog = page.getByRole('alertdialog');
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: label, exact: true }).click();
}

export function toast(page: Page, text: string | RegExp) {
  return page.locator('[data-sonner-toast]').filter({ hasText: text });
}

/** Versions of the two builds made by e2e/run.sh (build.sh appends "-dirty" on an unclean tree, so match by prefix). */
export const VERSION_1 = 'e2e-1';
export const VERSION_2 = 'e2e-2';

/** Merges a build's dist/ into the running site, like uploading it to the server. */
export function deploy(distDir: '.dist-v1' | '.dist-v2'): void {
  execSync(`bash e2e/stack.sh deploy e2e/${distDir}`, { cwd: root, stdio: 'pipe' });
}
