/** Lower case without accents, so "muller" finds "Müller". */
function normalize(text: string): string {
  return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

/** The words of a search query, normalised; empty for a blank query. */
export function queryWords(query: string): string[] {
  return normalize(query).split(/\s+/).filter((w) => w !== '');
}

/** True when `text` contains every word (any order, case and accent insensitive). */
export function matchesWords(text: string, words: readonly string[]): boolean {
  const haystack = normalize(text);
  return words.every((w) => haystack.includes(w));
}
