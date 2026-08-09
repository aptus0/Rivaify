import { useEffect, useState, type FormEvent } from 'react';
import { CalendarClock, Percent, Plus, Search, Tag, TicketCheck, Trash2, X } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { Pagination } from '../../../components/ui/Pagination';
import { SearchInput } from '../../../components/ui/SearchInput';
import { ApiError } from '../../../lib/api';
import {
  createDiscount,
  deleteDiscount,
  listDiscounts,
  updateDiscount,
  type Discount,
  type DiscountPayload,
  type DiscountStatus,
  type DiscountType,
} from '../api/discountsApi';

type DiscountFormState = {
  name: string;
  code: string;
  type: DiscountType;
  value: string;
  status: DiscountStatus;
  minimumPurchase: string;
  usageLimit: string;
  startsAt: string;
  endsAt: string;
};

const EMPTY_FORM: DiscountFormState = {
  name: '',
  code: '',
  type: 'percentage',
  value: '10',
  status: 'active',
  minimumPurchase: '',
  usageLimit: '',
  startsAt: '',
  endsAt: '',
};

const EMPTY_SUMMARY = { all: 0, active: 0, inactive: 0, total_usage: 0 };

function toLocalDateTime(value: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function toPayload(form: DiscountFormState): DiscountPayload {
  return {
    name: form.name.trim(),
    code: form.code.trim() ? form.code.trim().toUpperCase() : null,
    type: form.type,
    value: form.type === 'free_shipping' ? '0' : form.value,
    status: form.status,
    minimum_purchase: form.minimumPurchase ? form.minimumPurchase : null,
    usage_limit: form.usageLimit ? Number(form.usageLimit) : null,
    starts_at: form.startsAt ? new Date(form.startsAt).toISOString() : null,
    ends_at: form.endsAt ? new Date(form.endsAt).toISOString() : null,
  };
}

function formFromDiscount(discount: Discount): DiscountFormState {
  return {
    name: discount.name,
    code: discount.code ?? '',
    type: discount.type,
    value: discount.value,
    status: discount.status,
    minimumPurchase: discount.minimum_purchase ?? '',
    usageLimit: discount.usage_limit?.toString() ?? '',
    startsAt: toLocalDateTime(discount.starts_at),
    endsAt: toLocalDateTime(discount.ends_at),
  };
}

function formatBenefit(discount: Discount, currency: string): string {
  if (discount.type === 'free_shipping') return 'Ücretsiz kargo';
  if (discount.type === 'percentage') return `%${Number(discount.value).toLocaleString('tr-TR')}`;

  return new Intl.NumberFormat('tr-TR', { style: 'currency', currency }).format(Number(discount.value));
}

function scheduleText(discount: Discount): string {
  const formatter = new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'short', year: 'numeric' });
  if (discount.starts_at && discount.ends_at) return `${formatter.format(new Date(discount.starts_at))} – ${formatter.format(new Date(discount.ends_at))}`;
  if (discount.starts_at) return `${formatter.format(new Date(discount.starts_at))} tarihinde başlar`;
  if (discount.ends_at) return `${formatter.format(new Date(discount.ends_at))} tarihine kadar`;

  return 'Süresiz';
}

function availability(discount: Discount): { label: string; tone: 'success' | 'warning' | 'neutral' } {
  const states: Record<Discount['availability'], { label: string; tone: 'success' | 'warning' | 'neutral' }> = {
    active: { label: 'Aktif', tone: 'success' },
    inactive: { label: 'Pasif', tone: 'neutral' },
    scheduled: { label: 'Planlandı', tone: 'warning' },
    expired: { label: 'Süresi doldu', tone: 'neutral' },
    usage_limit_reached: { label: 'Limit doldu', tone: 'warning' },
  };

  return states[discount.availability];
}

export function DiscountsPage() {
  usePageTitle('İndirimler');
  const [searchParams] = useSearchParams();
  const [discounts, setDiscounts] = useState<Discount[]>([]);
  const [summary, setSummary] = useState(EMPTY_SUMMARY);
  const [currency, setCurrency] = useState('TRY');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<DiscountStatus | ''>('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Discount | null | undefined>(searchParams.get('create') === '1' ? null : undefined);

  useEffect(() => {
    const timeout = window.setTimeout(() => { setSearch(searchInput.trim()); setPage(1); }, 300);
    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listDiscounts({ q: search || undefined, status: status || undefined, page: String(page) })
      .then((response) => {
        if (!active) return;
        setDiscounts(response.data);
        setCurrency(response.currency);
        setSummary(response.summary);
        setLastPage(response.meta.last_page);
      })
      .catch(() => { if (active) setError('İndirimler yüklenemedi. Lütfen tekrar deneyin.'); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [page, search, status]);

  async function refresh() {
    const response = await listDiscounts({ q: search || undefined, status: status || undefined, page: String(page) });
    setDiscounts(response.data);
    setCurrency(response.currency);
    setSummary(response.summary);
    setLastPage(response.meta.last_page);
  }

  async function remove(discount: Discount) {
    if (!window.confirm(`“${discount.name}” indirimi kalıcı olarak silinsin mi?`)) return;
    try {
      await deleteDiscount(discount.id);
      await refresh();
    } catch {
      setError('İndirim silinemedi. Kullanılmış indirimleri pasif duruma almayı deneyin.');
    }
  }

  return (
    <div className="mx-auto max-w-7xl space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="mb-1 text-xs font-semibold uppercase tracking-[.16em] text-primary">Satış araçları</p>
          <h2 className="text-2xl font-semibold text-dark">İndirimler</h2>
          <p className="mt-1 text-sm text-muted">Kodlu veya otomatik indirimlerin kullanımını ve geçerlilik tarihlerini yönetin.</p>
        </div>
        <Button fullWidth={false} onClick={() => setEditing(null)}><Plus size={16} />İndirim oluştur</Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric icon={Tag} label="Toplam" value={summary.all} />
        <Metric icon={TicketCheck} label="Aktif" value={summary.active} tone="text-emerald-600" />
        <Metric icon={CalendarClock} label="Pasif" value={summary.inactive} />
        <Metric icon={Percent} label="Toplam kullanım" value={summary.total_usage} tone="text-primary" />
      </div>

      <Card className="p-0">
        <div className="flex flex-col gap-3 border-b border-border p-4 md:flex-row md:items-center md:justify-between">
          <SearchInput value={searchInput} onChange={setSearchInput} placeholder="İndirim adı veya kodu ara..." className="w-full md:max-w-md" />
          <div className="flex gap-2">
            {([['', 'Tümü'], ['active', 'Aktif'], ['inactive', 'Pasif']] as const).map(([value, label]) => (
              <button key={label} onClick={() => { setStatus(value); setPage(1); }} className={`rounded-md border px-3 py-2 text-sm font-medium ${status === value ? 'border-primary bg-surface-orange text-primary-hover' : 'border-border text-muted hover:text-dark'}`}>{label}</button>
            ))}
          </div>
        </div>
        {error && <p className="border-b border-border bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
        {loading ? <div className="space-y-3 p-4">{Array.from({ length: 5 }, (_, index) => <div key={index} className="h-16 animate-pulse rounded-lg bg-app-bg" />)}</div> : discounts.length === 0 ? (
          <EmptyState icon={search ? Search : Tag} title={search || status ? 'Eşleşen indirim bulunamadı.' : 'Henüz indirim oluşturulmadı.'} description={search || status ? 'Arama veya durum filtresini değiştirerek tekrar deneyin.' : 'İlk kampanyanızı oluşturup ödeme adımında kullanıma açın.'} action={!search && !status ? <Button fullWidth={false} onClick={() => setEditing(null)}><Plus size={16} />İlk indirimi oluştur</Button> : undefined} />
        ) : (
          <div className="divide-y divide-border">
            {discounts.map((discount) => (
              <div key={discount.id} className="grid gap-3 p-4 hover:bg-app-bg/50 md:grid-cols-[minmax(0,1.5fr)_minmax(120px,.6fr)_minmax(180px,.8fr)_auto] md:items-center">
                <button onClick={() => setEditing(discount)} className="min-w-0 text-left">
                  <span className="block truncate font-semibold text-dark hover:text-primary-hover">{discount.name}</span>
                  <span className="mt-1 block text-xs text-muted">{discount.code ? <code className="rounded bg-app-bg px-1.5 py-0.5 font-semibold text-dark">{discount.code}</code> : 'Kod tanımlanmamış'} · {discount.usage_count}{discount.usage_limit ? ` / ${discount.usage_limit}` : ''} kullanım</span>
                </button>
                <p className="font-semibold text-dark">{formatBenefit(discount, currency)}</p>
                <div><Badge tone={availability(discount).tone}>{availability(discount).label}</Badge><p className="mt-1 text-xs text-muted">{scheduleText(discount)}</p></div>
                <div className="flex justify-end gap-1"><Button variant="ghost" fullWidth={false} onClick={() => setEditing(discount)}>Düzenle</Button><button onClick={() => void remove(discount)} aria-label={`${discount.name} indirimini sil`} className="rounded-md p-2 text-muted hover:bg-red-50 hover:text-red-600"><Trash2 size={16} /></button></div>
              </div>
            ))}
          </div>
        )}
        <Pagination currentPage={page} lastPage={lastPage} onChange={setPage} />
      </Card>

      {editing !== undefined && <DiscountEditor discount={editing} currency={currency} onClose={() => setEditing(undefined)} onSaved={async () => { setEditing(undefined); await refresh(); }} />}
    </div>
  );
}

function Metric({ icon: Icon, label, value, tone = 'text-dark' }: { icon: typeof Tag; label: string; value: number; tone?: string }) {
  return <Card><div className="flex items-center justify-between"><p className="text-sm text-muted">{label}</p><Icon size={18} className={tone} /></div><p className={`mt-3 text-2xl font-semibold ${tone}`}>{value.toLocaleString('tr-TR')}</p></Card>;
}

function DiscountEditor({ discount, currency, onClose, onSaved }: { discount: Discount | null; currency: string; onClose: () => void; onSaved: () => Promise<void> }) {
  const [form, setForm] = useState<DiscountFormState>(() => discount ? formFromDiscount(discount) : EMPTY_FORM);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  function change<K extends keyof DiscountFormState>(key: K, value: DiscountFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setFieldErrors({});
    try {
      if (discount) await updateDiscount(discount.id, toPayload(form));
      else await createDiscount(toPayload(form));
      await onSaved();
    } catch (requestError) {
      if (requestError instanceof ApiError) setFieldErrors(requestError.validationErrors ?? {});
      setError('İndirim kaydedilemedi. Alanları kontrol edip tekrar deneyin.');
    } finally {
      setBusy(false);
    }
  }

  const inputClass = 'mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary focus:ring-2 focus:ring-primary/10';
  const fieldError = (key: string) => fieldErrors[key]?.[0];

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-dark/45 p-0 sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="discount-editor-title">
      <form onSubmit={(event) => void submit(event)} className="max-h-[95vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-card shadow-2xl sm:rounded-2xl">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card px-5 py-4"><div><h3 id="discount-editor-title" className="font-semibold text-dark">{discount ? 'İndirimi düzenle' : 'Yeni indirim'}</h3><p className="text-xs text-muted">Kural ödeme adımında sunucu tarafında doğrulanır.</p></div><button type="button" onClick={onClose} className="rounded-md p-2 text-muted hover:bg-app-bg"><X size={19} /></button></div>
        <div className="grid gap-5 p-5 md:grid-cols-2">
          {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 md:col-span-2">{error}</p>}
          <label className="text-sm font-medium text-dark md:col-span-2">İndirim adı<input required value={form.name} onChange={(event) => change('name', event.target.value)} placeholder="Örn. İlk sipariş indirimi" className={inputClass} />{fieldError('name') && <span className="mt-1 block text-xs text-red-600">{fieldError('name')}</span>}</label>
          <label className="text-sm font-medium text-dark">İndirim kodu<input required value={form.code} onChange={(event) => change('code', event.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ''))} placeholder="HOSGELDIN10" className={inputClass} />{fieldError('code') && <span className="mt-1 block text-xs text-red-600">{fieldError('code')}</span>}</label>
          <label className="text-sm font-medium text-dark">Durum<select value={form.status} onChange={(event) => change('status', event.target.value as DiscountStatus)} className={inputClass}><option value="active">Aktif</option><option value="inactive">Pasif</option></select></label>
          <label className="text-sm font-medium text-dark">İndirim türü<select value={form.type} onChange={(event) => { const type = event.target.value as DiscountType; setForm((current) => ({ ...current, type, value: type === 'free_shipping' ? '0' : current.value === '0' ? '10' : current.value })); }} className={inputClass}><option value="percentage">Yüzde</option><option value="fixed_amount">Sabit tutar</option><option value="free_shipping">Ücretsiz kargo</option></select></label>
          <label className="text-sm font-medium text-dark">Değer{form.type === 'percentage' ? ' (%)' : form.type === 'fixed_amount' ? ` (${currency})` : ''}<input required type="number" min="0" max={form.type === 'percentage' ? 100 : undefined} step="0.01" disabled={form.type === 'free_shipping'} value={form.type === 'free_shipping' ? '0' : form.value} onChange={(event) => change('value', event.target.value)} className={inputClass} />{fieldError('value') && <span className="mt-1 block text-xs text-red-600">{fieldError('value')}</span>}</label>
          <label className="text-sm font-medium text-dark">Minimum sepet ({currency})<input type="number" min="0" step="0.01" value={form.minimumPurchase} onChange={(event) => change('minimumPurchase', event.target.value)} placeholder="Koşul yok" className={inputClass} /></label>
          <label className="text-sm font-medium text-dark">Toplam kullanım limiti<input type="number" min="1" step="1" value={form.usageLimit} onChange={(event) => change('usageLimit', event.target.value)} placeholder="Limitsiz" className={inputClass} /></label>
          <label className="text-sm font-medium text-dark">Başlangıç<input type="datetime-local" value={form.startsAt} onChange={(event) => change('startsAt', event.target.value)} className={inputClass} /></label>
          <label className="text-sm font-medium text-dark">Bitiş<input type="datetime-local" min={form.startsAt || undefined} value={form.endsAt} onChange={(event) => change('endsAt', event.target.value)} className={inputClass} />{fieldError('ends_at') && <span className="mt-1 block text-xs text-red-600">{fieldError('ends_at')}</span>}</label>
        </div>
        <div className="sticky bottom-0 flex justify-end gap-2 border-t border-border bg-card px-5 py-4"><Button type="button" variant="secondary" fullWidth={false} onClick={onClose}>Vazgeç</Button><Button type="submit" fullWidth={false} disabled={busy}>{busy ? 'Kaydediliyor…' : discount ? 'Değişiklikleri kaydet' : 'İndirimi oluştur'}</Button></div>
      </form>
    </div>
  );
}
