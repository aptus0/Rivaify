import { useEffect, useState } from 'react';
import { ArrowLeft, Mail, MapPin, ShoppingBag, UserRound } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Card } from '../../../components/ui/Card';
import { ApiError } from '../../../lib/api';
import { getCustomer, updateCustomer, type CustomerDetail } from '../../commerce/api/adminCommerceApi';
import { formatDate, formatMoney } from '../../../utils/commerceFormat';

export function CustomerDetailPage() {
  usePageTitle('Müşteri Detayı');
  const { store } = useAuth();
  const { customerId } = useParams();
  const [customer, setCustomer] = useState<CustomerDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  async function changeStatus(status: CustomerDetail['status']) {
    if (!customerId) return; setSaving(true);
    try { await updateCustomer(customerId, { status }); setCustomer((current) => current ? { ...current, status } : current); }
    catch { setError('Müşteri durumu güncellenemedi.'); } finally { setSaving(false); }
  }

  useEffect(() => {
    if (!customerId) return;
    let active = true;
    void getCustomer(customerId)
      .then((response) => {
        if (active) setCustomer(response.data);
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof ApiError ? 'Müşteri yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [customerId]);

  if (loading) return <p className="text-sm text-muted">Müşteri yükleniyor...</p>;
  if (!customer) return <p className="text-sm text-red-600">{error || 'Müşteri bulunamadı.'}</p>;

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <Link to="/customers" className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"><ArrowLeft size={16} /> Müşteriler</Link>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 className="text-xl font-semibold text-dark">{customer.name || customer.email}</h2><p className="mt-1 text-sm text-muted">{customer.email}</p></div>
        <label className="text-xs font-semibold text-muted">MÜŞTERİ DURUMU<select disabled={saving} value={customer.status} onChange={e => void changeStatus(e.target.value as CustomerDetail['status'])} className="ml-2 rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="active">Aktif</option><option value="disabled">Devre dışı</option><option value="blocked">Engelli</option></select></label>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Card className="p-4"><p className="text-sm text-muted">Toplam harcama</p><p className="mt-2 text-xl font-semibold text-dark">{formatMoney(customer.total_spent, store?.default_currency ?? 'TRY')}</p></Card>
        <Card className="p-4"><p className="text-sm text-muted">Sipariş</p><p className="mt-2 text-xl font-semibold text-dark">{customer.total_orders}</p></Card>
        <Card className="p-4"><p className="text-sm text-muted">Ortalama sipariş</p><p className="mt-2 text-xl font-semibold text-dark">{formatMoney(customer.average_order_value, store?.default_currency ?? 'TRY')}</p></Card>
      </div>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.8fr)]">
        <Card>
          <div className="mb-4 flex items-center gap-2"><ShoppingBag size={18} className="text-muted" /><h3 className="font-medium text-dark">Sipariş geçmişi</h3></div>
          {customer.orders.length === 0 ? <p className="text-sm text-muted">Henüz sipariş yok.</p> : (
            <div className="divide-y divide-border">
              {customer.orders.map((order) => (
                <Link key={order.id} to={`/orders/${order.id}`} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:text-primary-hover">
                  <div><p className="font-medium text-dark">{order.number}</p><p className="text-sm text-muted">{formatDate(order.placed_at)}</p></div>
                  <p className="font-medium text-dark">{formatMoney(order.grand_total, order.currency)}</p>
                </Link>
              ))}
            </div>
          )}
        </Card>

        <div className="space-y-5">
          <Card>
            <div className="mb-3 flex items-center gap-2"><UserRound size={18} className="text-muted" /><h3 className="font-medium text-dark">İletişim</h3></div>
            <p className="text-sm text-dark">{customer.name}</p>
            <p className="mt-1 flex items-center gap-2 text-sm text-muted"><Mail size={14} />{customer.email}</p>
            {customer.phone && <p className="mt-1 text-sm text-muted">{customer.phone}</p>}
          </Card>
          <Card>
            <div className="mb-3 flex items-center gap-2"><MapPin size={18} className="text-muted" /><h3 className="font-medium text-dark">Adresler</h3></div>
            <div className="space-y-4">
              {customer.addresses.map((address) => (
                <div key={address.id} className="text-sm">
                  <p className="font-medium text-dark">{address.type === 'shipping' ? 'Teslimat' : 'Fatura'}{address.is_default ? ' · Varsayılan' : ''}</p>
                  <p className="mt-1 text-muted">{[address.address_line_1, address.address_line_2, address.district, address.province, address.postal_code].filter(Boolean).join(', ')}</p>
                </div>
              ))}
              {customer.addresses.length === 0 && <p className="text-sm text-muted">Kayıtlı adres yok.</p>}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
