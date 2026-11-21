import { UserOptionDto } from '../../../core/api/generated';
import { matchesWords, queryWords } from '../../../shared/search';

/**
 * Members whose name contains every word of the query (any order, case and accent insensitive);
 * at most `limit` of them, plus how many more matched. An empty query matches nobody.
 */
export function filterMembers(users: readonly UserOptionDto[], query: string, limit: number): { users: UserOptionDto[]; more: number } {
  const words = queryWords(query);
  if (words.length === 0) {
    return { users: [], more: 0 };
  }
  const found = users.filter((u) => matchesWords(u.displayName, words));
  return { users: found.slice(0, limit), more: Math.max(0, found.length - limit) };
}
