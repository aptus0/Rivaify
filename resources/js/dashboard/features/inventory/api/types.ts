export type InventoryStatus = 'in_stock' | 'low_stock' | 'out_of_stock';

export interface InventoryLocation {
  id: string;
  name: string;
  code: string | null;
  type: string;
  fulfillment_enabled: boolean;
}

export interface InventoryQuantities {
  available: number;
  reserved: number;
  sellable: number;
  incoming: number;
}

export interface InventoryLevel extends InventoryQuantities {
  id: string;
  location: InventoryLocation;
}

export interface InventoryItem {
  id: string;
  product: { id: string; title: string };
  variant: {
    id: string;
    title: string;
    sku: string | null;
    barcode: string | null;
  };
  allow_oversell: boolean;
  quantities: InventoryQuantities;
  status: InventoryStatus;
  levels: InventoryLevel[];
  updated_at: string | null;
}

export interface InventorySummary {
  tracked_variants: number;
  available: number;
  reserved: number;
  sellable: number;
  incoming: number;
  low_stock: number;
  out_of_stock: number;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface InventoryListResponse {
  data: InventoryItem[];
  meta: PaginationMeta;
  summary: InventorySummary;
  locations: InventoryLocation[];
}

export interface InventoryAdjustmentPayload {
  available_quantity: number;
  reason?: string;
}
