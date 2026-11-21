import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { UserCreateRequest, UserUpdateRequest, userAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { UserForm } from './user-form';

@Component({
  selector: 'app-user-edit-page',
  imports: [RouterLink, HlmButtonImports, HlmSpinner, UserForm, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-2xl flex-col gap-4">
      @if (user.hasValue()) {
        <h1 class="text-2xl font-semibold">{{ 'users.edit' | t }}</h1>
        <app-user-form mode="edit" [initial]="user.value()" [error]="error()" [busy]="busy()" (save)="update($event)" />
      } @else if (user.error()) {
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn routerLink="/users">{{ 'nav.users' | t }}</a>
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
  protected readonly errorKey = computed(() => {
    const error = asApiError(this.user.error());
    return error?.status === 404 ? 'users.not_found' : (error?.error ?? 'errors.general');
  });

  protected async update(body: UserCreateRequest | UserUpdateRequest): Promise<void> {
    this.error.set(null);
    this.busy.set(true);
    try {
      await userAdminApi(this.api).update(this.id(), body as UserUpdateRequest);
      this.notify.success('users.update_success');
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
