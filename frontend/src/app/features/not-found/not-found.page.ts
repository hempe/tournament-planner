import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink, HlmButtonImports, TranslatePipe],
  template: `
    <h1 class="text-2xl font-semibold">{{ 'errors.404_title' | t }}</h1>
    <p class="mt-2 text-muted-foreground">{{ 'errors.not_found' | t }}</p>
    <a hlmBtn class="mt-6" routerLink="/">{{ 'navigation.home' | t }}</a>
  `,
})
export class NotFoundPage {}
