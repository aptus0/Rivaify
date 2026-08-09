import type {
  AddressInput,
  Cart,
  Checkout,
  PaymentResult,
  ShippingQuote,
  StorefrontProduct,
  StorefrontRuntime,
  StorefrontStore,
} from './types';

const API_PREFIX = '/api/storefront/v1';

export class StorefrontApiError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`Storefront API request failed with status ${status}`);
  }

  get messageFromApi(): string | null {
    return this.body && typeof this.body === 'object' && 'message' in this.body
      ? String((this.body as { message: unknown }).message)
      : null;
  }
}

interface ApiResponse<T> {
  data: T;
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: unknown;
  headers?: Record<string, string>;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<ApiResponse<T>> {
  const response = await fetch(`${API_PREFIX}${path}`, {
    method: options.method ?? 'GET',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const contentType = response.headers.get('content-type') ?? '';
  const payload = contentType.includes('application/json') ? await response.json() : null;
  if (!response.ok) throw new StorefrontApiError(response.status, payload);

  return payload as ApiResponse<T>;
}

export function getStore(): Promise<ApiResponse<StorefrontStore>> {
  return request('/store');
}

export function getRuntime(): Promise<ApiResponse<StorefrontRuntime>> {
  return request('/runtime');
}

export function listProducts(): Promise<ApiResponse<StorefrontProduct[]>> {
  return request('/products');
}

export function getProduct(slug: string): Promise<ApiResponse<StorefrontProduct>> {
  return request(`/products/${slug}`);
}

export function getCart(): Promise<ApiResponse<Cart>> {
  return request('/cart');
}

export function addCartItem(variantId: string, quantity: number): Promise<ApiResponse<Cart>> {
  return request('/cart/items', { method: 'POST', body: { variant_id: variantId, quantity } });
}

export function updateCartItem(itemId: string, quantity: number): Promise<ApiResponse<Cart>> {
  return request(`/cart/items/${itemId}`, { method: 'PATCH', body: { quantity } });
}

export function removeCartItem(itemId: string): Promise<ApiResponse<Cart>> {
  return request(`/cart/items/${itemId}`, { method: 'DELETE' });
}

export function startCheckout(): Promise<ApiResponse<Checkout>> {
  return request('/checkout', { method: 'POST' });
}

export function getCheckout(token: string): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}`);
}

export function updateCheckoutCustomer(
  token: string,
  input: { email: string; first_name: string; last_name: string; phone: string; accepts_marketing?: boolean },
): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/customer`, { method: 'PATCH', body: input });
}

export function updateCheckoutAddress(
  token: string,
  shipping: AddressInput,
  billingSameAsShipping: boolean,
  billing?: AddressInput,
): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/address`, {
    method: 'PATCH',
    body: { shipping, billing_same_as_shipping: billingSameAsShipping, ...(billing ? { billing } : {}) },
  });
}

export function getShippingQuotes(token: string): Promise<ApiResponse<ShippingQuote[]>> {
  return request(`/checkouts/${token}/shipping-methods`);
}

export function selectCheckoutShipping(token: string, shippingMethodId: string): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/shipping`, { method: 'POST', body: { shipping_method_id: shippingMethodId } });
}

export function applyCheckoutDiscount(token: string, code: string): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/discount`, { method: 'POST', body: { code } });
}

export function applyCheckoutTax(token: string): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/tax`, { method: 'POST' });
}

export function payCheckout(
  token: string,
  provider: string,
  paymentMethodType: string,
  idempotencyKey: string,
): Promise<ApiResponse<PaymentResult>> {
  return request(`/checkouts/${token}/payment`, {
    method: 'POST',
    body: { provider, payment_method_type: paymentMethodType },
    headers: { 'Idempotency-Key': idempotencyKey },
  });
}

export function getCheckoutConfirmation(token: string): Promise<ApiResponse<Checkout>> {
  return request(`/checkouts/${token}/confirmation`);
}
