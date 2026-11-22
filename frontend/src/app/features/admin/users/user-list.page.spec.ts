import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ApiClient } from '../../../core/api/api-client';
import { AdminUserDto } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { UserListPage } from './user-list.page';
import { amigosFlavor, provideTestFlavor, setTestFlavor } from '../../../core/flavor/testing';

function user(id: number, username: string, over: Partial<AdminUserDto> = {}): AdminUserDto {
  return { id, username, firstName: 'Vor', lastName: `Name${id}`, displayName: `Vor Name${id}`, male: true, isAdmin: false, rfeg: null, memberNumber: null, phone: null, handicap: null, priority: null, nameConflict: false, ...over };
}

describe('UserListPage', () => {
  let api: Record<'get' | 'put' | 'post' | 'delete', ReturnType<typeof vi.fn>>;
  let confirm: { ask: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  const users = [user(1, 'anna', { isAdmin: true }), user(2, 'bert')];

  afterEach(() => setTestFlavor(null));

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue(users), put: vi.fn(), post: vi.fn(), delete: vi.fn() };
    confirm = { ask: vi.fn().mockResolvedValue(true) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideTestFlavor(), { provide: ApiClient, useValue: api }, { provide: ConfirmService, useValue: confirm }, { provide: Notify, useValue: notify }],
    });
    TestBed.inject(I18n).use('de', {
      users: {
        title: 'Benutzer', username: 'Benutzername', admin: 'Administrator', add_user: 'Benutzer hinzufügen', edit: 'Bearbeiten', delete: 'Löschen',
        give_admin_rights: 'Admin-Rechte geben', remove_admin_rights: 'Admin-Rechte entziehen',
        search: 'Benutzer suchen', no_users_found: 'Keine Benutzer gefunden.',
        give_admin_rights_confirm: ':username Admin rechte geben?', remove_admin_rights_confirm: ':username Admin rechte entnehmen?',
        delete_confirm: ':username definitiv löschen?', first_name: 'Vorname', last_name: 'Nachname',
      },
    });
  });

  async function render() {
    const fixture = TestBed.createComponent(UserListPage);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const row = (username: string) => Array.from(el.querySelectorAll<HTMLElement>('[data-user]')).find((r) => r.querySelector('[data-username]')?.textContent?.trim() === username)!;
    // Row actions are icon buttons; they are found by their aria-label.
    const buttonIn = (root: ParentNode, label: string) => root.querySelector<HTMLButtonElement>(`button[aria-label="${label}"]`) ?? undefined;
    const click = async (b: HTMLElement) => {
      b.click();
      await fixture.whenStable();
    };
    const search = async (text: string) => {
      const input = el.querySelector<HTMLInputElement>('[data-user-search]')!;
      input.value = text;
      input.dispatchEvent(new Event('input'));
      await fixture.whenStable();
    };
    return { el, row, buttonIn, click, search };
  }

  it('lists users with username, display name and an admin badge only for admins; links to add and edit', async () => {
    const { el, row } = await render();

    expect(api.get).toHaveBeenCalledWith('/users', expect.anything());
    expect(el.querySelector('h1')?.textContent).toContain('Benutzer');
    expect(el.querySelectorAll('[data-user]')).toHaveLength(2);
    expect(row('anna').textContent).toContain('Vor Name1');
    expect(row('anna').querySelector('[data-admin-badge]')?.textContent).toContain('Administrator');
    expect(row('bert').querySelector('[data-admin-badge]')).toBeNull();
    const hrefs = Array.from(el.querySelectorAll('a')).map((a) => a.getAttribute('href'));
    expect(hrefs).toContain('/users/new');
    expect(hrefs).toContain('/users/2/edit');
    expect(row('bert').querySelector('a[href="/users/2/edit"]')?.getAttribute('aria-label')).toBe('Bearbeiten');
  });

  it('filters by display name or username; shows a message when nothing matches', async () => {
    const { el, search } = await render();

    await search('name2');
    expect(Array.from(el.querySelectorAll('[data-username]')).map((u) => u.textContent?.trim())).toEqual(['bert']);
    await search('ANNA');
    expect(Array.from(el.querySelectorAll('[data-username]')).map((u) => u.textContent?.trim())).toEqual(['anna']);
    await search('zzz');
    expect(el.querySelectorAll('[data-user]')).toHaveLength(0);
    expect(el.textContent).toContain('Keine Benutzer gefunden.');
    await search('');
    expect(el.querySelectorAll('[data-user]')).toHaveLength(2);
  });

  it('grants admin rights after confirming and re-renders from the returned user', async () => {
    api.put.mockResolvedValue(user(2, 'bert', { isAdmin: true }));
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('bert'), 'Admin-Rechte geben')!);

    expect(confirm.ask).toHaveBeenCalledWith('bert Admin rechte geben?');
    expect(api.put).toHaveBeenCalledWith('/users/2/admin', undefined, undefined);
    expect(row('bert').querySelector('[data-admin-badge]')).not.toBeNull();
    expect(buttonIn(row('bert'), 'Admin-Rechte entziehen')).toBeDefined();
  });

  it('revokes admin rights after confirming', async () => {
    api.delete.mockResolvedValue(user(1, 'anna'));
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('anna'), 'Admin-Rechte entziehen')!);

    expect(confirm.ask).toHaveBeenCalledWith('anna Admin rechte entnehmen?');
    expect(api.delete).toHaveBeenCalledWith('/users/1/admin', undefined);
    expect(row('anna').querySelector('[data-admin-badge]')).toBeNull();
  });

  it('does nothing when the admin-rights confirmation is cancelled', async () => {
    confirm.ask.mockResolvedValue(false);
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('bert'), 'Admin-Rechte geben')!);

    expect(api.put).not.toHaveBeenCalled();
  });

  it('deletes after a danger confirmation and reloads the list', async () => {
    api.delete.mockResolvedValue(undefined);
    const { el, row, buttonIn, click } = await render();
    api.get.mockResolvedValue([users[0]]);

    await click(buttonIn(row('bert'), 'Löschen')!);

    expect(confirm.ask).toHaveBeenCalledWith('bert definitiv löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/users/2', undefined);
    expect(notify.success).toHaveBeenCalledWith('users.delete_success');
    expect(el.querySelectorAll('[data-user]')).toHaveLength(1);
  });

  it('does not delete when cancelled', async () => {
    confirm.ask.mockResolvedValue(false);
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('bert'), 'Löschen')!);

    expect(api.delete).not.toHaveBeenCalled();
  });

  it('shows the username only when it differs from the display name', async () => {
    api.get.mockResolvedValue([user(1, 'anna'), user(2, 'Vor Name2')]);
    const { el } = await render();

    expect(Array.from(el.querySelectorAll('[data-username]')).map((u) => u.textContent?.trim())).toEqual(['anna']);
  });

  it('marks name conflicts; shows phone and priority only in the Amigos flavour', async () => {
    api.get.mockResolvedValue([user(1, 'anna', { nameConflict: true, phone: '+34 600', priority: 1000 }), user(2, 'bert')]);
    const elfaro = await render();
    expect(elfaro.row('anna').querySelector('[data-name-conflict]')).not.toBeNull();
    expect(elfaro.row('bert').querySelector('[data-name-conflict]')).toBeNull();
    expect(elfaro.el.querySelector('[data-phone]')).toBeNull();

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideTestFlavor(), { provide: ApiClient, useValue: api }, { provide: ConfirmService, useValue: confirm }, { provide: Notify, useValue: notify }] });
    setTestFlavor(amigosFlavor);
    const amigos = await render();
    expect(amigos.row('anna').querySelector('[data-phone]')?.getAttribute('href')).toBe('tel:+34 600');
    expect(amigos.row('anna').querySelector('[data-priority]')?.textContent).toContain('1000');
  });
});
