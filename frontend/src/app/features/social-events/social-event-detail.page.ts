import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmLabelImports } from '@ui/label';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { asApiError } from '../../core/api/api-error';
import { SocialEventDetailDto, TableDto, socialEventsApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { ConfirmService } from '../../core/feedback/confirm.service';
import { formatDate, formatDateTime } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { SELECT_CLASSES } from '../../shared/forms/select';

@Component({
  selector: 'app-social-event-detail-page',
  imports: [FormField, RouterLink, HlmButtonImports, HlmCardImports, HlmLabelImports, HlmSpinner, TranslatePipe],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div class="mx-auto flex max-w-2xl flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
          <a hlmBtn variant="ghost" size="sm" routerLink="/" [queryParams]="{ month: d.event.date.slice(0, 7) }">
            {{ 'nav.back' | t }}
          </a>
          @if (session.isAdmin()) {
            <a hlmBtn variant="outline" size="sm" [routerLink]="['/social-events', d.event.id, 'admin']">
              {{ 'social_events.edit' | t }}
            </a>
          }
        </div>

        <header>
          <p class="text-sm text-muted-foreground">{{ formatDate(d.event.date) }}</p>
          <h1 class="text-2xl font-semibold">{{ d.event.name }}</h1>
        </header>

        @if (d.description || d.registrationClose) {
          <section hlmCard>
            <dl hlmCardContent class="flex flex-col gap-3">
              @if (d.description) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'social_events.description' | t }}</dt>
                  <dd class="whitespace-pre-line">{{ d.description }}</dd>
                </div>
              }
              @if (d.registrationClose) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'social_events.registration_close' | t }}</dt>
                  <dd>{{ formatDateTime(d.registrationClose) }}</dd>
                </div>
              }
            </dl>
          </section>
        }

        <section hlmCard>
          <div hlmCardContent class="flex flex-col gap-3">
            @if (d.event.userRegistered && d.myRegistration; as mine) {
              <p class="font-medium">{{ 'social_events.registered' | t }}</p>
              <dl class="flex flex-col gap-1">
                @if (mine.menuName) {
                  <div>
                    <dt class="inline text-muted-foreground">{{ 'social_events.menu' | t }}:</dt>
                    <dd class="inline">{{ mine.menuName }}</dd>
                  </div>
                }
                <div>
                  <dt class="inline text-muted-foreground">{{ 'social_events.table' | t }}:</dt>
                  <dd class="inline">{{ mine.tableNumber === null ? ('social_events.libero' | t) : tableLabel(mine.tableNumber) }}</dd>
                </div>
              </dl>
              @if (!d.event.isLocked) {
                <button hlmBtn variant="destructive" type="button" class="w-full sm:w-auto sm:self-start" [disabled]="busy()" (click)="unregister()">
                  {{ 'social_events.unregister' | t }}
                </button>
              }
            } @else if (d.event.isLocked) {
              <p class="text-sm text-muted-foreground">{{ 'social_events.locked_message' | t }}</p>
            } @else if (d.event.available === 0) {
              <p class="font-medium">{{ 'social_events.full' | t }}</p>
            } @else {
              <form class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); register()">
                @if (d.menus.length > 0) {
                  <div class="flex flex-col gap-2">
                    <label hlmLabel for="menu">{{ 'social_events.menu' | t }} *</label>
                    <select id="menu" [class]="selectClasses" [formField]="registerForm.menuId">
                      <option value="">{{ 'social_events.select_menu' | t }}</option>
                      @for (m of d.menus; track m.id) {
                        <option [value]="m.id">{{ m.name }}</option>
                      }
                    </select>
                    @if (menuError(); as message) {
                      <p id="menu-error" class="text-sm text-destructive">{{ message }}</p>
                    }
                  </div>
                }
                @if (d.tables.length > 0) {
                  <div class="flex flex-col gap-2">
                    <label hlmLabel for="table">{{ 'social_events.table' | t }}</label>
                    <select id="table" [class]="selectClasses" [formField]="registerForm.tableId">
                      <option value="">{{ 'social_events.libero' | t }}</option>
                      @for (tbl of d.tables; track tbl.id) {
                        <option [value]="tbl.id" [disabled]="tbl.available === 0">{{ tableOption(tbl) }}</option>
                      }
                    </select>
                    @if (tableError(); as message) {
                      <p id="table-error" class="text-sm text-destructive">{{ message }}</p>
                    }
                  </div>
                }
                <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
                  {{ 'social_events.register' | t }}
                </button>
              </form>
            }
          </div>
        </section>

        <section hlmCard>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'social_events.participants' | t }}</h2></div>
          <div hlmCardContent>
            <table class="w-full text-left">
              <thead>
                <tr class="text-sm text-muted-foreground">
                  <th class="w-24 font-normal">{{ 'social_events.table' | t }}</th>
                  <th class="font-normal">{{ 'social_events.name' | t }}</th>
                </tr>
              </thead>
              <tbody>
                @for (p of d.participants; track $index) {
                  <tr>
                    <td>{{ p.tableNumber ?? '' }}</td>
                    <td>{{ p.name }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        </section>
      </div>
    } @else if (detail.error()) {
      <div class="mx-auto max-w-2xl">
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn class="mt-4" routerLink="/">{{ 'navigation.home' | t }}</a>
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class SocialEventDetailPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  protected readonly session = inject(SessionStore);
  private readonly api = inject(ApiClient);
  private readonly confirm = inject(ConfirmService);
  private readonly i18n = inject(I18n);
  protected readonly selectClasses = SELECT_CLASSES;

  protected readonly detail = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => socialEventsApi(this.api).show(params, { signal: abortSignal }),
  });

  private readonly current = computed(() => (this.detail.hasValue() ? this.detail.value() : undefined));
  protected readonly busy = signal(false);
  private readonly serverErrors = signal<Record<string, string>>({});

  protected readonly model = signal({ menuId: '', tableId: '' });
  protected readonly registerForm = form(this.model, (path) => {
    required(path.menuId, { when: () => (this.current()?.menus.length ?? 0) > 0 });
  });

  protected readonly errorKey = computed(() => {
    const error = asApiError(this.detail.error());
    return error?.status === 404 ? 'social_events.not_found' : (error?.error ?? 'errors.general');
  });

  protected readonly menuError = computed(() => {
    const field = this.registerForm.menuId();
    if (field.touched() && field.invalid()) {
      return this.i18n.t('validation.required', { field: this.i18n.t('social_events.menu') });
    }
    return this.serverErrors()['menuId'] ?? null;
  });
  protected readonly tableError = computed(() => this.serverErrors()['tableId'] ?? null);

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected formatDateTime(value: string): string {
    return formatDateTime(value, this.i18n.locale());
  }

  protected tableLabel(number: number): string {
    return this.i18n.t('social_events.table_number', { number });
  }

  protected tableOption(table: TableDto): string {
    return this.tableLabel(table.number);
  }

  protected async register(): Promise<void> {
    this.serverErrors.set({});
    await submit(this.registerForm, async () => {
      if (!(await this.confirm.ask(this.i18n.t('social_events.register_confirm')))) {
        return undefined;
      }
      const { menuId, tableId } = this.model();
      this.busy.set(true);
      try {
        const fresh = await socialEventsApi(this.api).register(this.id(), {
          menuId: menuId === '' ? null : Number(menuId),
          tableId: tableId === '' ? null : Number(tableId),
        });
        this.detail.value.set(fresh);
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          this.serverErrors.set(
            Object.fromEntries(Object.entries(error.fields).map(([name, f]) => [name, this.i18n.t(f.key, f.params)])),
          );
          return undefined;
        }
        throw error;
      } finally {
        this.busy.set(false);
      }
      return undefined;
    });
  }

  protected async unregister(): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('social_events.unregister_confirm'), { danger: true }))) {
      return;
    }
    this.busy.set(true);
    try {
      const fresh: SocialEventDetailDto = await socialEventsApi(this.api).unregister(this.id());
      this.detail.value.set(fresh);
    } finally {
      this.busy.set(false);
    }
  }
}
