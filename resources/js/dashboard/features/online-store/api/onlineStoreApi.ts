import { apiRequest } from '../../../lib/api';
import type { BuilderDocument, BuilderDocumentRecord, OnlineStoreOverview, StoreTheme, ThemePackageInspection, VersionHistoryItem } from '../types';
import type { StorefrontRuntime } from '../../../../storefront/types';

export function getOnlineStore(): Promise<{ data: OnlineStoreOverview }> {
  return apiRequest('/api/v1/online-store');
}

export function getThemeLibrary(): Promise<{ data: { themes: StoreTheme[] } }> {
  return apiRequest('/api/v1/online-store/themes');
}

export function getThemeEditor(themeId: string): Promise<{ data: { theme: StoreTheme; documents: BuilderDocumentRecord[]; version_history: VersionHistoryItem[] } }> {
  return apiRequest(`/api/v1/online-store/themes/${themeId}/editor`);
}

export function getThemePreviewRuntime(themeId: string): Promise<{ data: StorefrontRuntime }> {
  return apiRequest(`/api/v1/online-store/themes/${themeId}/preview-runtime`);
}

export function saveBuilderDocument(documentId: string, revision: number, document: BuilderDocument): Promise<{ data: BuilderDocumentRecord & { saved_at: string } }> {
  return apiRequest(`/api/v1/online-store/documents/${documentId}`, {
    method: 'PATCH',
    body: { revision, document },
  });
}

export function publishTheme(themeId: string): Promise<{ data: { version: { id: string; number: number; status: string; published_at: string | null }; theme: StoreTheme } }> {
  return apiRequest(`/api/v1/online-store/themes/${themeId}/publish`, { method: 'POST' });
}

export function uploadThemePackage(file: File): Promise<{ data: ThemePackageInspection }> {
  const body = new FormData();
  body.append('theme', file);

  return apiRequest('/api/v1/online-store/themes/upload', {
    method: 'POST',
    body,
  });
}

export function installThemePackage(packageId: string): Promise<{ data: { theme: StoreTheme; package: ThemePackageInspection } }> {
  return apiRequest(`/api/v1/online-store/theme-packages/${packageId}/install`, { method: 'POST' });
}
