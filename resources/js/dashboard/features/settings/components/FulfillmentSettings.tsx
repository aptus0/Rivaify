import { useEffect, useState, type FormEvent } from 'react';
import { Check, CircleAlert, Pencil, Plus, ReceiptText, Save, Trash2, Truck, X } from 'lucide-react';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ApiError } from '../../../lib/api';
import {
  createShippingMethod,
  createTaxRate,
  deleteShippingMethod,
  deleteTaxRate,
  getShippingMethods,
  getTaxRates,
  updateShippingMethod,
  updateTaxRate,
  type ShippingMethodPayload,
  type ShippingMethodSettings,
  type ShippingMethodsResponse,
  type TaxRatePayload,
  type TaxRateSettings,
  type TaxRatesResponse,
} from '../api/settingsApi';

const INPUT_CLASS = 'mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 disabled:cursor-not-allowed disabled:bg-app-bg disabled:text-muted';

interface FulfillmentSettingsProps {
  canManage: boolean;
  countryCode: string;
  currency: string;
}

interface ShippingEditor {
  id: string | null;
  values: ShippingMethodPayload;
}

interface TaxEditor {
  id: string | null;
  values: TaxRatePayload;
}

export function FulfillmentSettings({ canManage, countryCode, currency }: FulfillmentSettingsProps) {
  const [shipping, setShipping] = useState<ShippingMethodsResponse | null>(null);
  const [taxes, setTaxes] = useState<TaxRatesResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<'shipping' | 'tax' | null>(null);
  const [actionId, setActionId] = useState<string | null>(null);
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [shippingEditor, setShippingEditor] = useState<ShippingEditor>(() => emptyShippingEditor());
  const [taxEditor, setTaxEditor] = useState<TaxEditor>(() => emptyTaxEditor('TR'));

  async function reload() {
    const [shippingResponse, taxResponse] = await Promise.all([getShippingMethods(), getTaxRates()]);
    setShipping(shippingResponse);
    setTaxes(taxResponse);
    return { shippingResponse, taxResponse };
  }

  useEffect(() => {
    let active = true;
    void Promise.all([getShippingMethods(), getTaxRates()])
      .then(([shippingResponse, taxResponse]) => {
        if (!active) return;
        setShipping(shippingResponse);
        setTaxes(taxResponse);
        setTaxEditor((current) => current.id === null && current.values.name === '' ? emptyTaxEditor(taxResponse.default_country_code) : current);
      })
      .catch(() => { if (active) setMessage({ tone: 'error', text: 'Kargo ve vergi ayarları yüklenemedi.' }); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [countryCode]);

  async function saveShipping(event: FormEvent) {
    event.preventDefault();
    setSaving('shipping');
    clearFeedback();
    try {
      if (shippingEditor.id) await updateShippingMethod(shippingEditor.id, shippingEditor.values);
      else await createShippingMethod(shippingEditor.values);
      await reload();
      setShippingEditor(emptyShippingEditor());
      setMessage({ tone: 'success', text: shippingEditor.id ? 'Kargo yöntemi güncellendi.' : 'Kargo yöntemi oluşturuldu.' });
    } catch (requestError) {
      handleRequestError(requestError, 'Kargo yöntemi kaydedilemedi.');
    } finally {
      setSaving(null);
    }
  }

  async function saveTax(event: FormEvent) {
    event.preventDefault();
    setSaving('tax');
    clearFeedback();
    try {
      if (taxEditor.id) await updateTaxRate(taxEditor.id, taxEditor.values);
      else await createTaxRate(taxEditor.values);
      const { taxResponse } = await reload();
      setTaxEditor(emptyTaxEditor(taxResponse.default_country_code));
      setMessage({ tone: 'success', text: taxEditor.id ? 'Vergi oranı güncellendi.' : 'Vergi oranı oluşturuldu.' });
    } catch (requestError) {
      handleRequestError(requestError, 'Vergi oranı kaydedilemedi.');
    } finally {
      setSaving(null);
    }
  }

  async function removeShipping(method: ShippingMethodSettings) {
    if (!window.confirm(`${method.name} kargo yöntemi silinsin mi?`)) return;
    setActionId(method.id);
    clearFeedback();
    try {
      await deleteShippingMethod(method.id);
      await reload();
      if (shippingEditor.id === method.id) setShippingEditor(emptyShippingEditor());
      setMessage({ tone: 'success', text: 'Kargo yöntemi silindi.' });
    } catch (requestError) {
      handleRequestError(requestError, 'Kargo yöntemi silinemedi.');
    } finally {
      setActionId(null);
    }
  }

  async function removeTax(rate: TaxRateSettings) {
    if (!window.confirm(`${rate.name} vergi oranı silinsin mi?`)) return;
    setActionId(rate.id);
    clearFeedback();
    try {
      await deleteTaxRate(rate.id);
      const { taxResponse } = await reload();
      if (taxEditor.id === rate.id) setTaxEditor(emptyTaxEditor(taxResponse.default_country_code));
      setMessage({ tone: 'success', text: 'Vergi oranı silindi.' });
    } catch (requestError) {
      handleRequestError(requestError, 'Vergi oranı silinemedi.');
    } finally {
      setActionId(null);
    }
  }

  function editShipping(method: ShippingMethodSettings) {
    clearFeedback();
    setShippingEditor({
      id: method.id,
      values: {
        name: method.name,
        type: method.type,
        price: method.price,
        minimum_order: method.minimum_order,
        maximum_order: method.maximum_order,
        estimated_days_min: method.estimated_days_min,
        estimated_days_max: method.estimated_days_max,
        status: method.status,
        shipping_zone_id: method.zone?.id ?? null,
      },
    });
    document.getElementById('shipping-editor')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function editTax(rate: TaxRateSettings) {
    clearFeedback();
    setTaxEditor({
      id: rate.id,
      values: {
        name: rate.name,
        country_code: rate.country_code,
        rate: rate.rate,
        is_inclusive: rate.is_inclusive,
        status: rate.status,
      },
    });
    document.getElementById('tax-editor')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function clearFeedback() {
    setMessage(null);
    setFieldErrors({});
  }

  function handleRequestError(requestError: unknown, fallback: string) {
    const errors = requestError instanceof ApiError ? requestError.validationErrors ?? {} : {};
    setFieldErrors(errors);
    setMessage({ tone: 'error', text: firstError(errors) ?? fallback });
  }

  if (loading) return <div className="grid gap-6 lg:grid-cols-2">{[0, 1].map((item) => <div key={item} className="h-96 animate-pulse rounded-lg bg-app-bg" />)}</div>;
  if (!shipping || !taxes) return <Card><p className="text-sm text-red-700">{message?.text ?? 'Fulfillment ayarları bulunamadı.'}</p></Card>;

  return (
    <section id="fulfillment" className="scroll-mt-24 space-y-6">
      <div>
        <p className="text-xs font-semibold uppercase tracking-[.16em] text-primary">Checkout yapılandırması</p>
        <h3 className="mt-1 text-xl font-semibold text-dark">Kargo ve vergi</h3>
        <p className="mt-1 text-sm text-muted">Checkout tekliflerini gerçek kargo yöntemleri ve ülke bazlı vergi oranlarıyla yönetin.</p>
      </div>

      {message && <div className={`flex items-start gap-2 rounded-lg border px-4 py-3 text-sm ${message.tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{message.tone === 'success' ? <Check size={17} /> : <CircleAlert size={17} />}<span>{message.text}</span></div>}
      {!canManage && <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Bu ayarları görüntüleyebilirsiniz. Oluşturma, düzenleme ve silme işlemleri owner veya admin rolü gerektirir.</div>}

      <div className="grid gap-6 xl:grid-cols-2">
        <Card>
          <div className="mb-5 flex items-start justify-between gap-3"><div><h4 className="flex items-center gap-2 font-semibold text-dark"><Truck size={18} className="text-primary" />Kargo yöntemleri</h4><p className="mt-1 text-sm text-muted">Bölge, sipariş eşiği, ücret ve teslimat tahmini.</p></div><Badge tone={shipping.summary.active > 0 ? 'success' : 'warning'}>{shipping.summary.active} aktif</Badge></div>

          {canManage && <form id="shipping-editor" className="mb-6 scroll-mt-24 rounded-lg border border-border bg-app-bg p-4" onSubmit={(event) => void saveShipping(event)}>
            <div className="mb-3 flex items-center justify-between"><p className="text-sm font-semibold text-dark">{shippingEditor.id ? 'Kargo yöntemini düzenle' : 'Yeni kargo yöntemi'}</p>{shippingEditor.id && <button type="button" onClick={() => { setShippingEditor(emptyShippingEditor()); clearFeedback(); }} className="rounded p-1 text-muted hover:bg-card hover:text-dark" aria-label="Düzenlemeyi kapat"><X size={16} /></button>}</div>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="text-xs font-medium text-dark sm:col-span-2">Yöntem adı<input required value={shippingEditor.values.name} onChange={(event) => setShippingEditorValue('name', event.target.value)} className={INPUT_CLASS} />{fieldErrors.name?.[0] && <FieldError>{fieldErrors.name[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Tür<select value={shippingEditor.values.type} onChange={(event) => { const type = event.target.value as ShippingMethodPayload['type']; setShippingEditor((current) => ({ ...current, values: { ...current.values, type, price: type === 'free_shipping' ? '0.00' : current.values.price } })); }} className={INPUT_CLASS}><option value="flat_rate">Sabit ücret</option><option value="free_shipping">Ücretsiz kargo</option></select></label>
              <label className="text-xs font-medium text-dark">Durum<select value={shippingEditor.values.status} onChange={(event) => setShippingEditorValue('status', event.target.value as ShippingMethodPayload['status'])} className={INPUT_CLASS}><option value="active">Aktif</option><option value="inactive">Pasif</option></select></label>
              <label className="text-xs font-medium text-dark">Ücret ({currency})<input required type="number" min="0" step="0.01" disabled={shippingEditor.values.type === 'free_shipping'} value={shippingEditor.values.price} onChange={(event) => setShippingEditorValue('price', event.target.value)} className={INPUT_CLASS} />{fieldErrors.price?.[0] && <FieldError>{fieldErrors.price[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Kargo bölgesi<select value={shippingEditor.values.shipping_zone_id ?? ''} onChange={(event) => setShippingEditorValue('shipping_zone_id', event.target.value || null)} className={INPUT_CLASS}><option value="">Tüm bölgeler</option>{shipping.zones.map((zone) => <option key={zone.id} value={zone.id}>{zone.name}</option>)}</select>{fieldErrors.shipping_zone_id?.[0] && <FieldError>{fieldErrors.shipping_zone_id[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Minimum sipariş<input type="number" min="0" step="0.01" value={shippingEditor.values.minimum_order ?? ''} onChange={(event) => setShippingEditorValue('minimum_order', nullableText(event.target.value))} className={INPUT_CLASS} /></label>
              <label className="text-xs font-medium text-dark">Maksimum sipariş<input type="number" min="0" step="0.01" value={shippingEditor.values.maximum_order ?? ''} onChange={(event) => setShippingEditorValue('maximum_order', nullableText(event.target.value))} className={INPUT_CLASS} />{fieldErrors.maximum_order?.[0] && <FieldError>{fieldErrors.maximum_order[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">En erken teslimat (gün)<input type="number" min="0" max="365" value={shippingEditor.values.estimated_days_min ?? ''} onChange={(event) => setShippingEditorValue('estimated_days_min', nullableInteger(event.target.value))} className={INPUT_CLASS} /></label>
              <label className="text-xs font-medium text-dark">En geç teslimat (gün)<input type="number" min="0" max="365" value={shippingEditor.values.estimated_days_max ?? ''} onChange={(event) => setShippingEditorValue('estimated_days_max', nullableInteger(event.target.value))} className={INPUT_CLASS} />{fieldErrors.estimated_days_max?.[0] && <FieldError>{fieldErrors.estimated_days_max[0]}</FieldError>}</label>
            </div>
            <div className="mt-4 flex justify-end"><Button type="submit" fullWidth={false} disabled={saving === 'shipping'}>{shippingEditor.id ? <Save size={15} /> : <Plus size={15} />}{saving === 'shipping' ? 'Kaydediliyor…' : shippingEditor.id ? 'Güncelle' : 'Yöntem ekle'}</Button></div>
          </form>}

          <div className="space-y-3">
            {shipping.data.map((method) => <div key={method.id} className="rounded-lg border border-border p-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><p className="font-medium text-dark">{method.name}</p><Badge tone={method.status === 'active' ? 'success' : 'neutral'}>{method.status === 'active' ? 'Aktif' : 'Pasif'}</Badge></div><p className="mt-1 text-xs text-muted">{method.type === 'free_shipping' ? 'Ücretsiz' : formatMoney(method.price, currency)} · {method.zone?.name ?? 'Tüm bölgeler'} · {deliveryLabel(method)}</p>{(method.minimum_order || method.maximum_order) && <p className="mt-1 text-xs text-muted">Sipariş aralığı: {method.minimum_order ? formatMoney(method.minimum_order, currency) : '0'} – {method.maximum_order ? formatMoney(method.maximum_order, currency) : 'sınırsız'}</p>}</div>{canManage && <div className="flex shrink-0 gap-1"><button type="button" onClick={() => editShipping(method)} className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label={`${method.name} yöntemini düzenle`}><Pencil size={15} /></button><button type="button" disabled={actionId === method.id} onClick={() => void removeShipping(method)} className="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:opacity-50" aria-label={`${method.name} yöntemini sil`}><Trash2 size={15} /></button></div>}</div></div>)}
            {shipping.data.length === 0 && <EmptyState text="Henüz kargo yöntemi yok. Checkout’u açmak için en az bir aktif yöntem ekleyin." />}
          </div>
        </Card>

        <Card>
          <div className="mb-5 flex items-start justify-between gap-3"><div><h4 className="flex items-center gap-2 font-semibold text-dark"><ReceiptText size={18} className="text-primary" />Vergi oranları</h4><p className="mt-1 text-sm text-muted">Teslimat ülkesine göre inclusive/exclusive vergi.</p></div><Badge tone={taxes.summary.active > 0 ? 'success' : 'warning'}>{taxes.summary.active} aktif</Badge></div>

          {canManage && <form id="tax-editor" className="mb-6 scroll-mt-24 rounded-lg border border-border bg-app-bg p-4" onSubmit={(event) => void saveTax(event)}>
            <div className="mb-3 flex items-center justify-between"><p className="text-sm font-semibold text-dark">{taxEditor.id ? 'Vergi oranını düzenle' : 'Yeni vergi oranı'}</p>{taxEditor.id && <button type="button" onClick={() => { setTaxEditor(emptyTaxEditor(taxes.default_country_code)); clearFeedback(); }} className="rounded p-1 text-muted hover:bg-card hover:text-dark" aria-label="Düzenlemeyi kapat"><X size={16} /></button>}</div>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="text-xs font-medium text-dark sm:col-span-2">Oran adı<input required value={taxEditor.values.name} onChange={(event) => setTaxEditorValue('name', event.target.value)} className={INPUT_CLASS} />{fieldErrors.name?.[0] && <FieldError>{fieldErrors.name[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Ülke kodu<input required maxLength={2} value={taxEditor.values.country_code} onChange={(event) => setTaxEditorValue('country_code', event.target.value.toUpperCase())} className={INPUT_CLASS} />{fieldErrors.country_code?.[0] && <FieldError>{fieldErrors.country_code[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Vergi oranı (%)<input required type="number" min="0" max="100" step="0.01" value={taxEditor.values.rate} onChange={(event) => setTaxEditorValue('rate', event.target.value)} className={INPUT_CLASS} />{fieldErrors.rate?.[0] && <FieldError>{fieldErrors.rate[0]}</FieldError>}</label>
              <label className="text-xs font-medium text-dark">Durum<select value={taxEditor.values.status} onChange={(event) => setTaxEditorValue('status', event.target.value as TaxRatePayload['status'])} className={INPUT_CLASS}><option value="active">Aktif</option><option value="inactive">Pasif</option></select></label>
              <label className="flex items-center gap-2 self-end rounded-md border border-border bg-card px-3 py-2.5 text-xs font-medium text-dark"><input type="checkbox" checked={taxEditor.values.is_inclusive} onChange={(event) => setTaxEditorValue('is_inclusive', event.target.checked)} className="h-4 w-4 accent-primary" />Fiyata vergi dahil</label>
            </div>
            {fieldErrors.tax_rate?.[0] && <FieldError>{fieldErrors.tax_rate[0]}</FieldError>}
            <div className="mt-4 flex justify-end"><Button type="submit" fullWidth={false} disabled={saving === 'tax'}>{taxEditor.id ? <Save size={15} /> : <Plus size={15} />}{saving === 'tax' ? 'Kaydediliyor…' : taxEditor.id ? 'Güncelle' : 'Oran ekle'}</Button></div>
          </form>}

          <div className="space-y-3">
            {taxes.data.map((rate) => <div key={rate.id} className="rounded-lg border border-border p-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><p className="font-medium text-dark">{rate.name}</p><Badge tone={rate.status === 'active' ? 'success' : 'neutral'}>{rate.status === 'active' ? 'Aktif' : 'Pasif'}</Badge>{rate.applies_to_default_country && <Badge tone="primary">Mağaza ülkesi</Badge>}</div><p className="mt-1 text-xs text-muted">{rate.country_code} · %{rate.rate} · {rate.is_inclusive ? 'Fiyata dahil' : 'Fiyata eklenir'}</p></div>{canManage && <div className="flex shrink-0 gap-1"><button type="button" onClick={() => editTax(rate)} className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label={`${rate.name} oranını düzenle`}><Pencil size={15} /></button><button type="button" disabled={actionId === rate.id} onClick={() => void removeTax(rate)} className="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:opacity-50" aria-label={`${rate.name} oranını sil`}><Trash2 size={15} /></button></div>}</div></div>)}
            {taxes.data.length === 0 && <EmptyState text="Henüz vergi oranı yok. Mağaza ülkeniz için aktif bir oran ekleyin." />}
          </div>
        </Card>
      </div>
    </section>
  );

  function setShippingEditorValue<K extends keyof ShippingMethodPayload>(key: K, value: ShippingMethodPayload[K]) {
    setShippingEditor((current) => ({ ...current, values: { ...current.values, [key]: value } }));
  }

  function setTaxEditorValue<K extends keyof TaxRatePayload>(key: K, value: TaxRatePayload[K]) {
    setTaxEditor((current) => ({ ...current, values: { ...current.values, [key]: value } }));
  }
}

function emptyShippingEditor(): ShippingEditor {
  return {
    id: null,
    values: {
      name: '',
      type: 'flat_rate',
      price: '0.00',
      minimum_order: null,
      maximum_order: null,
      estimated_days_min: 2,
      estimated_days_max: 5,
      status: 'active',
      shipping_zone_id: null,
    },
  };
}

function emptyTaxEditor(countryCode: string): TaxEditor {
  return { id: null, values: { name: '', country_code: countryCode, rate: '20.00', is_inclusive: true, status: 'active' } };
}

function nullableText(value: string): string | null {
  return value === '' ? null : value;
}

function nullableInteger(value: string): number | null {
  return value === '' ? null : Number.parseInt(value, 10);
}

function formatMoney(value: string, currency: string): string {
  return new Intl.NumberFormat('tr-TR', { style: 'currency', currency }).format(Number(value));
}

function deliveryLabel(method: ShippingMethodSettings): string {
  if (method.estimated_days_min === null && method.estimated_days_max === null) return 'Teslimat süresi belirtilmedi';
  if (method.estimated_days_min === method.estimated_days_max) return `${method.estimated_days_min} gün`;
  return `${method.estimated_days_min ?? 0}–${method.estimated_days_max ?? '?'} gün`;
}

function firstError(errors: Record<string, string[]>): string | null {
  return Object.values(errors).find((messages) => messages.length > 0)?.[0] ?? null;
}

function FieldError({ children }: { children: string }) {
  return <span className="mt-1 block text-xs text-red-600">{children}</span>;
}

function EmptyState({ text }: { text: string }) {
  return <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted">{text}</div>;
}
