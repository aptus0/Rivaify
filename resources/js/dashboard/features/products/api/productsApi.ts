import { ApiError, apiRequest } from '../../../lib/api';
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

export type ProductListFilters = {
  q?: string;
  status?: string;
  category_id?: string;
  brand_id?: string;
  product_type?: string;
  inventory_status?: string;
  page?: string;
};

export interface ProductCsvError {
  row: number;
  field: string;
  message: string;
  handle: string | null;
}

export interface ProductCsvResult {
  mode: 'preview' | 'commit';
  file_name: string;
  row_count: number;
  product_count: number;
  will_create: number;
  will_update: number;
  created: number;
  updated: number;
  failed: number;
  can_import: boolean;
  error_count: number;
  errors_truncated: boolean;
  errors: ProductCsvError[];
}

function queryString(values: Record<string, string | undefined>): string {
  const query = new URLSearchParams(
    Object.entries(values).filter((entry): entry is [string, string] => entry[1] !== undefined && entry[1] !== ''),
  );

  return query.size === 0 ? '' : `?${query.toString()}`;
}

export function listProducts(filters: ProductListFilters): Promise<ProductsResponse> {
  return apiRequest(`/api/v1/products${queryString(filters)}`);
}

function productCsvForm(file: File, mode: ProductCsvResult['mode']): FormData {
  const form = new FormData();
  form.append('file', file);
  form.append('mode', mode);

  return form;
}

export function previewProductCsv(file: File): Promise<DataResponse<ProductCsvResult>> {
  return apiRequest('/api/v1/products/import', { method: 'POST', body: productCsvForm(file, 'preview') });
}

export function importProductCsv(file: File): Promise<DataResponse<ProductCsvResult>> {
  return apiRequest('/api/v1/products/import', { method: 'POST', body: productCsvForm(file, 'commit') });
}

export async function exportProductsCsv(filters: Omit<ProductListFilters, 'page'>): Promise<void> {
  const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? '';
  const response = await fetch(`${apiBaseUrl}/api/v1/products/export${queryString(filters)}`, {
    credentials: 'include',
    headers: { Accept: 'text/csv' },
  });
  if (!response.ok) {
    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json') ? await response.json() : undefined;
    throw new ApiError(response.status, payload);
  }

  const blob = await response.blob();
  const disposition = response.headers.get('content-disposition') ?? '';
  const match = disposition.match(/filename="?([^";]+)"?/i);
  const filename = match?.[1] ?? 'urunler.csv';
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
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
