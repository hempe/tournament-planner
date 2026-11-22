import { Component, input, linkedSignal, output } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { HlmButtonImports } from '@ui/button';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiError } from '../../../core/api/api-client';
import { AdminUserDto, UserCreateRequest, UserUpdateRequest } from '../../../core/api/generated';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { FieldError } from '../../../shared/forms/field-error';
import { SELECT_CLASSES } from '../../../shared/forms/select';

interface FormModel {
  /** '' (not chosen), 'true' (Herr) or 'false' (Frau). */
  male: string;
  username: string;
  password: string;
  firstName: string;
  lastName: string;
  memberNumber: string;
  rfeg: string;
}

/** Presentational form shared by the user create and edit pages. Emits `save` with the request body. */
@Component({
  selector: 'app-user-form',
  imports: [FormField, HlmButtonImports, HlmInputImports, HlmLabelImports, TranslatePipe, FieldError],
  template: `
    <form class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); send()">
      <div class="flex flex-col gap-2">
        <label hlmLabel for="male">{{ 'users.salutation' | t }} *</label>
        <select id="male" [class]="selectClasses" [formField]="userForm.male">
          <option value=""></option>
          <option value="true">{{ 'users.mr' | t }}</option>
          <option value="false">{{ 'users.mrs' | t }}</option>
        </select>
        <app-field-error field="male" label="users.salutation" [error]="error()" [control]="userForm.male()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="username">{{ 'users.username' | t }} *</label>
        <input hlmInput id="username" autocomplete="off" [formField]="userForm.username" />
        <app-field-error field="username" label="users.username" [error]="error()" [control]="userForm.username()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="password">{{ (mode() === 'create' ? 'users.password' : 'users.new_password') | t }}@if (mode() === 'create') { * }</label>
        <input hlmInput id="password" type="password" autocomplete="new-password" [formField]="userForm.password" />
        <app-field-error
          field="password"
          [label]="mode() === 'create' ? 'users.password' : 'users.new_password'"
          [error]="error()"
          [control]="userForm.password()"
        />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label hlmLabel for="firstName">{{ 'users.first_name' | t }}</label>
          <input hlmInput id="firstName" [formField]="userForm.firstName" />
          <app-field-error field="firstName" label="users.first_name" [error]="error()" />
        </div>
        <div class="flex flex-col gap-2">
          <label hlmLabel for="lastName">{{ 'users.last_name' | t }}</label>
          <input hlmInput id="lastName" [formField]="userForm.lastName" />
          <app-field-error field="lastName" label="users.last_name" [error]="error()" />
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label hlmLabel for="memberNumber">{{ 'users.member_number' | t }}</label>
          <input hlmInput id="memberNumber" [formField]="userForm.memberNumber" />
          <app-field-error field="memberNumber" label="users.member_number" [error]="error()" />
        </div>
        <div class="flex flex-col gap-2">
          <label hlmLabel for="rfeg">{{ 'users.rfeg' | t }}</label>
          <input hlmInput id="rfeg" [formField]="userForm.rfeg" />
          <app-field-error field="rfeg" label="users.rfeg" [error]="error()" />
        </div>
      </div>
      <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
        {{ (mode() === 'create' ? 'users.create' : 'users.update') | t }}
      </button>
    </form>
  `,
})
export class UserForm {
  readonly mode = input<'create' | 'edit'>('create');
  readonly initial = input<AdminUserDto | null>(null);
  /** 422 from the last save. */
  readonly error = input<ApiError | null>(null);
  readonly busy = input(false);
  readonly save = output<UserCreateRequest | UserUpdateRequest>();

  protected readonly selectClasses = SELECT_CLASSES;

  protected readonly model = linkedSignal<FormModel>(() => {
    const u = this.initial();
    return {
      male: u ? String(u.male) : '',
      username: u?.username ?? '',
      password: '',
      firstName: u?.firstName ?? '',
      lastName: u?.lastName ?? '',
      memberNumber: u?.memberNumber ?? '',
      rfeg: u?.rfeg ?? '',
    };
  });

  protected readonly userForm = form(this.model, (path) => {
    required(path.male);
    required(path.username);
    required(path.password, { when: () => this.mode() === 'create' });
  });

  protected async send(): Promise<void> {
    await submit(this.userForm, async () => {
      const m = this.model();
      const common = {
        male: m.male === 'true',
        username: m.username,
        firstName: m.firstName || null,
        lastName: m.lastName || null,
        rfeg: m.rfeg || null,
        memberNumber: m.memberNumber || null,
        phone: null,
        handicap: null,
        priority: null,
      };
      this.save.emit(
        this.mode() === 'create' ? { ...common, password: m.password } : { ...common, password: m.password || null },
      );
      return undefined;
    });
  }
}
