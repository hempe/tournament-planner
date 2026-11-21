import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { legacyRedirects } from './legacy-redirects';

@Component({ template: '' })
class Target {}

describe('legacyRedirects', () => {
  async function landsOn(url: string): Promise<string> {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          ...legacyRedirects,
          { path: '', component: Target },
          { path: 'events/bulk/new', component: Target },
          { path: 'events/:id/admin', component: Target },
          { path: 'events/:id/guests/:guestId/edit', component: Target },
          { path: 'events/:id', component: Target },
          { path: 'social-events/:id/admin', component: Target },
          { path: 'social-events/:id', component: Target },
          { path: 'users', component: Target },
        ]),
      ],
    });
    const router = TestBed.inject(Router);
    await router.navigateByUrl(url);
    return router.url;
  }

  it.each([
    ['/events/5/register', '/events/5'],
    ['/events/5/unregister', '/events/5'],
    ['/events/5/comment', '/events/5'],
    ['/events/5/lock', '/events/5/admin'],
    ['/events/bulk/store', '/events/bulk/new'],
    ['/events/5/guests/9/update', '/events/5/guests/9/edit'],
    ['/events/5/guests/9/delete', '/events/5/admin'],
    ['/social-events/3/register', '/social-events/3'],
    ['/social-events/3/delete', '/social-events/3/admin'],
    ['/social-events/3/registrations/7/delete', '/social-events/3/admin'],
    ['/users/4/update', '/users'],
    ['/logout', '/'],
    ['/guest', '/'],
  ])('%s → %s', async (from, to) => {
    expect(await landsOn(from)).toBe(to);
  });
});
