import { apiRequest } from '../../../lib/api';

export type AnalyticsRange = '7d' | '30d' | '90d';
export interface TrafficSource { source: string; sessions: number; share: number }
export interface FunnelStep { key: 'page_view' | 'product_view' | 'add_to_cart' | 'checkout_started' | 'purchase'; label: string; sessions: number; conversion_rate: number | null; step_rate: number | null }
export interface AnalyticsData {
  range: AnalyticsRange; currency: string; period: { from: string; to: string };
  metrics: { net_sales: string; refunds: string; orders: number; average_order: string; new_customers: number; returning_customers: number };
  changes: Record<'net_sales' | 'orders' | 'average_order' | 'new_customers', number | null>;
  series: Array<{ date: string; sales: string; gross_sales: string; refunds: string; orders: number }>;
  top_products: Array<{ title: string; quantity: number; revenue: string }>;
  top_products_basis: 'gross_order_item_revenue_excludes_refunds';
  payment_breakdown: Array<{ status: string; total: number }>;
  traffic: { available: boolean; sessions: number; total_events: number; sources: TrafficSource[]; funnel: FunnelStep[] };
}
export function getAnalytics(range: AnalyticsRange): Promise<{ data: AnalyticsData }> { return apiRequest(`/api/v1/analytics?range=${range}`); }
