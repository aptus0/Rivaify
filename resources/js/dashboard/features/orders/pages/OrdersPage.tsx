import { useEffect, useState, type FormEvent } from 'react';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { ApiError } from '../../../lib/api';
import {
  listOrders,
  type AdminOrderSummary,
  type FulfillmentStatus,
  type PaginationMeta,
  type PaymentStatus,
} from '../../commerce/api/adminCommerceApi';
import { formatDate, formatMoney } from '../../../utils/commerceFormat';
import { FulfillmentStatusBadge, PaymentStatusBadge } from '../components/OrderStatusBadge';

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

function messageFor(error: unknown): string {
  return error instanceof ApiError ? 'Siparişler yüklenemedi.' : 'Beklenmeyen bir hata oluştu.';
}

export function OrdersPage() {
  usePageTitle('Siparişler');
  const [draftQuery, setDraftQuery] = useState('');
  const [query, setQuery] = useState('');
  const [paymentStatus, setPaymentStatus] = useState('');
  const [fulfillmentStatus, setFulfillmentStatus] = useState('');
  const [orders, setOrders] = useState<AdminOrderSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_META);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);

    void listOrders({
      q: query || undefined,
      payment_status: paymentStatus || undefined,
      fulfillment_status: fulfillmentStatus || undefined,
      page: String(meta.current_page),
    })
      .then((response) => {
        if (!active) return;
        setOrders(response.data);
        setMeta(response.meta);
      })
      .catch((requestError: unknown) => {
        if (active) setError(messageFor(requestError));
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [fulfillmentStatus, meta.current_page, paymentStatus, query]);

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMeta((current) => ({ ...current, current_page: 1 }));
    setQuery(draftQuery.trim());
  }

  function changePage(page: number) {
    setMeta((current) => ({ ...current, current_page: page }));
  }

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-semibold text-dark">Siparişler</h2>
          <p className="text-sm text-muted">{meta.total} kayıt</p>
        </div>
        <Button fullWidth={false} disabled title="Manuel sipariş oluşturma yakında">
          <Plus size={16} />
          Sipariş Oluştur
        </Button>
      </div>

      <Card className="p-0">
        <form onSubmit={submitSearch} className="grid gap-3 border-b border-border p-4 lg:grid-cols-[minmax(0,1fr)_10rem_11rem_auto]">
          <label className="relative">
            <span className="sr-only">Sipariş, müşteri veya e-posta ara</span>
            <Search size={17} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input
              value={draftQuery}
              onChange={(event) => setDraftQuery(event.target.value)}
              placeholder="Sipariş, müşteri veya e-posta ara..."
              className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm text-dark outline-none focus:border-primary"
            />
          </label>
          <select
            value={paymentStatus}
            onChange={(event) => {
              setMeta((current) => ({ ...current, current_page: 1 }));
              setPaymentStatus(event.target.value);
            }}
            aria-label="Ödeme durumu"
            className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
          >
            <option value="">Tüm ödemeler</option>
            <option value="paid">Ödendi</option>
            <option value="pending">Bekliyor</option>
            <option value="failed">Başarısız</option>
            <option value="refunded">İade edildi</option>
          </select>
          <select
            value={fulfillmentStatus}
            onChange={(event) => {
              setMeta((current) => ({ ...current, current_page: 1 }));
              setFulfillmentStatus(event.target.value);
            }}
            aria-label="Teslimat durumu"
            className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
          >
            <option value="">Tüm teslimatlar</option>
            <option value="unfulfilled">Hazırlanıyor</option>
            <option value="partial">Kısmi gönderildi</option>
            <option value="fulfilled">Gönderildi</option>
            <option value="returned">İade edildi</option>
          </select>
          <Button fullWidth={false} type="submit">Ara</Button>
        </form>

        {error && <p className="border-b border-border px-4 py-3 text-sm text-red-600">{error}</p>}
        {loading ? (
          <div className="p-8 text-sm text-muted">Siparişler yükleniyor...</div>
        ) : orders.length === 0 ? (
          <EmptyState icon={Search} title="Sipariş bulunamadı" description="Filtrelerini değiştirerek tekrar dene." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-[760px] w-full text-left text-sm">
              <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-3">Sipariş</th>
                  <th className="px-4 py-3">Tarih</th>
                  <th className="px-4 py-3">Müşteri</th>
                  <th className="px-4 py-3 text-right">Toplam</th>
                  <th className="px-4 py-3">Ödeme</th>
                  <th className="px-4 py-3">Teslimat</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {orders.map((order) => (
                  <tr key={order.id} className="hover:bg-app-bg/70">
                    <td className="px-4 py-3 font-medium text-dark">
                      <Link to={`/dashboard/orders/${order.id}`} className="hover:text-primary-hover">
                        {order.number}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-muted">{formatDate(order.placed_at, { year: undefined })}</td>
                    <td className="px-4 py-3">
                      <p className="font-medium text-dark">{order.customer.name || 'Misafir müşteri'}</p>
                      <p className="text-xs text-muted">{order.customer.email}</p>
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-dark">{formatMoney(order.grand_total, order.currency)}</td>
                    <td className="px-4 py-3"><PaymentStatusBadge status={order.payment_status as PaymentStatus} /></td>
                    <td className="px-4 py-3"><FulfillmentStatusBadge status={order.fulfillment_status as FulfillmentStatus} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-border px-4 py-3">
            <p className="text-sm text-muted">Sayfa {meta.current_page} / {meta.last_page}</p>
            <div className="flex gap-2">
              <Button fullWidth={false} variant="secondary" disabled={meta.current_page === 1} onClick={() => changePage(meta.current_page - 1)} aria-label="Önceki sayfa">
                <ChevronLeft size={16} />
              </Button>
              <Button fullWidth={false} variant="secondary" disabled={meta.current_page === meta.last_page} onClick={() => changePage(meta.current_page + 1)} aria-label="Sonraki sayfa">
                <ChevronRight size={16} />
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}