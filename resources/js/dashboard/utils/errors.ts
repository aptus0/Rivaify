import { ApiError } from '../lib/api';

/** First validation message per field, or a generic fallback for non-422 errors. */
export function describeApiError(error: unknown): { fieldErrors: Record<string, string>; message: string | null } {
  if (error instanceof ApiError) {
    const validation = error.validationErrors;
    if (validation) {
      const fieldErrors = Object.fromEntries(
        Object.entries(validation).map(([field, messages]) => [field, messages[0]]),
      );
      return { fieldErrors, message: null };
    }
    const body = error.body as { message?: string } | undefined;
    return { fieldErrors: {}, message: body?.message ?? 'Bir şeyler ters gitti, lütfen tekrar deneyin.' };
  }
  return { fieldErrors: {}, message: 'Bağlantı hatası, lütfen tekrar deneyin.' };
}
