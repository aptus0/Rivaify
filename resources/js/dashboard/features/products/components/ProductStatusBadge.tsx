import { Badge } from '../../../components/ui/Badge';
import type { ProductStatus } from '../api/types';

const LABELS: Record<ProductStatus, string> = {
  active: 'Aktif',
  draft: 'Taslak',
  archived: 'Arşiv',
};

export function ProductStatusBadge({ status }: { status: ProductStatus }) {
  const tone = status === 'active' ? 'success' : status === 'draft' ? 'warning' : 'neutral';

  return <Badge tone={tone}>{LABELS[status]}</Badge>;
}