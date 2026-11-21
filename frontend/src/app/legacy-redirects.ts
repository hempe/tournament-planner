import { Routes } from '@angular/router';

/**
 * Form actions of the old server-rendered UI. A page left open from before the switch
 * posts to one of these URLs; .htaccess answers with 303 to the same path, and these
 * routes send the user on to the page the action belonged to.
 */
export const legacyRedirects: Routes = [
  ...['register', 'unregister', 'comment'].map((action) => ({ path: `events/:id/${action}`, redirectTo: 'events/:id' })),
  ...['lock', 'unlock', 'delete', 'export'].map((action) => ({ path: `events/:id/${action}`, redirectTo: 'events/:id/admin' })),
  { path: 'events/bulk/preview', redirectTo: 'events/bulk/new' },
  { path: 'events/bulk/store', redirectTo: 'events/bulk/new' },
  { path: 'events/:id/guests/:guestId/update', redirectTo: 'events/:id/guests/:guestId/edit' },
  { path: 'events/:id/guests/:guestId/delete', redirectTo: 'events/:id/admin' },
  ...['register', 'unregister'].map((action) => ({ path: `social-events/:id/${action}`, redirectTo: 'social-events/:id' })),
  ...['lock', 'unlock', 'delete'].map((action) => ({ path: `social-events/:id/${action}`, redirectTo: 'social-events/:id/admin' })),
  { path: 'social-events/:id/registrations/:registrationId/delete', redirectTo: 'social-events/:id/admin' },
  ...['update', 'admin', 'delete'].map((action) => ({ path: `users/:id/${action}`, redirectTo: 'users' })),
  { path: 'logout', redirectTo: '' },
  { path: 'guest', redirectTo: '' },
];
