import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpRight, CheckCircle2, Clock3, CreditCard, FileText, Globe2, Image, LayoutTemplate, Navigation, Palette, Settings2 } from 'lucide-react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { getOnlineStore } from '../api/onlineStoreApi';
import type { OnlineStoreOverview } from '../types';

function formatRelative(value: string | null): string {
  if (!value) return 'Henüz yayın yok';
  const minutes = Math.max(1, Math.round((Date.now() - new Date(value).getTime()) / 60000));
  if (minutes < 60) return `${minutes} dk önce`;
  const hours = Math.round(minutes / 60);
  return hours < 24 ? `${hours} saat önce` : `${Math.round(hours / 24)} gün önce`;
}

export function OnlineStorePage() {
  const [data, setData] = useState<OnlineStoreOverview | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    getOnlineStore().then((response) => setData(response.data)).catch(() => setError(true));
  }, []);

  if (error) {
    return <Card><h2 className="text-lg font-semibold text-dark">Online mağaza yüklenemedi</h2><p className="mt-2 text-sm text-muted">Lütfen biraz sonra tekrar deneyin.</p></Card>;
  }

  if (!data) {
    return <div className="space-y-4"><div className="h-32 animate-pulse rounded-lg bg-card" /><div className="h-80 animate-pulse rounded-lg bg-card" /></div>;
  }

  const navItems = [
    { label: 'Temalar', detail: data.summary.theme, href: '/online-store/themes', icon: Palette },
    { label: 'Sayfalar', detail: `${data.summary.pages} sayfa`, href: '/online-store/pages', icon: FileText },
    { label: 'Navigasyon', detail: `${data.summary.menus} menü`, href: '/online-store/navigation', icon: Navigation },
    { label: 'Domainler', detail: data.store.domain, href: '/online-store/domains', icon: Globe2 },
    { label: 'Tercihler', detail: 'SEO, marka, sosyal', href: '/online-store/preferences', icon: Settings2 },
    { label: 'Medya', detail: 'Görseller ve odak noktaları', href: '/online-store/media', icon: Image },
    { label: 'Checkout', detail: 'Ödeme deneyimi', href: '/online-store/checkout', icon: CreditCard },
  ];

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <section className="rounded-lg border border-border bg-card p-5 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-semibold text-dark">Online mağaza</h1>
              <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${data.store.status === 'live' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                <span className="h-1.5 w-1.5 rounded-full bg-current" />{data.store.status === 'live' ? 'Yayında' : 'Taslak'}
              </span>
            </div>
            <p className="mt-1 text-sm text-muted">{data.store.name} · {data.store.domain}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <a href={data.store.storefront_url} target="_blank" rel="noreferrer">
              <Button type="button" variant="secondary" fullWidth={false}><ArrowUpRight className="h-4 w-4" />Mağazayı aç</Button>
            </a>
            <Link to={`/online-store/themes/${data.theme.id}/editor`}>
              <Button type="button" fullWidth={false}><Palette className="h-4 w-4" />Düzenle</Button>
            </Link>
          </div>
        </div>

        <div className="mt-5 grid gap-3 border-t border-border pt-5 sm:grid-cols-3">
          <div className="flex items-center gap-3">
            <CheckCircle2 className="h-5 w-5 text-emerald-600" />
            <div><p className="text-xs text-muted">Tema</p><p className="text-sm font-semibold text-dark">{data.summary.theme}</p></div>
          </div>
          <div className="flex items-center gap-3">
            <Clock3 className="h-5 w-5 text-primary" />
            <div><p className="text-xs text-muted">Son yayın</p><p className="text-sm font-semibold text-dark">{formatRelative(data.summary.last_publish)}</p></div>
          </div>
          <div className="flex items-center gap-3">
            <Globe2 className="h-5 w-5 text-primary" />
            <div><p className="text-xs text-muted">Domain</p><p className="text-sm font-semibold text-dark">{data.summary.domain_status === 'connected' ? 'Bağlı' : 'DNS bekleniyor'}</p></div>
          </div>
        </div>
      </section>

      <section className="rounded-lg border border-border bg-card shadow-sm">
        <div className="border-b border-border px-5 py-4">
          <h2 className="text-base font-semibold text-dark">Yönetim</h2>
        </div>
        <div className="divide-y divide-border">
          {navItems.map((item) => (
            <Link to={item.href} className="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-app-bg/70" key={item.href}>
              <div className="flex min-w-0 items-center gap-3">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-app-bg text-primary"><item.icon className="h-4 w-4" /></span>
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-dark">{item.label}</p>
                  <p className="truncate text-xs text-muted">{item.detail}</p>
                </div>
              </div>
              <ArrowUpRight className="h-4 w-4 shrink-0 text-muted" />
            </Link>
          ))}
        </div>
      </section>

      <section className="grid gap-3 md:grid-cols-2">
        <Link to={`/online-store/themes/${data.theme.id}/editor`} className="rounded-lg border border-border bg-card p-4 shadow-sm transition hover:border-primary">
          <LayoutTemplate className="h-5 w-5 text-primary" />
          <p className="mt-3 text-sm font-semibold text-dark">Ana sayfayı düzenle</p>
          <p className="mt-1 text-xs text-muted">Section, blok ve responsive ayarları.</p>
        </Link>
        <Link to="/online-store/themes" className="rounded-lg border border-border bg-card p-4 shadow-sm transition hover:border-primary">
          <Palette className="h-5 w-5 text-primary" />
          <p className="mt-3 text-sm font-semibold text-dark">Tema kitaplığı</p>
          <p className="mt-1 text-xs text-muted">Önizleme, yayınlama ve kurulu temalar.</p>
        </Link>
      </section>
    </div>
  );
}
