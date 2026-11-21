import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SocialEventSummaryDto } from '../../core/api/generated';
import { I18n } from '../../core/i18n/i18n';
import { SocialEventRow } from './social-event-row';
import { socialStatus } from './social-status';

function social(over: Partial<SocialEventSummaryDto> = {}): SocialEventSummaryDto {
  return { id: 9, tournamentId: 3, name: 'Abendessen', date: '2099-06-01', isLocked: false, totalCapacity: 20, registered: 5, available: 15, userRegistered: false, ...over };
}

describe('socialStatus', () => {
  it('registered wins; no capacity gives no status; else free spots or full', () => {
    expect(socialStatus(social({ userRegistered: true, available: 0 }))).toEqual({ key: 'social_events.registered', tone: 'registered' });
    expect(socialStatus(social({ available: null }))).toBeNull();
    expect(socialStatus(social({ available: 0 }))).toEqual({ key: 'social_events.full', tone: 'full' });
    expect(socialStatus(social({ available: 2 }))).toEqual({ key: 'social_events.available', count: 2, tone: 'available' });
  });
});

describe('SocialEventRow', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', {
      social_events: { registered: 'Angemeldet', full: 'Ausgebucht', available: '1 Platz verfügbar|:count Plätze verfügbar', edit: 'Bearbeiten' },
    });
  });

  async function render(event: SocialEventSummaryDto, note: string | null = null) {
    const fixture = TestBed.createComponent(SocialEventRow);
    fixture.componentRef.setInput('event', event);
    fixture.componentRef.setInput('link', ['/social-events', event.id]);
    fixture.componentRef.setInput('note', note);
    await fixture.whenStable();
    return fixture.nativeElement as HTMLElement;
  }

  it('links to the social event and shows icon, name and the plural status', async () => {
    const el = await render(social({ available: 1 }));
    const link = el.querySelector('a[data-social-row]')!;

    expect(link.getAttribute('href')).toBe('/social-events/9');
    expect(link.textContent).toContain('Abendessen');
    expect(link.querySelector('[data-status]')?.textContent?.trim()).toBe('1 Platz verfügbar');
    expect(Array.from(link.querySelectorAll('ng-icon')).map((i) => i.getAttribute('name'))).toEqual(['lucideUtensils', 'lucideChevronRight']);
  });

  it('shows full / registered, or the note instead of the status', async () => {
    expect((await render(social({ available: 0 }))).textContent).toContain('Ausgebucht');
    const withNote = await render(social({ userRegistered: true }), 'social_events.edit');
    expect(withNote.textContent).toContain('Bearbeiten');
    expect(withNote.querySelector('[data-status]')).toBeNull();
  });
});
