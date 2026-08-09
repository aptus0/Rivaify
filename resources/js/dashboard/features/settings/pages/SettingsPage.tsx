import { useEffect, useState, type FormEvent } from 'react';
import { Building2, Check, CircleAlert, FileText, Globe2, Landmark, Plus, ReceiptText, Save, Store, Trash2 } from 'lucide-react';
import { useLocation } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ApiError } from '../../../lib/api';
import { AccountSettings } from '../components/AccountSettings';
import { FulfillmentSettings } from '../components/FulfillmentSettings';
import {
  addStoreDomain,
  deleteStoreDomain,
  getSettings,
  makeStoreDomainPrimary,
  updateStoreProfile,
  verifyStoreDomain,
  type StoreProfilePayload,
  type StoreSettings,
} from '../api/settingsApi';

const TIMEZONES = ['Europe/Istanbul', 'UTC', 'Europe/Berlin', 'Europe/London', 'America/New_York'];

export function SettingsPage() {
  usePageTitle('Ayarlar');
  const location = useLocation();
  const { refresh: refreshAuth } = useAuth();
  const [settings, setSettings] = useState<StoreSettings | null>(null);
  const [profile, setProfile] = useState<StoreProfilePayload | null>(null);
  const [domain, setDomain] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [addingDomain, setAddingDomain] = useState(false);
  const [domainActionId, setDomainActionId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  async function load() {
    const response = await getSettings();
    setSettings(response.data);
    setProfile({
      name: response.data.store.name,
      default_currency: response.data.store.default_currency,
      default_locale: response.data.store.default_locale,
      timezone: response.data.store.timezone,
      country_code: response.data.store.country_code,
    });
  }

  useEffect(() => {
    let active = true;
    void getSettings()
      .then((response) => {
        if (!active) return;
        setSettings(response.data);
        setProfile({
          name: response.data.store.name,
          default_currency: response.data.store.default_currency,
          default_locale: response.data.store.default_locale,
          timezone: response.data.store.timezone,
          country_code: response.data.store.country_code,
        });
      })
      .catch(() => { if (active) setError('Ayarlar yüklenemedi.'); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (loading || !location.hash) return;
    const frame = window.requestAnimationFrame(() => {
      document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    return () => window.cancelAnimationFrame(frame);
  }, [loading, location.hash]);

  function updateProfile<K extends keyof StoreProfilePayload>(key: K, value: StoreProfilePayload[K]) {
    setProfile((current) => current ? { ...current, [key]: value } : current);
  }

  async function saveProfile(event: FormEvent) {
    event.preventDefault();
    if (!profile) return;
    setSaving(true);
    setError(null);
    setSuccess(null);
    setFieldErrors({});
    try {
      const response = await updateStoreProfile(profile);
      setSettings(response.data);
      await refreshAuth();
      setSuccess('Mağaza bilgileri kaydedildi.');
    } catch (requestError) {
      if (requestError instanceof ApiError) setFieldErrors(requestError.validationErrors ?? {});
      setError('Mağaza bilgileri kaydedilemedi.');
    } finally {
      setSaving(false);
    }
  }

  async function addDomain(event: FormEvent) {
    event.preventDefault();
    if (!domain.trim()) return;
    setAddingDomain(true);
    setError(null);
    setSuccess(null);
    setFieldErrors({});
    try {
      await addStoreDomain(domain);
      setDomain('');
      await load();
      setSuccess('Alan adı eklendi ve doğrulama bekliyor.');
    } catch (requestError) {
      if (requestError instanceof ApiError) setFieldErrors(requestError.validationErrors ?? {});
      setError('Alan adı eklenemedi.');
    } finally {
      setAddingDomain(false);
    }
  }

  async function removeDomain(domainId: string, hostname: string) {
    if (!window.confirm(`${hostname} alan adı kaldırılsın mı?`)) return;
    setError(null);
    try {
      await deleteStoreDomain(domainId);
      await load();
      setSuccess('Alan adı kaldırıldı.');
    } catch {
      setError('Birincil alan adı silinemez.');
    }
  }

  async function verifyDomain(domainId: string) {
    setDomainActionId(domainId);
    setError(null);
    setSuccess(null);
    try {
      await verifyStoreDomain(domainId);
      await load();
      setSuccess('Alan adı doğrulandı. Artık birincil alan adı olarak seçebilirsiniz.');
    } catch {
      setError('DNS kaydı henüz doğrulanamadı. Yayılım birkaç saat sürebilir; kayıtları kontrol edip tekrar deneyin.');
    } finally {
      setDomainActionId(null);
    }
  }

  async function makePrimary(domainId: string) {
    setDomainActionId(domainId);
    setError(null);
    setSuccess(null);
    try {
      await makeStoreDomainPrimary(domainId);
      await load();
      setSuccess('Birincil alan adı güncellendi.');
    } catch {
      setError('Yalnızca doğrulanmış bir alan adı birincil yapılabilir.');
    } finally {
      setDomainActionId(null);
    }
  }

  if (loading) return <SettingsSkeleton />;
  if (!settings || !profile) return <Card><p className="text-sm text-red-700">{error ?? 'Ayar verisi bulunamadı.'}</p></Card>;

  const inputClass = 'mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary focus:ring-2 focus:ring-primary/10';
  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div><p className="mb-1 text-xs font-semibold uppercase tracking-[.16em] text-primary">Hesap ve mağaza yönetimi</p><h2 className="text-2xl font-semibold text-dark">Ayarlar</h2><p className="mt-1 text-sm text-muted">Hesabınızı, mağaza profilini, checkout yapılandırmasını ve alan adlarını yönetin.</p></div>

      {(error || success) && <div className={`flex items-center gap-2 rounded-lg border px-4 py-3 text-sm ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{error ? <CircleAlert size={17} /> : <Check size={17} />}{error ?? success}</div>}

      <AccountSettings />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
        <form id="store" className="scroll-mt-24" onSubmit={(event) => void saveProfile(event)}>
          <Card className="h-full">
            <div className="mb-5 flex items-start justify-between"><div><h3 className="flex items-center gap-2 font-semibold text-dark"><Store size={18} className="text-primary" />Mağaza profili</h3><p className="mt-1 text-sm text-muted">Müşteriye gösterilen temel mağaza bilgileri.</p></div><Badge tone={settings.store.status === 'active' ? 'success' : 'warning'}>{storeStatusLabel(settings.store.status)}</Badge></div>
            {!settings.permissions.can_manage && <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Mağaza profilini yalnızca owner veya admin rolü düzenleyebilir.</div>}
            <fieldset disabled={!settings.permissions.can_manage}>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="text-sm font-medium text-dark sm:col-span-2">Mağaza adı<input required value={profile.name} onChange={(event) => updateProfile('name', event.target.value)} className={inputClass} />{fieldErrors.name?.[0] && <span className="mt-1 block text-xs text-red-600">{fieldErrors.name[0]}</span>}</label>
              <label className="text-sm font-medium text-dark">Mağaza kimliği<input readOnly value={settings.store.slug} className={`${inputClass} cursor-not-allowed bg-app-bg text-muted`} /><span className="mt-1 block text-xs text-muted">Sistem adresi: {settings.store.slug}.rivaify.com</span></label>
              <label className="text-sm font-medium text-dark">Ülke<select value={profile.country_code} onChange={(event) => updateProfile('country_code', event.target.value)} className={inputClass}><option value="TR">Türkiye</option><option value="DE">Almanya</option><option value="GB">Birleşik Krallık</option><option value="US">ABD</option></select></label>
              <label className="text-sm font-medium text-dark">Para birimi<select value={profile.default_currency} onChange={(event) => updateProfile('default_currency', event.target.value as StoreProfilePayload['default_currency'])} className={inputClass}><option value="TRY">TRY — Türk Lirası</option><option value="USD">USD — ABD Doları</option><option value="EUR">EUR — Euro</option><option value="GBP">GBP — İngiliz Sterlini</option></select></label>
              <label className="text-sm font-medium text-dark">Dil<select value={profile.default_locale} onChange={(event) => updateProfile('default_locale', event.target.value as StoreProfilePayload['default_locale'])} className={inputClass}><option value="tr">Türkçe</option><option value="en">English</option></select></label>
              <label className="text-sm font-medium text-dark sm:col-span-2">Saat dilimi<select value={profile.timezone} onChange={(event) => updateProfile('timezone', event.target.value)} className={inputClass}>{!TIMEZONES.includes(profile.timezone) && <option value={profile.timezone}>{profile.timezone}</option>}{TIMEZONES.map((timezone) => <option key={timezone} value={timezone}>{timezone}</option>)}</select></label>
            </div>
            <div className="mt-5 flex justify-end"><Button type="submit" fullWidth={false} disabled={saving}><Save size={16} />{saving ? 'Kaydediliyor…' : 'Değişiklikleri kaydet'}</Button></div>
            </fieldset>
          </Card>
        </form>

        <BusinessOperationsCards storeName={settings.store.name} countryCode={settings.store.country_code} currency={settings.store.default_currency} />
      </div>

      <FulfillmentSettings canManage={settings.permissions.can_manage} countryCode={settings.store.country_code} currency={settings.store.default_currency} />

      <Card>
        <div className="mb-5 flex items-start justify-between"><div><h3 className="flex items-center gap-2 font-semibold text-dark"><Globe2 size={18} className="text-primary" />Alan adları</h3><p className="mt-1 text-sm text-muted">Sistem alan adınız hazırdır; özel alan adları doğrulanmadan trafik almaz.</p></div><Badge>{settings.domains.length} kayıt</Badge></div>
        {settings.permissions.can_manage ? <form onSubmit={(event) => void addDomain(event)} className="flex flex-col gap-2 sm:flex-row"><input aria-label="Özel alan adı" value={domain} onChange={(event) => setDomain(event.target.value)} placeholder="magazam.com" className="min-w-0 flex-1 rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary" /><Button type="submit" fullWidth={false} disabled={addingDomain || !domain.trim()}><Plus size={16} />{addingDomain ? 'Ekleniyor…' : 'Alan adı ekle'}</Button></form> : <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Alan adı yönetimi owner veya admin rolü gerektirir.</div>}
        {fieldErrors.domain?.[0] && <p className="mt-2 text-xs text-red-600">{fieldErrors.domain[0]}</p>}
        <div className="mt-5 divide-y divide-border rounded-lg border border-border">
          {settings.domains.map((item) => <div key={item.id} className="flex flex-col gap-3 px-4 py-3"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0"><p className="truncate font-medium text-dark">{item.domain}</p><div className="mt-1 flex flex-wrap gap-2">{item.is_primary && <Badge tone="primary">Birincil</Badge>}<Badge tone={item.verified ? 'success' : 'warning'}>{item.verified ? 'Doğrulandı' : 'Doğrulama bekliyor'}</Badge></div></div>{settings.permissions.can_manage && <div className="flex flex-wrap gap-2">{!item.verified && <Button fullWidth={false} variant="secondary" disabled={domainActionId === item.id} onClick={() => void verifyDomain(item.id)}>{domainActionId === item.id ? 'Kontrol ediliyor…' : 'DNS’i doğrula'}</Button>}{item.verified && !item.is_primary && <Button fullWidth={false} variant="secondary" disabled={domainActionId === item.id} onClick={() => void makePrimary(item.id)}>Birincil yap</Button>}{!item.is_primary && <button onClick={() => void removeDomain(item.id, item.domain)} className="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"><Trash2 size={14} />Kaldır</button>}</div>}</div>{!item.verified && !item.domain.endsWith('.rivaify.com') && <div className="rounded-md bg-app-bg p-3 text-xs leading-5 text-muted"><p><strong className="text-dark">Seçenek 1 — CNAME:</strong> <code>{item.domain}</code> → <code>{settings.store.slug}.rivaify.com</code></p><p className="mt-1"><strong className="text-dark">Seçenek 2 — TXT:</strong> <code>_rivaify-verification.{item.domain}</code> → <code>rivaify-site-verification={settings.store.id}</code></p></div>}</div>)}
        </div>
      </Card>
    </div>
  );
}

function BusinessOperationsCards({ storeName, countryCode, currency }: { storeName: string; countryCode: string; currency: string }) {
  const cards = [
    { icon: Building2, title: 'İşletme bilgileri', label: storeName, detail: `${countryCode} merkezli mağaza profili` },
    { icon: ReceiptText, title: 'Vergi ve fatura', label: countryCode === 'TR' ? 'KDV uyumlu' : 'Yerel vergi akışı', detail: 'Vergi oranları fulfillment bölümünde yönetilir.' },
    { icon: Landmark, title: 'Banka bilgileri', label: 'Tahsilat hesabı', detail: `${currency} para birimiyle ödeme sonrası mutabakat.` },
    { icon: FileText, title: 'Belge düzeni', label: 'Sipariş ve fatura', detail: 'Müşteri belgeleri mağaza profilinden beslenir.' },
  ];

  return (
    <Card className="h-full">
      <div className="mb-5">
        <h3 className="font-semibold text-dark">Operasyon profili</h3>
        <p className="mt-1 text-sm text-muted">İşletme, vergi, fatura ve banka hazırlığı tek bakışta.</p>
      </div>
      <div className="grid gap-3">
        {cards.map(({ icon: Icon, title, label, detail }) => (
          <div key={title} className="rounded-lg border border-border bg-app-bg p-4">
            <div className="flex items-start gap-3">
              <span className="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-card text-primary"><Icon size={18} /></span>
              <div className="min-w-0">
                <p className="font-medium text-dark">{title}</p>
                <p className="mt-1 text-sm font-semibold text-primary-hover">{label}</p>
                <p className="mt-1 text-xs leading-5 text-muted">{detail}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </Card>
  );
}

function storeStatusLabel(status: StoreSettings['store']['status']): string {
  return { draft: 'Taslak', pending_approval: 'Onay bekliyor', active: 'Aktif', suspended: 'Askıda', closed: 'Kapalı' }[status];
}

function SettingsSkeleton() {
  return <div className="mx-auto max-w-6xl space-y-5"><div className="h-16 w-72 animate-pulse rounded-lg bg-app-bg" /><div className="grid gap-6 lg:grid-cols-2">{[0, 1].map((item) => <div key={item} className="h-96 animate-pulse rounded-lg bg-app-bg" />)}</div></div>;
}
