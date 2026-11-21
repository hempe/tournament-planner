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
      <!-- Branding as on the previous site: logo 100px (75px below md), club name 21px/13px Hind Guntur -->
      <div class="flex flex-wrap items-start gap-x-4 gap-y-2 p-[10px]">
        <div class="flex min-w-0 gap-5">
          <a href="https://www.golfelfaro.es" class="shrink-0">
            <img src="/favicon.svg" alt="EL FARO GOLF" class="size-[75px] md:size-[100px]" />
          </a>
          <a
            routerLink="/"
            class="site-name min-w-0 basis-[300px] pt-[27px] text-[13px] leading-[1.1em] md:pt-[35px] md:text-[21px]"
            data-testid="site-name"
          >
            {{ 'app.club_name_line1' | t }}<br />{{ 'app.club_name_line2' | t }}
          </a>
        </div>
        <!-- Phones: no room next to logo + name, so the nav gets its own centred row (as on the previous site) -->
        <nav class="flex w-full flex-wrap items-center justify-center gap-x-4 gap-y-2 md:ml-auto md:w-auto md:justify-end md:pt-1">
          @if (session.loggedIn()) {
            @if (session.isAdmin()) {
              <a routerLink="/events" routerLinkActive="text-primary">{{ 'nav.events' | t }}</a>
              <a routerLink="/users" routerLinkActive="text-primary">{{ 'nav.users' | t }}</a>
            }
            <span class="text-sm text-muted-foreground">{{ session.user()?.displayName }}</span>
            <button hlmBtn variant="ghost" size="sm" type="button" (click)="logout()">{{ 'nav.logout' | t }}</button>
          } @else {
            <a hlmBtn size="sm" routerLink="/login">{{ 'auth.login' | t }}</a>
          }
        </nav>
      </div>
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
