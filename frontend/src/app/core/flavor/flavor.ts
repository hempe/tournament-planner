import { Injectable, InjectionToken, Provider, inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ACTIVE_FLAVOR } from './active-flavor';

export type FeatureSwitch = 'socialEvents' | 'guests' | 'prices' | 'description' | 'deadline' | 'genderSplit';
export type UserField = 'rfeg' | 'memberNumber' | 'phone' | 'handicap' | 'priority';

/** Shape of frontend/flavors/<name>/flavor.json (validated on the PHP side, see TP\Core\Flavor). */
export interface FlavorConfig {
  name: string;
  shortName: string;
  timezone: string;
  usernameOrder: 'firstLast' | 'lastFirst';
  website: string | null;
  features: Record<FeatureSwitch, boolean> & { waitlistOrder: 'timestamp' | 'priority'; userFields: UserField[] };
}

/** The flavour this bundle was built for (angular.json configuration `elfaro` / `amigos`). */
export const FLAVOR = new InjectionToken<FlavorConfig>('FLAVOR', { providedIn: 'root', factory: () => ACTIVE_FLAVOR });

export function provideFlavor(flavor: FlavorConfig): Provider {
  return { provide: FLAVOR, useValue: flavor };
}

@Injectable({ providedIn: 'root' })
export class Features {
  private readonly flavor = inject(FLAVOR);

  has(feature: FeatureSwitch): boolean {
    return this.flavor.features[feature];
  }

  userField(field: UserField): boolean {
    return this.flavor.features.userFields.includes(field);
  }
}

/** Route guard: switched-off features look like unknown pages. */
export function featureGuard(feature: FeatureSwitch): CanActivateFn {
  return () => inject(Features).has(feature) || inject(Router).parseUrl('/not-found');
}
