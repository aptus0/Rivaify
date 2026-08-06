import { apiRequest } from '../../../lib/api';
import type {
  CatalogOrganization,
  ProductDetail,
  ProductMedia,
  ProductPayload,
  ProductsResponse,
} from './types';

interface DataResponse<T> {
  data: T;
}

function queryString(values: Record<string, string | undefined>): string {
  const query = new URLSearchParams(
    Object.entries(values).filter((entry): entry is [string, string] => entry[1] !== undefined && entry[1] !== ''),
  );

  return query.size === 0 ? '' : `?${query.toString()}`;
}

export function listProducts(filters: {
  q?: string;
  status?: string;
  category_id?: string;
  brand_id?: string;
  product_type?: string;
  inventory_status?: string;
  created_from?: string;
  created_to?: string;
  updated_from?: string;
  updated_to?: string;
  page?: string;
}): Promise<ProductsResponse> {
  return apiRequest(`/api/v1/products${queryString(filters)}`);
}

export function getProduct(productId: string): Promise<DataResponse<ProductDetail>> {
  return apiRequest(`/api/v1/products/${productId}`);
}

export function createProduct(payload: ProductPayload): Promise<DataResponse<ProductDetail>> {
  return apiRequest('/api/v1/products', { method: 'POST', body: payload });
}

export function updateProduct(productId: string, payload: ProductPayload): Promise<DataResponse<ProductDetail>> {
  return apiRequest(`/api/v1/products/${productId}`, { method: 'PATCH', body: payload });
}

export function duplicateProduct(productId: string): Promise<DataResponse<ProductDetail>> {
  return apiRequest(`/api/v1/products/${productId}/duplicate`, { method: 'POST' });
}

export function bulkUpdateProducts(payload: {
  product_ids: string[];
  action: 'activate' | 'draft' | 'archive' | 'delete' | 'change_category';
  category_id?: string;
}): Promise<DataResponse<{ updated_count: number }>> {
  return apiRequest('/api/v1/products/bulk', { method: 'POST', body: payload });
}

export function getCatalogOrganization(): Promise<DataResponse<CatalogOrganization>> {
  return apiRequest('/api/v1/catalog/organization');
}

export function createQuickCategory(payload: { name: string; parent_id?: string | null }): Promise<DataResponse<CatalogOrganization['categories'][number]>> {
  return apiRequest('/api/v1/catalog/categories', { method: 'POST', body: payload });
}

export function createQuickBrand(payload: { name: string }): Promise<DataResponse<CatalogOrganization['brands'][number]>> {
  return apiRequest('/api/v1/catalog/brands', { method: 'POST', body: payload });
}

export function uploadProductMedia(productId: string, file: File, altText?: string): Promise<DataResponse<ProductMedia>> {
  const form = new FormData();
  form.append('file', file);
  if (altText) form.append('alt_text', altText);

  return apiRequest(`/api/v1/products/${productId}/media`, { method: 'POST', body: form });
}

export function updateProductMedia(
  productId: string,
  mediaId: string,
  payload: { alt_text?: string | null; is_featured: boolean },
): Promise<DataResponse<ProductMedia>> {
  return apiRequest(`/api/v1/products/${productId}/media/${mediaId}`, { method: 'PATCH', body: payload });
}

export function reorderProductMedia(productId: string, mediaIds: string[]): Promise<DataResponse<ProductDetail>> {
  return apiRequest(`/api/v1/products/${productId}/media/reorder`, { method: 'POST', body: { media_ids: mediaIds } });
}

export function deleteProductMedia(productId: string, mediaId: string): Promise<void> {
  return apiRequest(`/api/v1/products/${productId}/media/${mediaId}`, { method: 'DELETE' });
}