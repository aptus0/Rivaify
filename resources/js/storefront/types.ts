export interface StorefrontStore {
  name: string;
  slug: string;
  currency: string;
  locale: string;
  announcements: Array<{ id: string; message: string; ends_at: string | null }>;
}

export interface ProductVariant {
  id: string;
  title: string;
  sku: string | null;
  price: string;
  compare_at_price: string | null;
  available: boolean;
  available_quantity: number | null;
}

export interface StorefrontProduct {
  id: string;
  title: string;
  slug: string;
  description: string | null;
  is_taxable: boolean;
  requires_shipping: boolean;
  media: Array<{ id: string; url: string; alt_text: string | null; width: number | null; height: number | null; is_featured: boolean }>;
  variants: ProductVariant[];
  options: Array<{ name: string; values: string[] }> | null;
}

export interface StorefrontRuntimeSection {
  id: string;
  type: string;
  enabled: boolean;
  locked?: boolean;
  settings: Record<string, unknown>;
  blocks?: Array<{ id: string; type: string; settings: Record<string, unknown> }>;
}

export interface StorefrontRuntimeDocument {
  version: 1;
  template: string;
  sections: StorefrontRuntimeSection[];
}

export interface StorefrontRuntime {
  mode: 'published' | 'preview';
  store: StorefrontStore;
  document: StorefrontRuntimeDocument;
  products: StorefrontProduct[];
}

export interface CartItem {
  id: string;
  product: { id: string | null; title: string | null; slug: string | null };
  variant: { id: string | null; title: string | null; sku: string | null };
  quantity: number;
  unit_price: string;
  original_price: string;
  discount_amount: string;
  tax_amount: string;
  line_total: string;
}

export interface Cart {
  id: string;
  status: string;
  currency: string;
  items: CartItem[];
  subtotal: string;
  discount_total: string;
  tax_total: string;
  tax_inclusive: boolean;
  shipping_total: string;
  grand_total: string;
  discount: { code: string | null; type: string } | null;
  expires_at: string | null;
}

export interface AddressInput {
  first_name: string;
  last_name: string;
  company?: string;
  phone?: string;
  country_code: string;
  province?: string;
  district?: string;
  address_line_1: string;
  address_line_2?: string;
  postal_code?: string;
}

export interface CheckoutAddress extends AddressInput {
  id: string;
}

export interface Checkout {
  id: string;
  token: string;
  status:
    | 'initiated'
    | 'customer_information'
    | 'address'
    | 'shipping'
    | 'payment'
    | 'processing'
    | 'completed'
    | 'failed'
    | 'expired';
  email: string | null;
  phone: string | null;
  customer: { id: string; first_name: string | null; last_name: string | null } | null;
  shipping_address: CheckoutAddress | null;
  billing_address: CheckoutAddress | null;
  shipping_method: { id: string; name: string; estimated_days_min: number | null; estimated_days_max: number | null } | null;
  cart: Cart;
  currency: string;
  subtotal: string;
  discount_total: string;
  tax_total: string;
  tax_inclusive: boolean;
  shipping_total: string;
  grand_total: string;
  discount: { code: string | null; type: string } | null;
  order: { id: string; number: string; payment_status: string; fulfillment_status: string } | null;
  expires_at: string | null;
  completed_at: string | null;
}

export interface ShippingQuote {
  id: string;
  name: string;
  type: string;
  amount: string;
  currency: string;
  estimated_days_min: number | null;
  estimated_days_max: number | null;
}

export interface PaymentResult {
  id: string;
  status: string;
  amount: string;
  currency: string;
  order_id: string | null;
  checkout: Checkout;
  gateway: { provider: string; iframe_url: string | null };
}
