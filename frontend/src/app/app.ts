import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { HlmToasterImports } from '@ui/sonner';
import { ConfirmDialog } from './core/feedback/confirm-dialog';
import { Footer } from './core/layout/footer';
import { Header } from './core/layout/header';
import { UpdateService } from './core/update/update.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Header, Footer, ConfirmDialog, HlmToasterImports],
  template: `
    <!--
      Phones: extra space at the bottom (as on the previous site, 150px) so mobile browser toolbars
      and the iOS home indicator never cover the last calendar entries.
    -->
    <div class="flex min-h-dvh flex-col pb-[calc(150px+env(safe-area-inset-bottom))] md:pb-0">
      <app-header />
      <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6">
        <router-outlet />
      </main>
      <app-footer />
    </div>
    <hlm-toaster />
    <app-confirm-dialog />
  `,
})
export class App {
  constructor() {
    inject(UpdateService).start();
  }
}
