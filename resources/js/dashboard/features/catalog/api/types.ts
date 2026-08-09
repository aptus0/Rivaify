export type CatalogStatus = 'active' | 'draft' | 'archived';

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CategoryItem {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  status: CatalogStatus;
  position: number;
  parent: { id: string; name: string } | null;
  product_count: number;
  children_count: number;
  updated_at: string | null;
}

export interface CategoryOption {
  id: string;
  name: string;
  parent_id: string | null;
  status: CatalogStatus;
}

export interface CollectionProduct {
  id: string;
  title: string;
  slug: string;
  status: CatalogStatus;
  featured_media_url: string | null;
  position: number;
}

export interface ProductCollection {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  status: CatalogStatus;
  position: number;
  product_count: number;
  updated_at: string | null;
  products?: CollectionProduct[];
}

export interface ProductPickerItem {
  id: string;
  title: string;
  slug: string;
  status: CatalogStatus;
  featured_media: { url: string } | null;
}

export interface CategoryPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  status: CatalogStatus;
  position: number;
  parent_id?: string | null;
}

export interface CollectionPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  status: CatalogStatus;
  position: number;
  product_ids: string[];
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface DataResponse<T> {
  data: T;
}

