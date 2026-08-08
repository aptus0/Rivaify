import { Badge } from '../../../components/ui/Badge';
import type { FulfillmentStatus, PaymentStatus } from '../../commerce/api/adminCommerceApi';

const PAYMENT_LABELS: Record<PaymentStatus, string> = {
  pending: 'Bekliyor',
  authorized: 'Onaylandı',
  paid: 'Ödendi',
  partially_paid: 'Kısmi Ödendi',
  refunded: 'İade Edildi',
  partially_refunded: 'Kısmi İade',
  failed: 'Başarısız',
  voided: 'İptal',
};

const FULFILLMENT_LABELS: Record<FulfillmentStatus, string> = {
  unfulfilled: 'Hazırlanıyor',
  partial: 'Kısmi Gönderildi',
  fulfilled: 'Gönderildi',
  returned: 'İade Edildi',
};

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
  const tone = status === 'paid' ? 'success' : status === 'failed' ? 'warning' : 'neutral';

  return <Badge tone={tone}>{PAYMENT_LABELS[status]}</Badge>;
}

export function FulfillmentStatusBadge({ status }: { status: FulfillmentStatus }) {
  const tone = status === 'fulfilled' ? 'success' : status === 'returned' ? 'warning' : 'neutral';

  return <Badge tone={tone}>{FULFILLMENT_LABELS[status]}</Badge>;
}