import { Component, computed, inject } from '@angular/core';
import { HlmAlertDialogImports } from '@ui/alert-dialog';
import { TranslatePipe } from '../i18n/translate.pipe';
import { ConfirmService } from './confirm.service';

@Component({
  selector: 'app-confirm-dialog',
  imports: [HlmAlertDialogImports, TranslatePipe],
  template: `
    <hlm-alert-dialog [state]="state()" (closed)="confirm.answer(null)">
      <hlm-alert-dialog-content *hlmAlertDialogPortal>
        <hlm-alert-dialog-header>
          <h2 hlmAlertDialogTitle>{{ confirm.pending()?.title || ('calendar.confirmation' | t) }}</h2>
          <p hlmAlertDialogDescription>{{ confirm.pending()?.message }}</p>
        </hlm-alert-dialog-header>
        <hlm-alert-dialog-footer class="flex-col-reverse sm:flex-row">
          <button hlmAlertDialogCancel (click)="confirm.answer(null)">{{ 'actions.cancel' | t }}</button>
          @for (action of confirm.pending()?.actions; track action.value) {
            <button
              hlmAlertDialogAction
              [variant]="action.danger ? 'destructive' : 'default'"
              (click)="confirm.answer(action.value)"
            >
              {{ action.label || ('actions.confirm' | t) }}
            </button>
          }
        </hlm-alert-dialog-footer>
      </hlm-alert-dialog-content>
    </hlm-alert-dialog>
  `,
})
export class ConfirmDialog {
  protected readonly confirm = inject(ConfirmService);
  protected readonly state = computed(() => (this.confirm.pending() ? 'open' : 'closed'));
}
