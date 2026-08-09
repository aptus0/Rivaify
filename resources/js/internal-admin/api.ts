/**
 * Self-contained API client for the internal-admin SPA — mirrors
 * dashboard/lib/api.ts's cookie+CSRF pattern rather than importing across
 * SPA boundaries (same convention as resources/js/storefront/api.ts).
 * ins.rivaify.com uses its own host-only internal session cookie, so a
 * merchant dashboard login never carries into this surface.
 */

let csrfCookiePromise: Promise<void> | null = null;

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) {
    return Promise.resolve();
  }
  csrfCookiePromise ??= fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    .then((response) => {
      if (!response.ok) throw new ApiError(response.status, undefined);
    })
    .finally(() => {
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

  get messageFromApi(): string | null {
    return this.body && typeof this.body === 'object' && 'message' in this.body
      ? String((this.body as { message: unknown }).message)
      : null;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
};

async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET';

  if (method !== 'GET') {
    await ensureCsrfCookie();
  }

  const response = await fetch(path, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(method !== 'GET' ? { 'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '' } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const contentType = response.headers.get('content-type') ?? '';
  const payload = contentType.includes('application/json') ? await response.json() : undefined;

  if (!response.ok) {
    throw new ApiError(response.status, payload);
  }

  return payload as T;
}

export interface CurrentStaff {
  authenticated: boolean;
  staff: {
    id: string;
    name: string;
    email: string;
    role: { key: string | null; name: string | null; permissions: string[] };
    two_factor_enabled: boolean;
    last_login_at: string | null;
  } | null;
}

export function me(): Promise<{ data: CurrentStaff }> {
  return apiRequest('/api/internal/v1/me');
}

export function login(email: string, password: string): Promise<void> {
  return apiRequest('/login', { method: 'POST', body: { email, password } });
}

export function logout(): Promise<void> {
  return apiRequest('/logout', { method: 'POST' });
}

export type DocumentType = 'tax_certificate' | 'identity' | 'signature_circular' | 'business_license' | 'other';

export interface VerificationDocumentSummary {
  id: string;
  type: DocumentType;
  original_filename: string | null;
  size_bytes: number | null;
  view_url: string | null;
}

export interface VerificationRequestSummary {
  id: string;
  status: string;
  submitted_at: string | null;
  rejection_reason: string | null;
  store: { name: string; slug: string };
  merchant: { type: string; owner: { name: string; email: string } | null };
  business: {
    legal_name: string | null;
    trade_name: string | null;
    registration_number: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    address: {
      line1: string;
      line2: string | null;
      city: string;
      state: string | null;
      postal_code: string | null;
      country_code: string;
    } | null;
  } | null;
  tax: { tax_office: string | null; tax_number: string | null; legal_entity_name: string | null } | null;
  documents: VerificationDocumentSummary[];
}

export function listVerificationRequests(): Promise<{ data: VerificationRequestSummary[] }> {
  return apiRequest('/api/admin/verification-requests');
}

export function getVerificationRequest(id: string): Promise<{ data: VerificationRequestSummary }> {
  return apiRequest(`/api/admin/verification-requests/${id}`);
}

export function approveVerificationRequest(id: string): Promise<{ data: VerificationRequestSummary }> {
  return apiRequest(`/api/admin/verification-requests/${id}/approve`, { method: 'POST' });
}

export function rejectVerificationRequest(id: string, reason: string): Promise<{ data: VerificationRequestSummary }> {
  return apiRequest(`/api/admin/verification-requests/${id}/reject`, { method: 'POST', body: { reason } });
}

export type SensitiveVerificationField = 'tax_number' | 'registration_number';

export function revealSensitiveField(
  id: string,
  field: SensitiveVerificationField,
): Promise<{ data: { field: SensitiveVerificationField; value: string | null } }> {
  return apiRequest(`/api/admin/verification-requests/${id}/sensitive-fields/reveal`, {
    method: 'POST',
    body: { field },
  });
}

export interface InternalDashboardMetrics {
  new_verifications: number;
  verification_in_review: number;
  active_stores: number;
  orders_today: number;
  payment_issues: number;
  refund_failures: number;
  shipping_failures: number;
  support_tickets: number;
  critical_alerts: number;
}

export interface InternalDashboardAction {
  severity: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  title: string;
  detail: string;
  href: string;
}

export interface InternalDashboard {
  date: string;
  metrics: InternalDashboardMetrics;
  action_center: InternalDashboardAction[];
  system: {
    laravel: string;
    database: string;
    queue_waiting: number;
    failed_jobs: number;
  };
}

export function getInternalDashboard(): Promise<{ data: InternalDashboard }> {
  return apiRequest('/api/internal/v1/dashboard');
}

export interface OperationCaseTab {
  key: string;
  label: string;
  count: number;
}

export interface OperationCaseSummary {
  id: string;
  case_number: string;
  type: string;
  title: string;
  priority: 'LOW' | 'NORMAL' | 'HIGH' | 'CRITICAL';
  status: string;
  store: { id: string; name: string; slug: string; status: string } | null;
  assigned_to: { id: string; name: string } | null;
  opened_at: string | null;
  age: string | null;
}

export function listOperationCases(tab = 'inbox'): Promise<{ data: { tabs: OperationCaseTab[]; items: OperationCaseSummary[] } }> {
  return apiRequest(`/api/internal/v1/cases?tab=${encodeURIComponent(tab)}`);
}
