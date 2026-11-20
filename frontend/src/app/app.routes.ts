import { Routes } from '@angular/router';
import { guestGuard } from './core/auth/guards';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./features/home/home.page').then((m) => m.HomePage) },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  { path: '**', loadComponent: () => import('./features/not-found/not-found.page').then((m) => m.NotFoundPage) },
];
