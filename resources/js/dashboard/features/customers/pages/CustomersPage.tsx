import { useEffect, useState, type FormEvent } from 'react';
import { ChevronLeft, ChevronRight, Plus, Search, Users, X } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { ApiError } from '../../../lib/api';
import { createCustomer, listCustomers, type CustomerSummary, type PaginationMeta } from '../../commerce/api/adminCommerceApi';
import { formatDate, formatMoney } from '../../../utils/commerceFormat';

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

export function CustomersPage() {
  usePageTitle('Müşteriler');
  const { store } = useAuth();
  const [searchParams] = useSearchParams();
  const [draftQuery, setDraftQuery] = useState('');
  const [query, setQuery] = useState('');
  const [customers, setCustomers] = useState<CustomerSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_META);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(searchParams.get('create') === '1');
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ first_name: '', last_name: '', email: '', phone: '', accepts_marketing: false });

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listCustomers({ q: query || undefined, page: String(meta.current_page) })
      .then((response) => {
        if (!active) return;
        setCustomers(response.data);
        setMeta(response.meta);
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof ApiError ? 'Müşteriler yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [meta.current_page, query]);

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMeta((current) => ({ ...current, current_page: 1 }));
    setQuery(draftQuery.trim());
  }

  async function submitCustomer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setCreating(true); setError(null);
    try { const response = await createCustomer(form); setCustomers((current) => [response.data, ...current]); setMeta((current) => ({ ...current, total: current.total + 1 })); setForm({ first_name: '', last_name: '', email: '', phone: '', accepts_marketing: false }); setCreateOpen(false); }
    catch { setError('Müşteri oluşturulamadı. E-posta adresi kullanımda olabilir.'); }
    finally { setCreating(false); }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <div className="flex items-end justify-between gap-3">
        <div><h2 className="text-xl font-semibold text-dark">Müşteriler</h2><p className="text-sm text-muted">{meta.total} müşteri</p></div>
        <Button fullWidth={false} onClick={() => setCreateOpen(true)}><Plus size={16}/>Müşteri ekle</Button>
      </div>

      <Card className="p-0">
        <form onSubmit={submitSearch} className="border-b border-border p-4">
          <label className="relative block max-w-xl">
            <span className="sr-only">Müşteri, e-posta veya telefon ara</span>
            <Search size={17} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input
              value={draftQuery}
              onChange={(event) => setDraftQuery(event.target.value)}
              placeholder="Müşteri, e-posta veya telefon ara..."
              className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm text-dark outline-none focus:border-primary"
            />
          </label>
        </form>
        {error && <p className="border-b border-border px-4 py-3 text-sm text-red-600">{error}</p>}
        {loading ? (
          <div className="p-8 text-sm text-muted">Müşteriler yükleniyor...</div>
        ) : customers.length === 0 ? (
          <EmptyState icon={Users} title="Müşteri bulunamadı" description="Müşteriler sipariş verildikçe burada görünür." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-[700px] w-full text-left text-sm">
              <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted">
                <tr><th className="px-4 py-3">Müşteri</th><th className="px-4 py-3">E-posta</th><th className="px-4 py-3 text-right">Sipariş</th><th className="px-4 py-3 text-right">Harcama</th><th className="px-4 py-3">Son sipariş</th></tr>
              </thead>
              <tbody className="divide-y divide-border">
                {customers.map((customer) => (
                  <tr key={customer.id} className="hover:bg-app-bg/70">
                    <td className="px-4 py-3 font-medium text-dark"><Link to={`/customers/${customer.id}`} className="hover:text-primary-hover">{customer.name || customer.email}</Link></td>
                    <td className="px-4 py-3 text-muted">{customer.email}</td>
                    <td className="px-4 py-3 text-right text-dark">{customer.total_orders}</td>
                    <td className="px-4 py-3 text-right font-medium text-dark">{formatMoney(customer.total_spent, store?.default_currency ?? 'TRY')}</td>
                    <td className="px-4 py-3 text-muted">{formatDate(customer.last_order_at, { year: undefined })}</td>
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
              <Button fullWidth={false} variant="secondary" disabled={meta.current_page === 1} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page - 1 }))} aria-label="Önceki sayfa"><ChevronLeft size={16} /></Button>
              <Button fullWidth={false} variant="secondary" disabled={meta.current_page === meta.last_page} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page + 1 }))} aria-label="Sonraki sayfa"><ChevronRight size={16} /></Button>
            </div>
          </div>
        )}
      </Card>
      {createOpen && <div className="fixed inset-0 z-50 grid place-items-center bg-dark/40 p-4"><form onSubmit={submitCustomer} className="w-full max-w-lg rounded-2xl bg-card p-6 shadow-spectrum"><div className="flex items-center justify-between"><div><h3 className="text-lg font-semibold">Yeni müşteri</h3><p className="text-sm text-muted">Müşteri iletişim ve izin bilgileri.</p></div><button type="button" onClick={() => setCreateOpen(false)}><X size={20}/></button></div><div className="mt-6 grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Ad<input value={form.first_name} onChange={e => setForm({...form,first_name:e.target.value})} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"/></label><label className="text-sm font-medium">Soyad<input value={form.last_name} onChange={e => setForm({...form,last_name:e.target.value})} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"/></label><label className="text-sm font-medium sm:col-span-2">E-posta<input required type="email" value={form.email} onChange={e => setForm({...form,email:e.target.value})} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"/></label><label className="text-sm font-medium sm:col-span-2">Telefon<input value={form.phone} onChange={e => setForm({...form,phone:e.target.value})} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"/></label><label className="flex items-center gap-2 text-sm text-muted sm:col-span-2"><input type="checkbox" checked={form.accepts_marketing} onChange={e => setForm({...form,accepts_marketing:e.target.checked})} className="accent-primary"/>Pazarlama iletişimine izin verdi</label></div><div className="mt-6 flex justify-end gap-2"><Button type="button" variant="secondary" fullWidth={false} onClick={() => setCreateOpen(false)}>Vazgeç</Button><Button disabled={creating} fullWidth={false}>{creating?'Kaydediliyor...':'Müşteriyi kaydet'}</Button></div></form></div>}
    </div>
  );
}
