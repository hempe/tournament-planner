import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { UserCreateRequest, UserUpdateRequest, userAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { UserForm } from './user-form';

@Component({
  selector: 'app-user-create-page',
  imports: [UserForm, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-2xl flex-col gap-4">
      <h1 class="text-2xl font-semibold">{{ 'users.new' | t }}</h1>
      <app-user-form mode="create" [error]="error()" [busy]="busy()" (save)="create($event)" />
    </div>
  `,
})
export class UserCreatePage {
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);

  protected readonly error = signal<ApiError | null>(null);
  protected readonly busy = signal(false);

  protected async create(body: UserCreateRequest | UserUpdateRequest): Promise<void> {
    this.error.set(null);
    this.busy.set(true);
    try {
      await userAdminApi(this.api).create(body as UserCreateRequest);
      this.notify.success('users.create_success');
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
