/**
 * Thin fetch wrapper for Sanctum's cookie-based SPA auth (brief: React
 * frontend + api.rivaify.com). Every mutating request needs the
 * XSRF-TOKEN cookie Sanctum issues, fetched once via /sanctum/csrf-cookie
 * before the first request — see ensureCsrfCookie().
 */

// Empty string = same-origin: app.rivaify.com serves both the SPA and its
// API from the same Laravel app (see routes/web.php's Route::domain group).
// Set VITE_API_BASE_URL if the API ever moves to a separate api.rivaify.com
// origin — SANCTUM_STATEFUL_DOMAINS/CORS would need updating to match.
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

let csrfCookiePromise: Promise<void> | null = null;

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) {
    return Promise.resolve();
  }
  csrfCookiePromise ??= fetch(`${API_BASE_URL}/sanctum/csrf-cookie`, {
    credentials: 'include',
  })
    .then((response) => {
      if (!response.ok) throw new ApiError(response.status, undefined);
    })
    .finally(() => {
      // Do not retain a resolved/rejected request forever: the cookie may
      // expire or be cleared while the SPA remains open.
      csrfCookiePromise = null;
    });
  return csrfCookiePromise;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`API request failed with status ${status}`);
  }

  /** Laravel validation errors: { message, errors: { field: string[] } } */
  get validationErrors(): Record<string, string[]> | null {
    if (this.status === 422 && this.body && typeof this.body === 'object' && 'errors' in this.body) {
      return (this.body as { errors: Record<string, string[]> }).errors;
    }
    return null;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
};

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET';
  const isFormData = options.body instanceof FormData;

  if (method !== 'GET') {
    await ensureCsrfCookie();
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
      ...(method !== 'GET' ? { 'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '' } : {}),
    },
    body: options.body ? (isFormData ? options.body as FormData : JSON.stringify(options.body)) : undefined,
  });

  const contentType = response.headers.get('content-type') ?? '';
  const payload = contentType.includes('application/json') ? await response.json() : undefined;

  if (!response.ok) {
    throw new ApiError(response.status, payload);
  }

  return payload as T;
}
