import { LoadError } from '../../../shared/ui/load-error';
import { Component, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { HlmCardImports } from '@ui/card';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { UserCreateRequest, UserUpdateRequest, userAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { UserForm } from './user-form';
import { BackLink } from '../../../shared/ui/back-link';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-user-edit-page',
  imports: [LoadError, RouterLink, HlmButtonImports, HlmSpinner, UserForm, TranslatePipe, HlmCardImports, BackLink, Page, PageHeader],
  template: `
    <div appPage>
      <app-back-link link="/users" />
      @if (user.hasValue()) {
        <app-page-header [title]="user.value().displayName" [subtitle]="'users.edit' | t" />
        <section hlmCard>
          <div hlmCardContent>
            <app-user-form mode="edit" [initial]="user.value()" [error]="error()" [busy]="busy()" (save)="update($event)" />
          </div>
        </section>
      } @else if (user.error()) {
        <app-load-error [error]="user.error()" notFoundKey="users.not_found" (retry)="user.reload()" />
        <a hlmBtn variant="outline" class="self-start" routerLink="/users">{{ 'nav.users' | t }}</a>
      } @else {
        <div class="flex justify-center p-8"><hlm-spinner /></div>
      }
    </div>
  `,
})
export class UserEditPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);

  protected readonly user = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => userAdminApi(this.api).show(params, { signal: abortSignal }),
  });

  protected readonly error = signal<ApiError | null>(null);
  protected readonly busy = signal(false);

  protected async update(body: UserCreateRequest | UserUpdateRequest): Promise<void> {
    this.error.set(null);
    this.busy.set(true);
    try {
      const updated = await userAdminApi(this.api).update(this.id(), body as UserUpdateRequest);
      this.notify.success('users.update_success');
      if (updated.nameConflict) {
        this.notify.info('users.name_conflict');
      }
      await this.router.navigateByUrl('/users');
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        this.error.set(e);
        return;
      }
      throw e;
    } finally {
      this.busy.set(false);
    }
  }
}
