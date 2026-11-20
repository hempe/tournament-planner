import { ApiError } from './api-client';
import { asApiError } from './api-error';

describe('asApiError', () => {
  it('returns ApiErrors, also when wrapped as cause, and null otherwise', () => {
    const error = new ApiError(404, 'events.not_found');

    expect(asApiError(error)).toBe(error);
    expect(asApiError(new Error('wrapped', { cause: error }))).toBe(error);
    expect(asApiError(new Error('x'))).toBeNull();
    expect(asApiError(undefined)).toBeNull();
  });
});
