import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { HlmCardImports } from '@ui/card';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { UserCreateRequest, UserUpdateRequest, userAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { UserForm } from './user-form';
import { BackLink } from '../../../shared/ui/back-link';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-user-create-page',
  imports: [UserForm, TranslatePipe, HlmCardImports, BackLink, Page, PageHeader],
  template: `
    <div appPage>
      <app-back-link link="/users" />
      <app-page-header [title]="'users.new' | t" />
      <section hlmCard>
        <div hlmCardContent>
          <app-user-form mode="create" [error]="error()" [busy]="busy()" (save)="create($event)" />
        </div>
      </section>
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
      const created = await userAdminApi(this.api).create(body as UserCreateRequest);
      this.notify.success('users.create_success');
      if (created.nameConflict) {
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
