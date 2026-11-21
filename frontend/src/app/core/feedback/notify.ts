import { Injectable, InjectionToken, inject } from '@angular/core';
import { toast } from '@spartan-ng/brain/sonner';
import { I18n, Params } from '../i18n/i18n';

export interface Toaster {
  success(message: string): unknown;
  error(message: string): unknown;
  info(message: string): unknown;
  /** Stays until dismissed; the action button runs `action.onClick`. */
  persistent(message: string, action: ToastAction): unknown;
}

export interface ToastAction {
  label: string;
  onClick: () => unknown;
}

export const TOASTER = new InjectionToken<Toaster>('TOASTER', {
  factory: () => ({
    success: (message) => toast.success(message),
    error: (message) => toast.error(message),
    info: (message) => toast.info(message),
    persistent: (message, action) =>
      toast(message, { duration: Infinity, action: { label: action.label, onClick: () => void action.onClick() } }),
  }),
});

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

  /** Toast that stays until dismissed, with a translated action button. */
  persistent(key: string, actionKey: string, onAction: () => unknown): void {
    this.toaster.persistent(this.i18n.t(key), { label: this.i18n.t(actionKey), onClick: onAction });
  }
}
