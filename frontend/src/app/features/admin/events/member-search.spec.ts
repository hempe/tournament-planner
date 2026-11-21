import { filterMembers } from './member-search';

const users = [
  { id: 1, displayName: 'Anna Müller' },
  { id: 2, displayName: 'Hans Muster' },
  { id: 3, displayName: 'Peter Meier' },
  { id: 4, displayName: 'Juan Carlos Aranda Cabello' },
];

describe('filterMembers', () => {
  it('matches nobody for an empty or blank query', () => {
    expect(filterMembers(users, '', 8)).toEqual({ users: [], more: 0 });
    expect(filterMembers(users, '   ', 8)).toEqual({ users: [], more: 0 });
  });

  it('is case and accent insensitive', () => {
    expect(filterMembers(users, 'MULLER', 8).users.map((u) => u.id)).toEqual([1]);
    expect(filterMembers(users, 'mü', 8).users.map((u) => u.id)).toEqual([1, 2]);
  });

  it('requires every word, in any order', () => {
    expect(filterMembers(users, 'cabello juan', 8).users.map((u) => u.id)).toEqual([4]);
    expect(filterMembers(users, 'juan meier', 8).users).toEqual([]);
  });

  it('limits the result and counts the rest', () => {
    expect(filterMembers(users, 'e', 2)).toEqual({ users: [users[0], users[1]], more: 2 });
  });
});
