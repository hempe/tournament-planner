import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth/guards';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./features/calendar/calendar.page').then((m) => m.CalendarPage) },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'events/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./features/events/event-detail.page').then((m) => m.EventDetailPage),
  },
  {
    path: 'social-events/:id',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/social-events/social-event-detail.page').then((m) => m.SocialEventDetailPage),
  },
  { path: '**', loadComponent: () => import('./features/not-found/not-found.page').then((m) => m.NotFoundPage) },
];
