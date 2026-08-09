import { apiRequest } from '../../../lib/api';
import type {
  CategoryItem,
  CategoryOption,
  CategoryPayload,
  CollectionPayload,
  DataResponse,
  PaginatedResponse,
  ProductCollection,
  ProductPickerItem,
} from './types';

function queryString(values: Record<string, string | undefined>): string {
  const query = new URLSearchParams(
    Object.entries(values).filter((entry): entry is [string, string] => Boolean(entry[1])),
  );

  return query.size === 0 ? '' : `?${query.toString()}`;
}

export function listCategories(filters: { q?: string; status?: string; page?: string }): Promise<PaginatedResponse<CategoryItem>> {
  return apiRequest(`/api/v1/categories${queryString(filters)}`);
}

export function listCategoryOptions(): Promise<DataResponse<{ categories: CategoryOption[] }>> {
  return apiRequest('/api/v1/catalog/organization');
}

export function createCategory(payload: CategoryPayload): Promise<DataResponse<CategoryItem>> {
  return apiRequest('/api/v1/categories', { method: 'POST', body: payload });
}

export function updateCategory(id: string, payload: CategoryPayload): Promise<DataResponse<CategoryItem>> {
  return apiRequest(`/api/v1/categories/${id}`, { method: 'PATCH', body: payload });
}

export function deleteCategory(id: string): Promise<void> {
  return apiRequest(`/api/v1/categories/${id}`, { method: 'DELETE' });
}

export function listCollections(filters: { q?: string; status?: string; page?: string }): Promise<PaginatedResponse<ProductCollection>> {
  return apiRequest(`/api/v1/collections${queryString(filters)}`);
}

export function getCollection(id: string): Promise<DataResponse<ProductCollection>> {
  return apiRequest(`/api/v1/collections/${id}`);
}

export function createCollection(payload: CollectionPayload): Promise<DataResponse<ProductCollection>> {
  return apiRequest('/api/v1/collections', { method: 'POST', body: payload });
}

export function updateCollection(id: string, payload: CollectionPayload): Promise<DataResponse<ProductCollection>> {
  return apiRequest(`/api/v1/collections/${id}`, { method: 'PATCH', body: payload });
}

export function deleteCollection(id: string): Promise<void> {
  return apiRequest(`/api/v1/collections/${id}`, { method: 'DELETE' });
}

export function syncCollectionProducts(id: string, productIds: string[]): Promise<DataResponse<ProductCollection>> {
  return apiRequest(`/api/v1/collections/${id}/products`, { method: 'PUT', body: { product_ids: productIds } });
}

export function listProductPicker(q?: string): Promise<PaginatedResponse<ProductPickerItem>> {
  return apiRequest(`/api/v1/products${queryString({ q, per_page: '100' })}`);
}

