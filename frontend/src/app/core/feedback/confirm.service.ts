import { Injectable, signal } from '@angular/core';

export interface ConfirmAction {
  value: string;
  label: string;
  danger?: boolean;
}

export interface ConfirmRequest {
  /** Already translated. Defaults to the "Bestätigung" title in the dialog. */
  title?: string;
  message: string;
  actions: ConfirmAction[];
}

/**
 * `if (await confirm.ask(i18n.t('…'))) { … }` for yes/no,
 * `await confirm.choose({ message, actions })` for several options (null = cancelled).
 * Rendered by <app-confirm-dialog> in the app shell.
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly _pending = signal<ConfirmRequest | null>(null);
  readonly pending = this._pending.asReadonly();
  private resolve: ((value: string | null) => void) | null = null;

  /** Yes/no question; the confirm button uses the "actions.confirm" label. */
  async ask(message: string, options: { danger?: boolean; confirmLabel?: string } = {}): Promise<boolean> {
    const answer = await this.choose({
      message,
      actions: [{ value: 'confirm', label: options.confirmLabel ?? '', danger: options.danger }],
    });
    return answer === 'confirm';
  }

  choose(request: ConfirmRequest): Promise<string | null> {
    this.resolve?.(null);
    return new Promise<string | null>((resolve) => {
      this.resolve = resolve;
      this._pending.set(request);
    });
  }

  /** `true`/`false` keep working for ask(); `null` cancels. */
  answer(value: string | boolean | null): void {
    const resolve = this.resolve;
    this.resolve = null;
    this._pending.set(null);
    resolve?.(value === true ? 'confirm' : value === false ? null : value);
  }
}
