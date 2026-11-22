import { LoadError } from '../../shared/ui/load-error';
import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmLabelImports } from '@ui/label';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { SocialEventDetailDto, TableDto, socialEventsApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { ConfirmService } from '../../core/feedback/confirm.service';
import { formatDate, formatDateTime } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { SELECT_CLASSES } from '../../shared/forms/select';
import { BackLink } from '../../shared/ui/back-link';
import { Badge } from '../../shared/ui/badge';
import { DetailList } from '../../shared/ui/detail-list';
import { Page, PageHeader } from '../../shared/ui/page';

@Component({
  selector: 'app-social-event-detail-page',
  imports: [LoadError, 
    FormField,
    RouterLink,
    HlmButtonImports,
    HlmCardImports,
    HlmLabelImports,
    HlmSpinner,
    TranslatePipe,
    BackLink,
    Badge,
    DetailList,
    Page,
    PageHeader,
  ],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div appPage>
        <app-back-link link="/" [queryParams]="{ month: d.event.date.slice(0, 7) }" />
        <app-page-header [title]="d.event.name" [subtitle]="formatDate(d.event.date)">
          <div meta class="flex flex-wrap gap-2 empty:hidden">
            @if (d.event.userRegistered) {
              <span appBadge>{{ 'social_events.registered' | t }}</span>
            }
          </div>
          @if (session.isAdmin()) {
            <a hlmBtn variant="outline" size="sm" [routerLink]="['/social-events', d.event.id, 'admin']">{{ 'social_events.edit' | t }}</a>
          }
        </app-page-header>

        @if (d.description || d.registrationClose) {
          <section hlmCard>
            <dl hlmCardContent appDetailList>
              @if (d.description) {
                <div>
                  <dt>{{ 'social_events.description' | t }}</dt>
                  <dd class="whitespace-pre-line">{{ d.description }}</dd>
                </div>
              }
              @if (d.registrationClose) {
                <div>
                  <dt>{{ 'social_events.registration_close' | t }}</dt>
                  <dd>{{ formatDateTime(d.registrationClose) }}</dd>
                </div>
              }
            </dl>
          </section>
        }

        <section hlmCard data-registration>
          @if (d.event.userRegistered && d.myRegistration) {
            <div hlmCardHeader><h2 hlmCardTitle>{{ 'events.your_registration' | t }}</h2></div>
          }
          <div hlmCardContent class="flex flex-col gap-3">
            @if (d.event.userRegistered && d.myRegistration; as mine) {
              <dl appDetailList>
                @if (mine.menuName) {
                  <div>
                    <dt>{{ 'social_events.menu' | t }}</dt>
                    <dd>{{ mine.menuName }}</dd>
                  </div>
                }
                <div>
                  <dt>{{ 'social_events.table' | t }}</dt>
                  <dd>{{ mine.tableNumber === null ? ('social_events.libero' | t) : tableLabel(mine.tableNumber) }}</dd>
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
              <p class="text-muted-foreground">{{ 'social_events.full' | t }}</p>
            } @else {
              <form class="flex flex-col gap-3 sm:flex-row sm:items-start" novalidate (submit)="$event.preventDefault(); register()">
                @if (d.menus.length > 0) {
                  <div class="flex flex-col gap-2 sm:flex-1">
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
                  <div class="flex flex-col gap-2 sm:flex-1">
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
                <button hlmBtn type="submit" class="w-full sm:mt-[1.375rem] sm:w-auto" [disabled]="busy()">
                  {{ 'social_events.register' | t }}
                </button>
              </form>
            }
          </div>
        </section>

        <section hlmCard data-participants>
          <div hlmCardHeader>
            <h2 hlmCardTitle>{{ 'social_events.participants' | t }} ({{ d.participants.length }})</h2>
          </div>
          <div hlmCardContent>
            @if (d.participants.length === 0) {
              <p class="text-muted-foreground">{{ 'events.no_participants' | t }}</p>
            } @else {
              <table class="-mx-2 w-[calc(100%+1rem)] text-left">
                <thead>
                  <tr class="border-b text-xs text-muted-foreground">
                    <th class="w-28 px-2 pb-1.5 font-medium sm:w-40">{{ 'social_events.table' | t }}</th>
                    <th class="px-2 pb-1.5 font-medium">{{ 'social_events.name' | t }}</th>
                  </tr>
                </thead>
                <tbody>
                  @for (p of d.participants; track $index) {
                    <tr class="border-b last:border-b-0 data-own:bg-primary/10 data-own:font-medium" [attr.data-own]="p.name === ownName() ? '' : null">
                      <td class="px-2 py-1.5 align-top" [class.text-muted-foreground]="p.tableNumber === null">
                        {{ p.tableNumber ?? ('social_events.libero' | t) }}
                      </td>
                      <td class="px-2 py-1.5 align-top">{{ p.name }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            }
          </div>
        </section>
      </div>
    } @else if (detail.error()) {
      <div appPage>
        <app-load-error [error]="detail.error()" notFoundKey="social_events.not_found" [showHome]="true" (retry)="detail.reload()" />
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

  /** The logged-in member's own row is highlighted in the participant list. */
  protected readonly ownName = computed(() => this.session.user()?.displayName ?? null);
  private readonly current = computed(() => (this.detail.hasValue() ? this.detail.value() : undefined));
  protected readonly busy = signal(false);
  private readonly serverErrors = signal<Record<string, string>>({});

  protected readonly model = signal({ menuId: '', tableId: '' });
  protected readonly registerForm = form(this.model, (path) => {
    required(path.menuId, { when: () => (this.current()?.menus.length ?? 0) > 0 });
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
