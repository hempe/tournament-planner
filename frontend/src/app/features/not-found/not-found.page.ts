import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { Page, PageHeader } from '../../shared/ui/page';

@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink, HlmButtonImports, TranslatePipe, Page, PageHeader],
  template: `
    <div appPage>
      <app-page-header [title]="'errors.404_title' | t" />
      <p class="text-muted-foreground">{{ 'errors.not_found' | t }}</p>
      <a hlmBtn class="self-start" routerLink="/">{{ 'navigation.home' | t }}</a>
    </div>
  `,
})
export class NotFoundPage {}
