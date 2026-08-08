import { apiRequest } from '../../../lib/api';

export type OrderStatus = 'open' | 'completed' | 'cancelled' | 'archived';
export type PaymentStatus =
  | 'pending'
  | 'authorized'
  | 'paid'
  | 'partially_paid'
  | 'refunded'
  | 'partially_refunded'
  | 'failed'
  | 'voided';
export type FulfillmentStatus = 'unfulfilled' | 'partial' | 'fulfilled' | 'returned';

export interface AdminOrderSummary {
  id: string;
  number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  fulfillment_status: FulfillmentStatus;
  customer: { id?: string; name: string | null; email: string | null };
  currency: string;
  grand_total: string;
  placed_at: string | null;
}

export interface AdminOrderDetail extends AdminOrderSummary {
  customer_phone: string | null;
  subtotal: string;
  discount_total: string;
  tax_total: string;
  shipping_total: string;
  notes: string | null;
  items: Array<{
    id: string;
    product_title: string;
    variant_title: string | null;
    sku: string | null;
    quantity: number;
    unit_price: string;
    discount_total: string;
    tax_total: string;
    line_total: string;
  }>;
  addresses: Array<{
    type: 'shipping' | 'billing';
    first_name: string;
    last_name: string;
    company: string | null;
    phone: string | null;
    country_code: string;
    province: string | null;
    district: string | null;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string | null;
  }>;
  tax_lines: Array<{ name: string; rate: string; amount: string }>;
  payments: Array<{
    id: string;
    provider: string;
    status: PaymentStatus;
    amount: string;
    currency: string;
    payment_method_type: string | null;
    paid_at: string | null;
  }>;
  timeline: Array<{ id: string; type: string; message: string; created_at: string | null }>;
}

export interface CustomerSummary {
  id: string;
  first_name: string | null;
  last_name: string | null;
  name: string;
  email: string;
  phone: string | null;
  status: 'active' | 'disabled' | 'blocked';
  total_orders: number;
  total_spent: string;
  last_order_at: string | null;
}

export interface CustomerDetail extends CustomerSummary {
  accepts_marketing: boolean;
  average_order_value: string;
  addresses: Array<{
    id: string;
    type: 'shipping' | 'billing';
    first_name: string;
    last_name: string;
    country_code: string;
    province: string | null;
    district: string | null;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string | null;
    is_default: boolean;
  }>;
  orders: AdminOrderSummary[];
  timeline: Array<{ type: string; created_at: string | null; metadata: Record<string, unknown> | null }>;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface DashboardMetrics {
  currency: string;
  range: 'today' | '7d' | '30d';
  sales: string;
  orders: number;
  average_order: string;
  customers: number;
  recent_orders: AdminOrderSummary[];
}

interface DataResponse<T> {
  data: T;
}

interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

function queryString(filters: Record<string, string | undefined>): string {
  const query = new URLSearchParams(
    Object.entries(filters).filter((entry): entry is [string, string] => Boolean(entry[1])),
  );

  return query.size === 0 ? '' : `?${query.toString()}`;
}

export function getDashboardMetrics(range: DashboardMetrics['range']): Promise<DataResponse<DashboardMetrics>> {
  return apiRequest(`/api/v1/dashboard${queryString({ range })}`);
}

export function listOrders(filters: {
  q?: string;
  status?: string;
  payment_status?: string;
  fulfillment_status?: string;
  page?: string;
}): Promise<PaginatedResponse<AdminOrderSummary>> {
  return apiRequest(`/api/v1/orders${queryString(filters)}`);
}

export function getOrder(orderId: string): Promise<DataResponse<AdminOrderDetail>> {
  return apiRequest(`/api/v1/orders/${orderId}`);
}

export function cancelOrder(orderId: string): Promise<DataResponse<AdminOrderDetail>> {
  return apiRequest(`/api/v1/orders/${orderId}/cancel`, { method: 'POST' });
}

export function listCustomers(filters: { q?: string; page?: string }): Promise<PaginatedResponse<CustomerSummary>> {
  return apiRequest(`/api/v1/customers${queryString(filters)}`);
}

export function getCustomer(customerId: string): Promise<DataResponse<CustomerDetail>> {
  return apiRequest(`/api/v1/customers/${customerId}`);
}