import { Component, inject } from '@angular/core';
import { TranslatePipe } from '../i18n/translate.pipe';
import { APP_VERSION } from '../version';

@Component({
  selector: 'app-footer',
  imports: [TranslatePipe],
  template: `
    <footer class="border-t border-border py-4 text-center text-xs text-muted-foreground">
      {{ 'app.name' | t }} · {{ version }}
    </footer>
  `,
})
export class Footer {
  protected readonly version = inject(APP_VERSION);
}
