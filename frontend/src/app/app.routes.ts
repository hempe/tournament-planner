import { Routes } from '@angular/router';
import { adminGuard, authGuard, guestGuard } from './core/auth/guards';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./features/calendar/calendar.page').then((m) => m.CalendarPage) },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'events',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/events/event-list.page').then((m) => m.EventListPage),
  },
  {
    path: 'events/new',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/events/event-create.page').then((m) => m.EventCreatePage),
  },
  {
    path: 'events/bulk/new',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/events/bulk-create.page').then((m) => m.BulkCreatePage),
  },
  {
    path: 'events/:id/admin',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/events/event-admin.page').then((m) => m.EventAdminPage),
  },
  {
    path: 'events/:id/guests/:guestId/edit',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/events/guest-edit.page').then((m) => m.GuestEditPage),
  },
  {
    path: 'events/:id/guests/new',
    loadComponent: () => import('./features/guests/guest-registration.page').then((m) => m.GuestRegistrationPage),
  },
  {
    path: 'events/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./features/events/event-detail.page').then((m) => m.EventDetailPage),
  },
  {
    path: 'social-events/new',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/social-events/social-event-create.page').then((m) => m.SocialEventCreatePage),
  },
  {
    path: 'social-events/:id/admin',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/social-events/social-event-admin.page').then((m) => m.SocialEventAdminPage),
  },
  {
    path: 'social-events/:id/guests/new',
    loadComponent: () =>
      import('./features/guests/social-guest-registration.page').then((m) => m.SocialGuestRegistrationPage),
  },
  {
    path: 'social-events/:id',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/social-events/social-event-detail.page').then((m) => m.SocialEventDetailPage),
  },
  {
    path: 'users',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/users/user-list.page').then((m) => m.UserListPage),
  },
  {
    path: 'users/new',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/users/user-create.page').then((m) => m.UserCreatePage),
  },
  {
    path: 'users/:id/edit',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/admin/users/user-edit.page').then((m) => m.UserEditPage),
  },
  { path: '**', loadComponent: () => import('./features/not-found/not-found.page').then((m) => m.NotFoundPage) },
];
