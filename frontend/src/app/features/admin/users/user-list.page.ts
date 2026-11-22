import { LoadError } from '../../../shared/ui/load-error';
import { Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucidePencil, lucidePlus, lucideSearch, lucideShieldMinus, lucideShieldPlus, lucideTrash2 } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { HlmInputImports } from '@ui/input';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../../core/api/api-client';
import { AdminUserDto, userAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { matchesWords, queryWords } from '../../../shared/search';
import { Badge } from '../../../shared/ui/badge';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-user-list-page',
  imports: [LoadError, RouterLink, NgIcon, HlmButtonImports, HlmInputImports, HlmSpinner, TranslatePipe, Badge, Page, PageHeader],
  providers: [provideIcons({ lucidePencil, lucidePlus, lucideSearch, lucideShieldMinus, lucideShieldPlus, lucideTrash2 })],
  template: `
    <div appPage width="wide">
      <app-page-header [title]="'users.title' | t">
        <a hlmBtn size="sm" routerLink="/users/new"><ng-icon name="lucidePlus" />{{ 'users.add_user' | t }}</a>
      </app-page-header>

      @if (users.hasValue()) {
        <div class="relative sm:max-w-sm">
          <ng-icon name="lucideSearch" class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <input
            hlmInput
            class="pl-8"
            data-user-search
            autocomplete="off"
            [attr.aria-label]="'users.search' | t"
            [attr.placeholder]="'users.search' | t"
            [value]="query()"
            (input)="query.set($any($event.target).value)"
          />
        </div>
        @if (filtered().length === 0) {
          <p class="text-muted-foreground">{{ 'users.no_users_found' | t }}</p>
        } @else {
          <ul class="divide-y divide-border rounded-lg border border-border bg-background px-3 text-sm shadow-xs sm:px-4">
            @for (u of filtered(); track u.id) {
              <li data-user class="flex items-center gap-2 py-2">
                <div class="flex min-w-0 flex-1 flex-col pt-[0.3em] sm:flex-row sm:items-baseline sm:gap-3">
                  <span class="flex items-center gap-2">
                    <span class="truncate font-medium">{{ u.displayName }}</span>
                    @if (u.isAdmin) {
                      <span appBadge variant="outline" data-admin-badge>{{ 'users.admin' | t }}</span>
                    }
                  </span>
                  @if (u.username !== u.displayName) {
                    <span data-username class="truncate text-sm text-muted-foreground">{{ u.username }}</span>
                  }
                </div>
                <div class="flex shrink-0 gap-0.5">
                  <a hlmBtn variant="ghost" size="icon-sm" [routerLink]="['/users', u.id, 'edit']" [attr.aria-label]="'users.edit' | t" [attr.title]="'users.edit' | t">
                    <ng-icon name="lucidePencil" />
                  </a>
                  <button
                    hlmBtn
                    variant="ghost"
                    size="icon-sm"
                    type="button"
                    [attr.aria-label]="(u.isAdmin ? 'users.remove_admin_rights' : 'users.give_admin_rights') | t"
                    [attr.title]="(u.isAdmin ? 'users.remove_admin_rights' : 'users.give_admin_rights') | t"
                    (click)="toggleAdmin(u)"
                  >
                    <ng-icon [name]="u.isAdmin ? 'lucideShieldMinus' : 'lucideShieldPlus'" />
                  </button>
                  <button
                    hlmBtn
                    variant="ghost"
                    size="icon-sm"
                    type="button"
                    class="text-muted-foreground hover:text-destructive"
                    [attr.aria-label]="'users.delete' | t"
                    [attr.title]="'users.delete' | t"
                    (click)="remove(u)"
                  >
                    <ng-icon name="lucideTrash2" />
                  </button>
                </div>
              </li>
            }
          </ul>
        }
      } @else if (users.error()) {
        <app-load-error [error]="users.error()" (retry)="users.reload()" />
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

  /** Filters by display name and username (every word must match). */
  protected readonly query = signal('');
  protected readonly filtered = computed(() => {
    const list = this.users.hasValue() ? this.users.value() : [];
    const words = queryWords(this.query());
    return words.length === 0 ? list : list.filter((u) => matchesWords(`${u.displayName} ${u.username}`, words));
  });

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
