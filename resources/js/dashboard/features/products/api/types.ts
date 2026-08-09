export type ProductStatus = 'active' | 'draft' | 'archived';
export type ProductType = 'physical' | 'digital' | 'service';
export type InventoryStatus = 'in_stock' | 'low_stock' | 'out_of_stock' | 'not_tracked';

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CatalogReference {
  id: string;
  name: string;
}

export interface ProductMedia {
  id: string;
  media_type: 'image' | 'video';
  url: string;
  original_filename: string;
  mime_type: string;
  size_bytes: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  position: number;
  is_featured: boolean;
}

export interface InventoryLevelDraft {
  location_id: string;
  location_name?: string;
  available_quantity: number;
  reserved?: number;
  incoming?: number;
  sellable?: number;
}

export interface ProductVariant {
  id: string;
  title: string;
  sku: string | null;
  barcode: string | null;
  price: string;
  compare_at_price: string | null;
  cost_price: string | null;
  profit: string | null;
  margin_percent: string | null;
  weight: string | null;
  weight_unit: 'g' | 'kg';
  requires_shipping: boolean;
  is_taxable: boolean;
  status: ProductStatus;
  inventory: {
    is_tracked: boolean;
    allow_oversell: boolean;
    available: number;
    reserved: number;
    incoming: number;
    sellable: number;
    levels: Array<{
      location_id: string;
      location_name: string;
      available: number;
      reserved: number;
      incoming: number;
      sellable: number;
    }>;
  };
}

export interface ProductSummary {
  id: string;
  title: string;
  slug: string;
  status: ProductStatus;
  product_type: ProductType;
  variant_count: number;
  featured_media: ProductMedia | null;
  inventory: {
    is_tracked: boolean;
    sellable: number;
    status: InventoryStatus;
  };
  category: CatalogReference | null;
  brand: CatalogReference | null;
  sales_channels: Array<{ key: string; label: string; enabled: boolean; status?: string; detail?: string }>;
  updated_at: string | null;
}

export interface ProductDetail extends ProductSummary {
  description: string | null;
  vendor: string | null;
  is_taxable: boolean;
  requires_shipping: boolean;
  package: {
    width: string | null;
    height: string | null;
    length: string | null;
    dimension_unit: 'cm' | 'in';
  };
  seo: {
    meta_title: string | null;
    meta_description: string | null;
    slug: string;
  };
  tags: string[];
  media: ProductMedia[];
  options: Array<{
    id: string;
    name: string;
    values: Array<{ id: string; value: string; position: number }>;
  }>;
  variants: ProductVariant[];
}

export interface ProductOptionDraft {
  name: string;
  values: string[];
}

export interface ProductVariantDraft {
  title: string;
  price: string;
  compare_at_price?: string | null;
  cost_price?: string | null;
  sku?: string | null;
  barcode?: string | null;
  weight?: string | null;
  weight_unit: 'g' | 'kg';
  requires_shipping: boolean;
  is_taxable: boolean;
  status: ProductStatus;
  track_inventory: boolean;
  allow_oversell?: boolean;
  inventory: Array<{ location_id: string; available_quantity: number }>;
}

export interface ProductPayload {
  title: string;
  description?: string | null;
  slug?: string | null;
  category_id?: string | null;
  brand_id?: string | null;
  product_type: ProductType;
  status: ProductStatus;
  vendor?: string | null;
  is_taxable: boolean;
  requires_shipping: boolean;
  meta_title?: string | null;
  meta_description?: string | null;
  package?: {
    width?: string | null;
    height?: string | null;
    length?: string | null;
    dimension_unit?: 'cm' | 'in';
  };
  tags: string[];
  options: ProductOptionDraft[];
  variants: ProductVariantDraft[];
}

export interface CatalogOrganization {
  categories: Array<{ id: string; name: string; parent_id: string | null; status: string }>;
  brands: Array<{ id: string; name: string; status: string }>;
  locations: Array<{ id: string; name: string; code: string | null }>;
}

export interface ProductSummaryCounts {
  all: number;
  active: number;
  draft: number;
  archived: number;
  out_of_stock: number;
  low_stock: number;
}

export interface ProductsResponse {
  data: ProductSummary[];
  meta: PaginationMeta;
  summary: ProductSummaryCounts;
}
