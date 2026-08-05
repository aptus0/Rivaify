import { apiRequest } from '../../../lib/api';
import type { CurrentStoreSummary } from '../../../types';

export function createStore(name: string): Promise<{ data: CurrentStoreSummary }> {
  return apiRequest('/api/stores', { method: 'POST', body: { name } });
}
