import { Component, computed, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { AdminUserDto, userAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';

@Component({
  selector: 'app-user-list-page',
  imports: [RouterLink, HlmButtonImports, HlmSpinner, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-3xl flex-col gap-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold">{{ 'users.title' | t }}</h1>
        <a hlmBtn routerLink="/users/new" class="w-full sm:w-auto">{{ 'users.add_user' | t }}</a>
      </div>

      @if (users.hasValue()) {
        <ul class="flex flex-col gap-2">
          @for (u of users.value(); track u.id) {
            <li data-user class="flex flex-col gap-2 rounded-md border bg-card p-3 sm:flex-row sm:items-center">
              <div class="sm:flex-1">
                <span data-username class="font-medium">{{ u.username }}</span>
                @if (u.isAdmin) {
                  <span data-admin-badge class="ml-2 rounded-full border px-2 py-0.5 text-xs text-muted-foreground">{{ 'users.admin' | t }}</span>
                }
                <span class="block text-sm text-muted-foreground">{{ u.displayName }}</span>
              </div>
              <div class="flex flex-col gap-2 sm:flex-row">
                <a hlmBtn size="sm" variant="outline" class="w-full sm:w-auto" [routerLink]="['/users', u.id, 'edit']">{{ 'users.edit' | t }}</a>
                <button hlmBtn size="sm" variant="outline" type="button" class="w-full sm:w-auto" (click)="toggleAdmin(u)">
                  {{ (u.isAdmin ? 'users.remove_admin_rights' : 'users.give_admin_rights') | t }}
                </button>
                <button hlmBtn size="sm" variant="destructive" type="button" class="w-full sm:w-auto" (click)="remove(u)">
                  {{ 'users.delete' | t }}
                </button>
              </div>
            </li>
          }
        </ul>
      } @else if (users.error()) {
        <p role="alert" class="text-destructive">{{ errorKey() | t }}</p>
      } @else {
        <div class="flex justify-center p-8"><hlm-spinner /></div>
      }
    </div>
  `,
})
export class UserListPage {
  private readonly api = inject(ApiClient);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);

  protected readonly users = resource({
    loader: ({ abortSignal }) => userAdminApi(this.api).index({ signal: abortSignal }),
  });
  protected readonly errorKey = computed(() => asApiError(this.users.error())?.error ?? 'errors.general');

  protected async toggleAdmin(user: AdminUserDto): Promise<void> {
    const key = user.isAdmin ? 'users.remove_admin_rights_confirm' : 'users.give_admin_rights_confirm';
    if (!(await this.confirm.ask(this.i18n.t(key, { username: user.username })))) {
      return;
    }
    const api = userAdminApi(this.api);
    const updated = await (user.isAdmin ? api.revokeAdmin(user.id) : api.grantAdmin(user.id));
    this.users.update((list) => list?.map((u) => (u.id === updated.id ? updated : u)));
  }

  protected async remove(user: AdminUserDto): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('users.delete_confirm', { username: user.username }), { danger: true }))) {
      return;
    }
    await userAdminApi(this.api).delete(user.id);
    this.notify.success('users.delete_success');
    this.users.reload();
  }
}
