import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { SessionStore } from '../auth/session.store';
import { Notify } from '../feedback/notify';
import { TranslatePipe } from '../i18n/translate.pipe';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, HlmButtonImports, TranslatePipe],
  template: `
    <header class="border-b border-border bg-background">
      <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
        <a routerLink="/" class="font-semibold tracking-wide">{{ 'app.name' | t }}</a>
        @if (session.loggedIn()) {
          @if (session.isAdmin()) {
            <a routerLink="/events" routerLinkActive="text-primary">{{ 'nav.events' | t }}</a>
            <a routerLink="/users" routerLinkActive="text-primary">{{ 'nav.users' | t }}</a>
          }
          <span class="ml-auto text-sm text-muted-foreground">{{ session.user()?.displayName }}</span>
          <button hlmBtn variant="ghost" size="sm" type="button" (click)="logout()">{{ 'nav.logout' | t }}</button>
        } @else {
          <a hlmBtn size="sm" class="ml-auto" routerLink="/login">{{ 'auth.login' | t }}</a>
        }
      </nav>
    </header>
  `,
})
export class Header {
  protected readonly session = inject(SessionStore);
  private readonly notify = inject(Notify);
  private readonly router = inject(Router);

  protected async logout(): Promise<void> {
    await this.session.logout();
    this.notify.success('auth.logout_success');
    await this.router.navigateByUrl('/');
  }
}
