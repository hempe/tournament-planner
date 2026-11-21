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
    <div class="flex min-h-dvh flex-col">
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
