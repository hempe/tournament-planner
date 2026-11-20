import {
  ApplicationConfig,
  ErrorHandler,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { routes } from './app.routes';
import { API_HOOKS } from './core/api/api-client';
import { AppErrorHandler } from './core/errors/app-error-handler';
import { apiHooks, initializeApp } from './core/init';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes, withComponentInputBinding()),
    { provide: ErrorHandler, useClass: AppErrorHandler },
    { provide: API_HOOKS, useFactory: apiHooks },
    provideAppInitializer(initializeApp),
  ],
};
