import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { I18n } from './i18n';
import { TranslatePipe } from './translate.pipe';

@Component({
  imports: [TranslatePipe],
  template: `<span>{{ 'events.unregister' | t }}</span><b>{{ 'auth.register_user' | t: { username: 'Jane' } }}</b>`,
})
class Host {}

describe('TranslatePipe', () => {
  it('translates keys in templates', async () => {
    TestBed.inject(I18n).use('de', {
      events: { unregister: 'Turnier abmelden' },
      auth: { register_user: ':username anmelden?' },
    });
    const fixture = TestBed.createComponent(Host);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('span').textContent).toBe('Turnier abmelden');
    expect(fixture.nativeElement.querySelector('b').textContent).toBe('Jane anmelden?');
  });
});
