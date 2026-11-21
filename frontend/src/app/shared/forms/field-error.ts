import { Component, computed, inject, input } from '@angular/core';
import { ApiError } from '../../core/api/api-client';
import { I18n } from '../../core/i18n/i18n';
import { translateFieldError } from './field-errors';

/**
 * Error text under an input: "required" once the (signal form) control is touched and invalid,
 * otherwise the server's message for `field`.
 */
@Component({
  selector: 'app-field-error',
  template: `
    @if (message(); as text) {
      <p [id]="field() + '-error'" class="text-sm text-destructive">{{ text }}</p>
    }
  `,
})
export class FieldError {
  /** The 422 error of the last submit, if any. */
  readonly error = input<ApiError | null>(null);
  /** API field name (camelCase request property). */
  readonly field = input.required<string>();
  /** Translation key of the field label, used for `:field`; defaults to the field name. */
  readonly label = input<string>();
  /** `form.name()` of a signal form; enables the client-side required message. */
  readonly control = input<{ touched(): boolean; invalid(): boolean; errors(): readonly { kind: string; min?: number }[] } | null>(null);

  private readonly i18n = inject(I18n);

  protected readonly message = computed(() => {
    const label = this.label() ? this.i18n.t(this.label()!) : undefined;
    const control = this.control();
    if (control?.touched() && control.invalid()) {
      const field = label ?? this.field();
      const failed = control.errors().find((e) => e.kind === 'min');
      return failed ? this.i18n.t('validation.min_value', { field, min: failed.min ?? 0 }) : this.i18n.t('validation.required', { field });
    }
    return translateFieldError(this.error(), this.field(), this.i18n, label);
  });
}
