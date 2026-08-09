import { useEffect, useState } from 'react';
import { Boxes, CircleAlert, CircleCheck, ExternalLink, PlugZap, Store } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';
import { getIntegrations, type IntegrationItem, type IntegrationStatus } from '../api/settingsApi';

const STATUS: Record<IntegrationStatus, { label: string; tone: 'success' | 'warning' | 'neutral' | 'primary' }> = {
  active: { label: 'Aktif', tone: 'success' },
  test_mode: { label: 'Test modu', tone: 'warning' },
  needs_attention: { label: 'İşlem gerekli', tone: 'warning' },
  not_configured: { label: 'Yapılandırılmadı', tone: 'neutral' },
  not_available: { label: 'Desteklenmiyor', tone: 'neutral' },
};

export function IntegrationsPage({ section }: { section: 'channels' | 'apps' }) {
  const title = section === 'channels' ? 'Satış Kanalları' : 'Uygulamalar';
  usePageTitle(title);
  const [items, setItems] = useState<IntegrationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void getIntegrations()
      .then((response) => { if (active) setItems(response.data[section]); })
      .catch(() => { if (active) setError('Entegrasyon durumları yüklenemedi.'); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [section]);

  const activeCount = items.filter((item) => item.status === 'active').length;
  const attentionCount = items.filter((item) => ['needs_attention', 'not_configured', 'test_mode'].includes(item.status)).length;

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div><p className="mb-1 text-xs font-semibold uppercase tracking-[.16em] text-primary">Entegrasyon merkezi</p><h2 className="text-2xl font-semibold text-dark">{title}</h2><p className="mt-1 max-w-2xl text-sm text-muted">{section === 'channels' ? 'Mağazanızın gerçekten desteklediği satış kanallarını ve yayın durumlarını görün.' : 'Ödeme, kargo ve envanter servislerinin canlı yapılandırma durumunu izleyin.'}</p></div>
      <div className="grid gap-4 sm:grid-cols-3"><Summary icon={section === 'channels' ? Store : Boxes} label="Listelenen" value={items.length} /><Summary icon={CircleCheck} label="Aktif" value={activeCount} tone="text-emerald-600" /><Summary icon={CircleAlert} label="İşlem gerekli" value={attentionCount} tone="text-amber-600" /></div>
      {error && <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {loading ? Array.from({ length: 3 }, (_, index) => <div key={index} className="h-52 animate-pulse rounded-lg bg-app-bg" />) : items.map((item) => <IntegrationCard key={item.id} item={item} />)}
      </div>
      <Card className="bg-app-bg/70"><div className="flex gap-3"><PlugZap className="mt-0.5 shrink-0 text-primary" size={20} /><div><h3 className="font-semibold text-dark">Şeffaf entegrasyon durumu</h3><p className="mt-1 text-sm leading-6 text-muted">Bu ekran yalnızca veritabanı ve güvenli sunucu yapılandırmasından doğrulanabilen durumları gösterir. Desteklenmeyen servisler bağlıymış gibi gösterilmez ve çalışmayan bağlantı düğmeleri sunulmaz.</p></div></div></Card>
    </div>
  );
}

function Summary({ icon: Icon, label, value, tone = 'text-dark' }: { icon: typeof Store; label: string; value: number; tone?: string }) {
  return <Card><div className="flex items-center justify-between"><p className="text-sm text-muted">{label}</p><Icon size={18} className={tone} /></div><p className={`mt-3 text-2xl font-semibold ${tone}`}>{value}</p></Card>;
}

function IntegrationCard({ item }: { item: IntegrationItem }) {
  const status = STATUS[item.status];
  return <Card className="flex min-h-52 flex-col"><div className="flex items-start justify-between gap-3"><div className="grid h-10 w-10 place-items-center rounded-xl bg-surface-orange text-primary"><PlugZap size={20} /></div><Badge tone={status.tone}>{status.label}</Badge></div><h3 className="mt-4 font-semibold text-dark">{item.name}</h3><p className="mt-1 flex-1 text-sm leading-6 text-muted">{item.description}</p>{item.detail && <p className="mt-3 truncate rounded-md bg-app-bg px-2.5 py-2 text-xs font-medium text-dark">{item.detail}</p>}{item.manage_path ? <Link to={item.manage_path} className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-hover">Yönet <ExternalLink size={14} /></Link> : <p className="mt-4 text-xs font-medium text-muted">Bağlantı işlemi mevcut değil</p>}</Card>;
}
