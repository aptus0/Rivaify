import { apiRequest } from '../../../lib/api';

export type WorkspaceSearchResult = {
  id: string;
  type: 'product' | 'order' | 'customer';
  title: string;
  description: string;
  path: string;
};

export type WorkspaceNotification = {
  id: string;
  type: 'order' | 'inventory' | 'payment' | 'integration';
  title: string;
  description: string;
  path: string;
  tone: 'success' | 'warning' | 'danger';
  created_at: string | null;
};

export function searchWorkspace(query: string): Promise<{ data: WorkspaceSearchResult[] }> {
  return apiRequest(`/api/v1/search?q=${encodeURIComponent(query)}`);
}

export function getWorkspaceNotifications(): Promise<{ data: WorkspaceNotification[]; meta: { total: number } }> {
  return apiRequest('/api/v1/notifications');
}
