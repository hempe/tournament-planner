import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { ApiClient } from '../../core/api/api-client';
import { EventDetailDto, SocialEventSummaryDto } from '../../core/api/generated';
import { ConfirmService } from '../../core/feedback/confirm.service';
import { Notify } from '../../core/feedback/notify';
import { I18n } from '../../core/i18n/i18n';
import { RegistrationFlow } from './registration-flow';

function social(over: Partial<SocialEventSummaryDto> = {}): SocialEventSummaryDto {
  return {
    id: 9, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false,
    totalCapacity: 10, registered: 2, available: 8, userRegistered: false, ...over,
  };
}

function detail(over: Partial<EventDetailDto> = {}, eventOver: Partial<EventDetailDto['event']> = {}): EventDetailDto {
  return {
    event: {
      id: 3, name: 'Cup', date: '2099-06-01', capacity: 10, joined: 1, waitlist: 0,
      available: 5, isLocked: false, manuallyLocked: false, userState: 'registered', ...eventOver,
    },
    description: null, priceMembers: null, priceGuests: null, registrationClose: null,
    myRegistration: { state: 'registered', comment: '' }, participants: [], guests: [], socialEvent: null,
    ...over,
  };
}

describe('RegistrationFlow', () => {
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn>; patch: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };
  let confirm: { ask: ReturnType<typeof vi.fn>; choose: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.fn>;
  let flow: RegistrationFlow;

  beforeEach(() => {
    api = { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() };
    confirm = { ask: vi.fn().mockResolvedValue(true), choose: vi.fn().mockResolvedValue(null) };
    notify = { success: vi.fn() };
    navigate = vi.fn().mockResolvedValue(true);
    TestBed.configureTestingModule({
      providers: [
        { provide: ApiClient, useValue: api },
        { provide: ConfirmService, useValue: confirm },
        { provide: Notify, useValue: notify },
        { provide: Router, useValue: { navigate } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      events: {
        join_waitlist: 'WAITLIST?',
        register_confirm: 'REGISTER?',
        unregister_confirm: 'UNREGISTER?',
        comment_update_confirm: 'COMMENT?',
        social_prompt_message: 'Auch :name?',
        social_prompt_yes: 'Ja',
        social_prompt_no: 'Nein',
        unregister_choice_title: 'Abmeldung',
        unregister_choice_message: 'Von welchem?',
        unregister_tournament_only: 'Nur Turnier',
        unregister_both: 'Beide',
      },
    });
    flow = TestBed.inject(RegistrationFlow);
  });

  describe('register', () => {
    it('asks the waitlist question for full events and the normal one otherwise', async () => {
      api.post.mockResolvedValue(detail());

      await flow.register(3, 'hi', { available: 0, isLocked: false });
      expect(confirm.ask).toHaveBeenLastCalledWith('WAITLIST?');

      await flow.register(3, 'hi', { available: 2, isLocked: false });
      expect(confirm.ask).toHaveBeenLastCalledWith('REGISTER?');
    });

    it('does nothing when the confirmation is cancelled', async () => {
      confirm.ask.mockResolvedValue(false);

      await expect(flow.register(3, 'hi')).resolves.toBeNull();

      expect(api.post).not.toHaveBeenCalled();
    });

    it('registers with the comment, toasts and returns the fresh detail', async () => {
      const fresh = detail();
      api.post.mockResolvedValue(fresh);

      await expect(flow.register(3, 'hi')).resolves.toBe(fresh);

      expect(api.post).toHaveBeenCalledWith('/events/3/registrations', { comment: 'hi' }, undefined);
      expect(notify.success).toHaveBeenCalledWith('events.registration_success');
      expect(confirm.choose).not.toHaveBeenCalled();
    });

    it.each([
      ['no social event', detail()],
      ['already registered for the dinner', detail({ socialEvent: social({ userRegistered: true }) })],
      ['dinner locked', detail({ socialEvent: social({ isLocked: true }) })],
      ['dinner full', detail({ socialEvent: social({ available: 0 }) })],
    ])('offers no dinner prompt: %s', async (_name, fresh) => {
      api.post.mockResolvedValue(fresh);

      await flow.register(3, '');

      expect(confirm.choose).not.toHaveBeenCalled();
    });

    it('offers the dinner and navigates on yes', async () => {
      api.post.mockResolvedValue(detail({ socialEvent: social() }));
      confirm.choose.mockResolvedValue('yes');

      await flow.register(3, '');

      expect(confirm.choose).toHaveBeenCalledWith({
        message: 'Auch Dinner?',
        actions: [{ value: 'yes', label: 'Ja' }],
        cancelLabel: 'Nein',
      });
      expect(navigate).toHaveBeenCalledWith(['/social-events', 9]);
    });

    it('stays put when the dinner is declined; unlimited dinners are offered', async () => {
      api.post.mockResolvedValue(detail({ socialEvent: social({ available: null }) }));

      await flow.register(3, '');

      expect(confirm.choose).toHaveBeenCalled();
      expect(navigate).not.toHaveBeenCalled();
    });
  });

  describe('unregister', () => {
    it('uses a plain confirmation without a dinner registration', async () => {
      api.delete.mockResolvedValue(detail());

      await flow.unregister(3, social({ userRegistered: false }));

      expect(confirm.ask).toHaveBeenCalledWith('UNREGISTER?', { danger: true });
      expect(confirm.choose).not.toHaveBeenCalled();
      expect(api.delete).toHaveBeenCalledTimes(1);
      expect(api.delete).toHaveBeenCalledWith('/events/3/registrations/me', undefined);
      expect(notify.success).toHaveBeenCalledWith('events.unregistration_success');
    });

    it('both: unregisters the dinner first, then the tournament', async () => {
      confirm.choose.mockResolvedValue('both');
      api.delete.mockResolvedValue(detail());

      await flow.unregister(3, social({ userRegistered: true }));

      expect(api.delete.mock.calls.map((c) => c[0])).toEqual([
        '/social-events/9/registrations/me',
        '/events/3/registrations/me',
      ]);
      expect(confirm.choose).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Abmeldung',
          message: 'Von welchem?',
          actions: [
            { value: 'tournament', label: 'Nur Turnier', danger: true },
            { value: 'both', label: 'Beide', danger: true },
          ],
        }),
      );
    });

    it('tournament only: leaves the dinner alone', async () => {
      confirm.choose.mockResolvedValue('tournament');
      api.delete.mockResolvedValue(detail());

      await flow.unregister(3, social({ userRegistered: true }));

      expect(api.delete.mock.calls.map((c) => c[0])).toEqual(['/events/3/registrations/me']);
    });

    it('cancel: no calls, returns null', async () => {
      confirm.choose.mockResolvedValue(null);
      await expect(flow.unregister(3, social({ userRegistered: true }))).resolves.toBeNull();

      confirm.ask.mockResolvedValue(false);
      await expect(flow.unregister(3, null)).resolves.toBeNull();

      expect(api.delete).not.toHaveBeenCalled();
    });
  });

  describe('updateComment', () => {
    it('confirms, patches and toasts', async () => {
      const fresh = detail();
      api.patch.mockResolvedValue(fresh);

      await expect(flow.updateComment(3, 'new')).resolves.toBe(fresh);

      expect(confirm.ask).toHaveBeenCalledWith('COMMENT?');
      expect(api.patch).toHaveBeenCalledWith('/events/3/registrations/me', { comment: 'new' }, undefined);
      expect(notify.success).toHaveBeenCalledWith('events.comment_update_success');
    });

    it('does nothing when cancelled', async () => {
      confirm.ask.mockResolvedValue(false);

      await expect(flow.updateComment(3, 'new')).resolves.toBeNull();

      expect(api.patch).not.toHaveBeenCalled();
    });
  });
});
