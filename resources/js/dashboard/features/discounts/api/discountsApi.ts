import { apiRequest } from '../../../lib/api';

export type DiscountType = 'percentage' | 'fixed_amount' | 'free_shipping';
export type DiscountStatus = 'active' | 'inactive';

export interface DiscountCondition {
  type: 'cart_total' | 'products' | 'collections';
  operator: string | null;
  value: Record<string, unknown>;
}

export interface Discount {
  id: string;
  name: string;
  code: string | null;
  type: DiscountType;
  value: string;
  status: DiscountStatus;
  availability: 'active' | 'inactive' | 'scheduled' | 'expired' | 'usage_limit_reached';
  starts_at: string | null;
  ends_at: string | null;
  usage_limit: number | null;
  usage_count: number;
  minimum_purchase: string | null;
  created_at: string | null;
  updated_at: string | null;
  conditions: DiscountCondition[];
}

export interface DiscountPayload {
  name: string;
  code: string | null;
  type: DiscountType;
  value: string;
  status: DiscountStatus;
  starts_at: string | null;
  ends_at: string | null;
  usage_limit: number | null;
  minimum_purchase: string | null;
}

interface DiscountListResponse {
  data: Discount[];
  currency: string;
  summary: { all: number; active: number; inactive: number; total_usage: number };
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface DiscountResponse {
  data: Discount;
}

function queryString(filters: Record<string, string | undefined>): string {
  const query = new URLSearchParams(
    Object.entries(filters).filter((entry): entry is [string, string] => Boolean(entry[1])),
  );

  return query.size ? `?${query.toString()}` : '';
}

export function listDiscounts(filters: { q?: string; status?: DiscountStatus; page?: string }): Promise<DiscountListResponse> {
  return apiRequest(`/api/v1/discounts${queryString(filters)}`);
}

export function getDiscount(discountId: string): Promise<DiscountResponse> {
  return apiRequest(`/api/v1/discounts/${discountId}`);
}

export function createDiscount(payload: DiscountPayload): Promise<DiscountResponse> {
  return apiRequest('/api/v1/discounts', { method: 'POST', body: payload });
}

export function updateDiscount(discountId: string, payload: DiscountPayload): Promise<DiscountResponse> {
  return apiRequest(`/api/v1/discounts/${discountId}`, { method: 'PATCH', body: payload });
}

export function deleteDiscount(discountId: string): Promise<{ data: { deleted: boolean } }> {
  return apiRequest(`/api/v1/discounts/${discountId}`, { method: 'DELETE' });
}
