import { Injectable, signal } from '@angular/core';

export interface ConfirmRequest {
  message: string;
  danger: boolean;
}

/** `if (await confirm.ask(t('…'))) { … }` — rendered by <app-confirm-dialog> in the app shell. */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly _pending = signal<ConfirmRequest | null>(null);
  readonly pending = this._pending.asReadonly();
  private resolve: ((answer: boolean) => void) | null = null;

  ask(message: string, options: { danger?: boolean } = {}): Promise<boolean> {
    this.resolve?.(false);
    return new Promise<boolean>((resolve) => {
      this.resolve = resolve;
      this._pending.set({ message, danger: options.danger ?? false });
    });
  }

  answer(answer: boolean): void {
    const resolve = this.resolve;
    this.resolve = null;
    this._pending.set(null);
    resolve?.(answer);
  }
}
