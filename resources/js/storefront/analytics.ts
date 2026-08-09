export type StorefrontClientEvent = 'page_view' | 'product_view' | 'add_to_cart' | 'checkout_started';

interface StorefrontEventInput {
  product_id?: string;
  checkout_token?: string;
  path?: string;
}

interface Attribution {
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
  referrer_host?: string;
}

const SESSION_KEY = 'rivaify.analytics.session';
const ATTRIBUTION_KEY = 'rivaify.analytics.attribution';
let memorySessionId: string | null = null;
let memoryAttribution: Attribution | null = null;

function randomSessionId(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
}

function sessionId(): string {
  if (memorySessionId) return memorySessionId;
  try {
    const stored = sessionStorage.getItem(SESSION_KEY);
    if (stored) return (memorySessionId = stored);
    const created = randomSessionId();
    sessionStorage.setItem(SESSION_KEY, created);
    return (memorySessionId = created);
  } catch {
    return (memorySessionId = randomSessionId());
  }
}

function referrerHost(): string | undefined {
  if (!document.referrer) return undefined;
  try {
    return new URL(document.referrer).hostname || undefined;
  } catch {
    return undefined;
  }
}

function attribution(): Attribution {
  if (memoryAttribution) return memoryAttribution;
  try {
    const stored = sessionStorage.getItem(ATTRIBUTION_KEY);
    if (stored) return (memoryAttribution = JSON.parse(stored) as Attribution);
  } catch {
    // Storage can be unavailable in privacy modes; in-memory attribution is enough.
  }

  const params = new URLSearchParams(window.location.search);
  const initial: Attribution = {
    ...(params.get('utm_source') ? { utm_source: params.get('utm_source') ?? undefined } : {}),
    ...(params.get('utm_medium') ? { utm_medium: params.get('utm_medium') ?? undefined } : {}),
    ...(params.get('utm_campaign') ? { utm_campaign: params.get('utm_campaign') ?? undefined } : {}),
    ...(referrerHost() ? { referrer_host: referrerHost() } : {}),
  };
  memoryAttribution = initial;
  try {
    sessionStorage.setItem(ATTRIBUTION_KEY, JSON.stringify(initial));
  } catch {
    // Tracking is deliberately best-effort and never blocks commerce actions.
  }

  return initial;
}

export function trackStorefrontEvent(eventType: StorefrontClientEvent, input: StorefrontEventInput = {}): void {
  const body = JSON.stringify({
    event_type: eventType,
    session_id: sessionId(),
    path: input.path ?? window.location.pathname,
    ...input,
    ...attribution(),
  });

  void fetch('/api/storefront/v1/events', {
    method: 'POST',
    credentials: 'include',
    keepalive: true,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body,
  }).catch(() => undefined);
}
