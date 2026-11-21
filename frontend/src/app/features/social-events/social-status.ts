import { SocialEventSummaryDto } from '../../core/api/generated';

/** Status line of a social event: a key, plus a count when the key is a "one|many" plural. */
export interface SocialStatus {
  key: string;
  count?: number;
  tone: 'registered' | 'available' | 'full';
}

/** Registered / n spots / full; null when the event has no capacity limit (no tables). */
export function socialStatus(event: SocialEventSummaryDto): SocialStatus | null {
  if (event.userRegistered) {
    return { key: 'social_events.registered', tone: 'registered' };
  }
  if (event.available === null) {
    return null;
  }
  return event.available <= 0
    ? { key: 'social_events.full', tone: 'full' }
    : { key: 'social_events.available', count: event.available, tone: 'available' };
}
