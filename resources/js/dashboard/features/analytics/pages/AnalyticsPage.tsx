import { useEffect, useState } from 'react';
import {
  ArrowDownRight,
  ArrowUpRight,
  BarChart3,
  Globe2,
  MousePointerClick,
  RefreshCw,
  ShoppingCart,
  Users,
  Wallet,
} from 'lucide-react';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Card } from '../../../components/ui/Card';
import { formatMoney } from '../../../utils/commerceFormat';
import { getAnalytics, type AnalyticsData, type AnalyticsRange } from '../api/analyticsApi';

function Change({ value }: { value: number | null }) {
  if (value === null) return <span className="text-xs text-muted">Yeni veri</span>;
  const up = value >= 0;
  return (
    <span className={`flex items-center gap-1 text-xs font-semibold ${up ? 'text-emerald-700' : 'text-red-600'}`}>
      {up ? <ArrowUpRight size={13} /> : <ArrowDownRight size={13} />}
      {Math.abs(value)}% önceki dönem
    </span>
  );
}

function Metric({ label, value, change, icon: Icon }: { label: string; value: string; change: number | null; icon: typeof Wallet }) {
  return (
    <Card>
      <div className="flex items-center justify-between"><p className="text-sm text-muted">{label}</p><span className="rounded-lg bg-surface-orange p-2 text-primary"><Icon size={17} /></span></div>
      <p className="my-3 text-2xl font-semibold">{value}</p>
      <Change value={change} />
    </Card>
  );
}

function Chart({ data }: { data: AnalyticsData }) {
  const values = data.series.map((point) => Number(point.sales));
  const positiveMax = Math.max(...values, 0);
  const negativeMax = Math.max(...values.map((value) => Math.abs(Math.min(value, 0))), 0);
  const domain = Math.max(positiveMax + negativeMax, 1);
  const zeroFromBottom = (negativeMax / domain) * 100;
  const zeroFromTop = (positiveMax / domain) * 100;

  return (
    <div className="relative flex h-64 gap-1.5 px-2 pt-5">
      <div className="pointer-events-none absolute inset-x-2 border-t border-border" style={{ bottom: `${zeroFromBottom}%` }} />
      {data.series.map((point) => {
        const value = Number(point.sales);
        const negative = value < 0;
        const availableHeight = negative ? 100 - zeroFromTop : 100 - zeroFromBottom;
        const height = value === 0
          ? 0
          : Math.min(Math.max((Math.abs(value) / domain) * 100, 2), availableHeight);

        return (
          <div key={point.date} className="group relative h-full flex-1">
            <div
              aria-label={`${point.date}: net ${formatMoney(point.sales, data.currency)}`}
              style={negative
                ? { height: `${height}%`, top: `${zeroFromTop}%` }
                : { bottom: `${zeroFromBottom}%`, height: `${height}%` }}
              className={`absolute inset-x-0 transition ${negative ? 'rounded-b bg-red-500/75 hover:bg-red-500' : 'rounded-t bg-primary/75 hover:bg-primary'}`}
            />
            <div className="pointer-events-none absolute left-1/2 top-1 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-dark px-3 py-2 text-xs text-white shadow-lg group-hover:block">
              <strong className={negative ? 'text-red-200' : ''}>Net {formatMoney(point.sales, data.currency)}</strong><br />Brüt {formatMoney(point.gross_sales, data.currency)} · İade {formatMoney(point.refunds, data.currency)}<br />{point.orders} sipariş · {point.date}
            </div>
          </div>
        );
      })}
    </div>
  );
}

function sourceLabel(source: string): string {
  if (source === 'direct') return 'Doğrudan';
  if (source === 'x') return 'X / Twitter';
  return source.replace(/-/g, ' ').replace(/\b\w/g, (letter: string) => letter.toUpperCase());
}

function Traffic({ data }: { data: AnalyticsData }) {
  if (!data.traffic.available) {
    return (
      <Card>
        <div className="flex items-start gap-3"><span className="rounded-xl bg-surface-orange p-3 text-primary"><MousePointerClick size={20} /></span><div><h3 className="font-semibold">Trafik ve dönüşüm hunisi</h3><p className="mt-1 text-sm leading-6 text-muted">Bu dönem için anonim storefront olayı henüz oluşmadı. Ziyaretler geldikçe kaynaklar ve dönüşüm adımları burada görünecek.</p></div></div>
        <div className="mt-6 rounded-xl border border-dashed border-border bg-app-bg p-6 text-center text-sm text-muted">Henüz trafik verisi yok</div>
      </Card>
    );
  }

  const maxFunnel = Math.max(...data.traffic.funnel.map((step) => step.sessions), 1);
  return (
    <Card>
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-start gap-3"><span className="rounded-xl bg-surface-orange p-3 text-primary"><MousePointerClick size={20} /></span><div><h3 className="font-semibold">Trafik ve dönüşüm hunisi</h3><p className="mt-1 text-sm text-muted">Anonim, PII içermeyen storefront olayları</p></div></div>
        <div className="text-right"><p className="text-2xl font-semibold">{data.traffic.sessions}</p><p className="text-xs text-muted">oturum</p></div>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <section>
          <h4 className="flex items-center gap-2 text-sm font-semibold"><Globe2 size={15} /> Trafik kaynakları</h4>
          {data.traffic.sources.length ? (
            <div className="mt-3 space-y-3">
              {data.traffic.sources.map((source) => (
                <div key={source.source}>
                  <div className="mb-1 flex items-center justify-between gap-3 text-xs"><span className="truncate font-medium">{sourceLabel(source.source)}</span><span className="shrink-0 text-muted">{source.sessions} · %{source.share}</span></div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-app-bg"><div className="h-full rounded-full bg-primary" style={{ width: `${Math.max(source.share, 2)}%` }} /></div>
                </div>
              ))}
            </div>
          ) : <p className="mt-4 text-sm text-muted">Kaynak oluşturacak sayfa görüntülemesi yok.</p>}
        </section>

        <section>
          <h4 className="text-sm font-semibold">Dönüşüm adımları</h4>
          <div className="mt-3 space-y-2.5">
            {data.traffic.funnel.map((step) => (
              <div key={step.key} className="rounded-lg bg-app-bg p-3">
                <div className="flex items-center justify-between gap-3 text-xs"><span className="font-medium">{step.label}</span><span className="text-muted">{step.sessions} · {step.conversion_rate === null ? '—' : `%${step.conversion_rate}`}</span></div>
                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-border"><div className="h-full rounded-full bg-dark" style={{ width: `${step.sessions ? Math.max((step.sessions / maxFunnel) * 100, 3) : 0}%` }} /></div>
              </div>
            ))}
          </div>
        </section>
      </div>
      <p className="mt-5 text-xs text-muted">{data.traffic.total_events} olay · Satın alma yalnızca doğrulanmış sunucu ödeme akışından sayılır.</p>
    </Card>
  );
}

export function AnalyticsPage() {
  usePageTitle('Analitik');
  const [range, setRange] = useState<AnalyticsRange>('30d');
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [error, setError] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(false);
    void getAnalytics(range)
      .then((response) => { if (active) setData(response.data); })
      .catch(() => { if (active) setError(true); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [range]);

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p className="text-xs font-semibold uppercase tracking-widest text-primary">Gerçek ticaret verileri</p><h2 className="mt-1 text-2xl font-semibold">Analitik</h2><p className="mt-1 text-sm text-muted">Satışlarını, trafik kaynaklarını ve dönüşüm hunini karşılaştır.</p></div>
        <div className="flex rounded-lg border border-border bg-card p-1">{(['7d', '30d', '90d'] as const).map((item) => <button key={item} onClick={() => setRange(item)} className={`rounded-md px-3 py-2 text-xs font-semibold ${range === item ? 'bg-dark text-white' : 'text-muted hover:text-dark'}`}>{item === '7d' ? '7 gün' : item === '30d' ? '30 gün' : '90 gün'}</button>)}</div>
      </div>
      {error && <div className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><RefreshCw size={16} />Analitik verileri alınamadı.</div>}
      {loading || !data ? <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">{[1, 2, 3, 4].map((item) => <div key={item} className="h-32 animate-pulse rounded-card bg-border/60" />)}</div> : <>
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4"><Metric label="Net satış" value={formatMoney(data.metrics.net_sales, data.currency)} change={data.changes.net_sales} icon={Wallet} /><Metric label="Sipariş" value={String(data.metrics.orders)} change={data.changes.orders} icon={ShoppingCart} /><Metric label="Ortalama sipariş" value={formatMoney(data.metrics.average_order, data.currency)} change={data.changes.average_order} icon={BarChart3} /><Metric label="Yeni müşteri" value={String(data.metrics.new_customers)} change={data.changes.new_customers} icon={Users} /></div>
        <div className="grid gap-4 xl:grid-cols-12"><Card className="xl:col-span-8"><div><h3 className="font-semibold">Satış trendi</h3><p className="text-sm text-muted">Ödenmiş siparişler eksi başarılı iadelerin günlük net toplamı</p></div>{data.series.length ? <Chart data={data} /> : <div className="grid h-64 place-items-center text-center"><div><BarChart3 className="mx-auto text-muted" /><p className="mt-3 font-medium">Bu dönemde satış yok</p><p className="text-sm text-muted">İlk başarılı ödeme burada görünür.</p></div></div>}</Card><Card className="xl:col-span-4"><h3 className="font-semibold">Müşteri özeti</h3><p className="mb-5 text-sm text-muted">Mağaza müşteri kalitesi</p><div className="space-y-3"><div className="rounded-xl bg-app-bg p-4"><p className="text-xs text-muted">Yeni müşteriler</p><p className="mt-1 text-xl font-semibold">{data.metrics.new_customers}</p></div><div className="rounded-xl bg-app-bg p-4"><p className="text-xs text-muted">Tekrar alışveriş yapan</p><p className="mt-1 text-xl font-semibold">{data.metrics.returning_customers}</p></div></div></Card></div>
        <div className="grid gap-4 xl:grid-cols-2"><Card><h3 className="font-semibold">En çok satan ürünler</h3><p className="mb-4 text-sm text-muted">İadeler ürün satırlarına dağıtılamadığı için brüt gelire göre ilk 10 ürün</p>{data.top_products.length ? <div className="divide-y divide-border">{data.top_products.map((product, index) => <div key={product.title} className="flex items-center gap-3 py-3"><span className="grid h-8 w-8 place-items-center rounded-lg bg-app-bg text-xs font-bold">{index + 1}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{product.title}</p><p className="text-xs text-muted">{product.quantity} adet</p></div><strong className="text-sm">{formatMoney(product.revenue, data.currency)}</strong></div>)}</div> : <p className="py-12 text-center text-sm text-muted">Satış verisi oluştuğunda ürünler sıralanacak.</p>}</Card><Traffic data={data} /></div>
      </>}
    </div>
  );
}
