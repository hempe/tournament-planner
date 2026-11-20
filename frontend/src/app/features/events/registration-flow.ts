import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient } from '../../core/api/api-client';
import { EventDetailDto, SocialEventSummaryDto, eventsApi, socialEventsApi } from '../../core/api/generated';
import { ConfirmService } from '../../core/feedback/confirm.service';
import { Notify } from '../../core/feedback/notify';
import { I18n } from '../../core/i18n/i18n';

/**
 * Register / unregister with confirmations, toasts and the dinner prompt.
 * Every method resolves with the fresh detail, or null when the user cancelled.
 * API errors propagate (the global handler shows them); callers use try/finally for busy state.
 */
@Injectable({ providedIn: 'root' })
export class RegistrationFlow {
  private readonly api = inject(ApiClient);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);
  private readonly router = inject(Router);

  async register(
    eventId: number,
    comment: string,
    event?: { available: number; isLocked: boolean },
  ): Promise<EventDetailDto | null> {
    const full = event !== undefined && event.available <= 0;
    if (!(await this.confirm.ask(this.i18n.t(full ? 'events.join_waitlist' : 'events.register_confirm')))) {
      return null;
    }
    const detail = await eventsApi(this.api).register(eventId, { comment });
    this.notify.success('events.registration_success');
    await this.offerDinner(detail);
    return detail;
  }

  async unregister(eventId: number, social: SocialEventSummaryDto | null): Promise<EventDetailDto | null> {
    if (social?.userRegistered) {
      const choice = await this.confirm.choose({
        title: this.i18n.t('events.unregister_choice_title'),
        message: this.i18n.t('events.unregister_choice_message'),
        actions: [
          { value: 'tournament', label: this.i18n.t('events.unregister_tournament_only'), danger: true },
          { value: 'both', label: this.i18n.t('events.unregister_both'), danger: true },
        ],
      });
      if (choice === null) {
        return null;
      }
      if (choice === 'both') {
        await socialEventsApi(this.api).unregister(social.id);
      }
    } else if (!(await this.confirm.ask(this.i18n.t('events.unregister_confirm'), { danger: true }))) {
      return null;
    }
    const detail = await eventsApi(this.api).unregister(eventId);
    this.notify.success('events.unregistration_success');
    return detail;
  }

  async updateComment(eventId: number, comment: string): Promise<EventDetailDto | null> {
    if (!(await this.confirm.ask(this.i18n.t('events.comment_update_confirm')))) {
      return null;
    }
    const detail = await eventsApi(this.api).updateComment(eventId, { comment });
    this.notify.success('events.comment_update_success');
    return detail;
  }

  private async offerDinner(detail: EventDetailDto): Promise<void> {
    const social = detail.socialEvent;
    if (!social || social.userRegistered || social.isLocked || (social.available !== null && social.available <= 0)) {
      return;
    }
    const answer = await this.confirm.choose({
      message: this.i18n.t('events.social_prompt_message', { name: social.name }),
      actions: [{ value: 'yes', label: this.i18n.t('events.social_prompt_yes') }],
      cancelLabel: this.i18n.t('events.social_prompt_no'),
    });
    if (answer === 'yes') {
      await this.router.navigate(['/social-events', social.id]);
    }
  }
}
