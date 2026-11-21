import { Component, inject, input, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { Router } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiError } from '../../core/api/api-client';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { Page } from '../../shared/ui/page';

/** Only same-site paths are allowed as redirect targets (no open redirects). */
export function safeReturnUrl(url: string | undefined): string {
  return url && url.startsWith('/') && !url.startsWith('//') ? url : '/';
}

@Component({
  selector: 'app-login-page',
  imports: [FormField, HlmButtonImports, HlmCardImports, HlmInputImports, HlmLabelImports, TranslatePipe, Page],
  template: `
    <div appPage width="narrow" class="sm:mt-8">
      <section hlmCard>
        <div hlmCardHeader>
          <h1 hlmCardTitle>{{ 'auth.login' | t }}</h1>
        </div>
        <form hlmCardContent class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); login()">
          <div class="flex flex-col gap-2">
            <label hlmLabel for="username">{{ 'auth.username' | t }}</label>
            <input hlmInput id="username" autocomplete="username" [formField]="loginForm.username" />
            @if (loginForm.username().touched() && loginForm.username().invalid()) {
              <p class="text-sm text-destructive">{{ requiredMessage('auth.username') }}</p>
            }
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="password">{{ 'auth.password' | t }}</label>
            <input hlmInput id="password" type="password" autocomplete="current-password" [formField]="loginForm.password" />
            @if (loginForm.password().touched() && loginForm.password().invalid()) {
              <p class="text-sm text-destructive">{{ requiredMessage('auth.password') }}</p>
            }
          </div>
          @if (error(); as key) {
            <p role="alert" class="text-sm text-destructive">{{ key | t }}</p>
          }
          <button hlmBtn type="submit" [disabled]="busy()">{{ 'auth.login' | t }}</button>
        </form>
      </section>
    </div>
  `,
})
export class LoginPage {
  /** From the ?returnUrl= query parameter (withComponentInputBinding). */
  readonly returnUrl = input<string>();

  private readonly session = inject(SessionStore);
  private readonly router = inject(Router);
  private readonly i18n = inject(I18n);

  protected readonly model = signal({ username: '', password: '' });
  protected readonly loginForm = form(this.model, (path) => {
    required(path.username);
    required(path.password);
  });
  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal(false);

  protected requiredMessage(labelKey: string): string {
    return this.i18n.t('validation.required', { field: this.i18n.t(labelKey) });
  }

  protected async login(): Promise<void> {
    this.error.set(null);
    await submit(this.loginForm, async () => {
      this.busy.set(true);
      try {
        const { username, password } = this.model();
        await this.session.login(username, password);
        await this.router.navigateByUrl(safeReturnUrl(this.returnUrl()));
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          this.error.set(error.error);
          return undefined;
        }
        throw error;
      } finally {
        this.busy.set(false);
      }
      return undefined;
    });
  }
}
