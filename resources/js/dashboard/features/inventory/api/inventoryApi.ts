import { apiRequest } from '../../../lib/api';
import type {
  InventoryAdjustmentPayload,
  InventoryItem,
  InventoryListResponse,
  InventoryStatus,
} from './types';

export interface InventoryFilters {
  q?: string;
  status?: InventoryStatus;
  location_id?: string;
  page?: number;
  per_page?: number;
}

function queryString(filters: InventoryFilters): string {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') query.set(key, String(value));
  });

  return query.size === 0 ? '' : `?${query.toString()}`;
}

export function listInventory(filters: InventoryFilters = {}): Promise<InventoryListResponse> {
  return apiRequest(`/api/v1/inventory${queryString(filters)}`);
}

export function adjustInventory(
  inventoryItemId: string,
  locationId: string,
  payload: InventoryAdjustmentPayload,
): Promise<{ data: InventoryItem }> {
  return apiRequest(`/api/v1/inventory/${inventoryItemId}/locations/${locationId}`, {
    method: 'PATCH',
    body: payload,
  });
}
