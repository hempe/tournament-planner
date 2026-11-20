import { Injectable, InjectionToken, inject } from '@angular/core';
import { toast } from '@spartan-ng/brain/sonner';
import { I18n, Params } from '../i18n/i18n';

export interface Toaster {
  success(message: string): unknown;
  error(message: string): unknown;
  info(message: string): unknown;
}

export const TOASTER = new InjectionToken<Toaster>('TOASTER', { factory: () => toast });

/** Translated toasts, rendered by <hlm-toaster> in the app shell. */
@Injectable({ providedIn: 'root' })
export class Notify {
  private readonly i18n = inject(I18n);
  private readonly toaster = inject(TOASTER);

  success(key: string, params?: Params): void {
    this.toaster.success(this.i18n.t(key, params));
  }

  error(key: string, params?: Params): void {
    this.toaster.error(this.i18n.t(key, params));
  }

  info(key: string, params?: Params): void {
    this.toaster.info(this.i18n.t(key, params));
  }
}
