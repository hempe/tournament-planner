import { Component, inject } from '@angular/core';
import { SessionStore } from '../../core/auth/session.store';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

/** Placeholder start page; replaced by the event overview in plan 3. */
@Component({
  selector: 'app-home-page',
  imports: [TranslatePipe],
  template: `
    <h1 class="text-2xl font-semibold">{{ 'app.welcome' | t }}</h1>
    @if (session.user(); as user) {
      <p class="mt-2 text-muted-foreground">{{ user.displayName }}</p>
    }
  `,
})
export class HomePage {
  protected readonly session = inject(SessionStore);
}
