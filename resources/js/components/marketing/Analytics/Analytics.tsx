import { TrendingUp } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Reveal } from '../../effects/Reveal';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';

const STATS = [
  { label: 'Net Satış', value: '₺184.920', delta: '+18,4%' },
  { label: 'Sipariş', value: '1.248', delta: '+9,2%' },
  { label: 'Dönüşüm Oranı', value: '%4,82', delta: '+0,6%' },
  { label: 'Ortalama Sepet', value: '₺428', delta: '+3,1%' },
];

const TOP_PRODUCTS = [
  { name: 'Nike Air Max 90', sales: '₺42.100' },
  { name: 'Essential Tişört', sales: '₺28.640' },
  { name: 'Deri Sırt Çantası', sales: '₺19.980' },
];

const CHANNELS = [
  { name: 'Online Mağaza', share: 68 },
  { name: 'Instagram', share: 22 },
  { name: 'Diğer', share: 10 },
];

const CHART_POINTS = [40, 52, 48, 61, 58, 70, 66, 78, 74, 88, 82, 96];

function LineChart() {
  const max = Math.max(...CHART_POINTS);
  const width = 600;
  const height = 200;
  const step = width / (CHART_POINTS.length - 1);
  const linePoints = CHART_POINTS.map((point, index) => `${index * step},${height - (point / max) * height}`).join(' ');
  const areaPoints = `0,${height} ${linePoints} ${width},${height}`;

  return (
    <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-40 w-full sm:h-52">
      <defs>
        <linearGradient id="analytics-chart-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#FF6B00" stopOpacity="0.25" />
          <stop offset="100%" stopColor="#FF6B00" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon points={areaPoints} fill="url(#analytics-chart-fill)" />
      <polyline
        points={linePoints}
        fill="none"
        stroke="#FF6B00"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}

export function Analytics() {
  return (
    <section id="analitik" className="px-6 py-24 lg:px-8 lg:py-32">
      <Container>
        <SectionHeading
          title={
            <>
              Veriye bakma.
              <br />
              <span className="text-primary">Ne olduğunu anla.</span>
            </>
          }
        />

        <div className="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {STATS.map((stat) => (
            <Reveal key={stat.label}>
              <div className="rounded-2xl border border-dark/[0.07] bg-white p-5">
                <p className="text-xs font-medium text-dark/40">{stat.label}</p>
                <p className="mt-1.5 text-xl font-bold text-dark sm:text-2xl">{stat.value}</p>
                <p className="mt-1 text-xs font-semibold text-primary">{stat.delta}</p>
              </div>
            </Reveal>
          ))}
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
          <AuraCard intensity="subtle" className="rounded-2xl lg:col-span-2">
            <div className="rounded-2xl border border-dark/[0.07] bg-white p-6">
              <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-dark/60">Gelir trendi</p>
                <span className="text-xs font-medium text-dark/30">Son 30 gün</span>
              </div>
              <div className="mt-4">
                <LineChart />
              </div>
            </div>
          </AuraCard>

          <div className="flex flex-col gap-4">
            <Reveal>
              <div className="rounded-2xl border border-dark/[0.07] bg-white p-5">
                <p className="text-xs font-bold uppercase tracking-wider text-dark/35">En Çok Satanlar</p>
                <div className="mt-3 flex flex-col gap-2.5">
                  {TOP_PRODUCTS.map((product) => (
                    <div key={product.name} className="flex items-center justify-between text-sm">
                      <span className="text-dark/70">{product.name}</span>
                      <span className="font-semibold text-dark">{product.sales}</span>
                    </div>
                  ))}
                </div>
              </div>
            </Reveal>

            <Reveal delay={0.1}>
              <div className="rounded-2xl border border-dark/[0.07] bg-white p-5">
                <p className="text-xs font-bold uppercase tracking-wider text-dark/35">Kanal Performansı</p>
                <div className="mt-3 flex flex-col gap-2.5">
                  {CHANNELS.map((channel) => (
                    <div key={channel.name}>
                      <div className="flex items-center justify-between text-xs text-dark/60">
                        <span>{channel.name}</span>
                        <span className="font-semibold">%{channel.share}</span>
                      </div>
                      <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-dark/[0.06]">
                        <div className="h-full rounded-full bg-primary" style={{ width: `${channel.share}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </Reveal>

            <Reveal delay={0.2}>
              <div className="flex items-center gap-3 rounded-2xl border border-dark/[0.07] bg-white p-5">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-orange text-primary">
                  <TrendingUp className="h-5 w-5" strokeWidth={2} />
                </span>
                <div>
                  <p className="text-sm font-bold text-dark">Müşteri büyümesi</p>
                  <p className="text-xs text-dark/40">Son 90 günde +%24 yeni müşteri</p>
                </div>
              </div>
            </Reveal>
          </div>
        </div>
      </Container>
    </section>
  );
}
