import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideLogIn, lucideLogOut } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { SessionStore } from '../auth/session.store';
import { FLAVOR } from '../flavor/flavor';
import { Notify } from '../feedback/notify';
import { TranslatePipe } from '../i18n/translate.pipe';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, NgIcon, HlmButtonImports, TranslatePipe],
  providers: [provideIcons({ lucideLogIn, lucideLogOut })],
  template: `
    <header class="border-b border-border bg-background">
      <!-- Green bar as on www.golfelfaro.es: account actions in white -->
      <div class="bg-primary text-primary-foreground">
        <div class="flex h-10 items-center justify-end gap-1 px-[10px] text-sm md:h-12">
          @if (session.loggedIn()) {
            <span data-user-name class="max-w-[14rem] truncate pt-[0.3em] opacity-90">{{ session.user()?.displayName }}</span>
            <span class="mx-2 h-4 w-px bg-white/40" aria-hidden="true"></span>
            <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-white/15" (click)="logout()">
              <ng-icon name="lucideLogOut" size="16" aria-hidden="true" /><span class="pt-[0.3em]">{{ 'nav.logout' | t }}</span>
            </button>
          } @else {
            <a routerLink="/login" class="flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-white/15">
              <ng-icon name="lucideLogIn" size="16" aria-hidden="true" /><span class="pt-[0.3em]">{{ 'auth.login' | t }}</span>
            </a>
          }
        </div>
      </div>
      <!-- Branding as on the previous site: logo 100px (75px below md), club name 21px/13px Hind Guntur -->
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 p-[10px]">
        <div class="flex min-w-0 gap-5">
          <!-- Logo from flavor.json; links to the club website when the flavour has one -->
          @if (flavor.website; as website) {
            <a [href]="website" class="shrink-0"><img [src]="flavor.logo" [alt]="flavor.name" class="size-[75px] md:size-[100px]" /></a>
          } @else {
            <a routerLink="/" class="shrink-0"><img [src]="flavor.logo" [alt]="flavor.name" class="size-[75px] md:size-[100px]" /></a>
          }
          <a
            routerLink="/"
            class="site-name min-w-0 basis-[300px] pt-[27px] text-[13px] leading-[1.1em] md:pt-[35px] md:text-[21px]"
            data-testid="site-name"
          >
            {{ 'app.club_name_line1' | t }}<br />{{ 'app.club_name_line2' | t }}
          </a>
        </div>
        @if (session.isAdmin()) {
          <!-- Admin navigation in the style of the website menu: active item green and underlined -->
          <nav class="flex w-full justify-center gap-5 text-[15px] md:ml-auto md:w-auto md:justify-end md:pr-2">
            <a
              routerLink="/"
              routerLinkActive="text-primary! border-primary!"
              [routerLinkActiveOptions]="{ exact: true }"
              class="border-b-2 border-transparent py-2 hover:text-primary"
              >{{ 'nav.home' | t }}</a
            >
            <!-- active for /events/* and /social-events/* (RouterLinkActive on a wrapper checks all links inside) -->
            <span routerLinkActive="[&>a:first-child]:text-primary! [&>a:first-child]:border-primary!" class="contents">
              <a routerLink="/events" class="border-b-2 border-transparent py-2 hover:text-primary">{{ 'nav.events' | t }}</a>
              <a routerLink="/social-events" hidden tabindex="-1" aria-hidden="true"></a>
            </span>
            <a routerLink="/users" routerLinkActive="text-primary! border-primary!" class="border-b-2 border-transparent py-2 hover:text-primary">{{ 'nav.users' | t }}</a>
          </nav>
        }
      </div>
    </header>
  `,
})
export class Header {
  protected readonly session = inject(SessionStore);
  protected readonly flavor = inject(FLAVOR);
  private readonly notify = inject(Notify);
  private readonly router = inject(Router);

  protected async logout(): Promise<void> {
    await this.session.logout();
    this.notify.success('auth.logout_success');
    await this.router.navigateByUrl('/');
  }
}
