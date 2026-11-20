import { ApiError } from './api-client';

/** `resource().error()` may wrap the thrown error; returns the ApiError inside, if any. */
export function asApiError(error: unknown): ApiError | null {
  if (error instanceof ApiError) {
    return error;
  }
  const cause = (error as { cause?: unknown } | undefined)?.cause;
  return cause instanceof ApiError ? cause : null;
}
