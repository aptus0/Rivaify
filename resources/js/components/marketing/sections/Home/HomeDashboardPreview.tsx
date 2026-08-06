import {
  BarChart3,
  Home,
  Megaphone,
  Package,
  ShoppingCart,
  Store,
  TrendingUp,
  Users,
} from 'lucide-react';

const SIDEBAR_ITEMS = [
  { label: 'Ana Sayfa', icon: Home, active: true },
  { label: 'Siparişler', icon: ShoppingCart, active: false },
  { label: 'Ürünler', icon: Package, active: false },
  { label: 'Müşteriler', icon: Users, active: false },
  { label: 'Pazarlama', icon: Megaphone, active: false },
  { label: 'Analitik', icon: BarChart3, active: false },
  { label: 'Online Mağaza', icon: Store, active: false },
] as const;

const STAT_CARDS = [
  { label: 'Satış', value: '₺184.920', delta: '+18,4%' },
  { label: 'Sipariş', value: '1.248', delta: '+9,2%' },
  { label: 'Müşteri', value: '4.892', delta: '+3,8%' },
  { label: 'Dönüşüm', value: '%4,82', delta: '+0,6%' },
] as const;

const RECENT_ORDERS = [
  { id: '#RV-2043', customer: 'Elif Kaya', amount: '₺1.240', status: 'Tamamlandı' },
  { id: '#RV-2042', customer: 'Mert Yıldız', amount: '₺860', status: 'Hazırlanıyor' },
  { id: '#RV-2041', customer: 'Aylin Demir', amount: '₺2.150', status: 'Tamamlandı' },
] as const;

const CHART_POINTS = [28, 42, 35, 55, 48, 66, 58, 74, 64, 82, 76, 92];

function MiniChart() {
  const max = Math.max(...CHART_POINTS);
  const width = 100;
  const height = 100;
  const step = width / (CHART_POINTS.length - 1);
  const linePoints = CHART_POINTS.map((point, index) => `${index * step},${height - (point / max) * height}`).join(' ');
  const areaPoints = `0,${height} ${linePoints} ${width},${height}`;

  return (
    <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-20 w-full">
      <defs>
        <linearGradient id="home-chart-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#FF6B00" stopOpacity="0.22" />
          <stop offset="100%" stopColor="#FF6B00" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon points={areaPoints} fill="url(#home-chart-fill)" />
      <polyline
        points={linePoints}
        fill="none"
        stroke="#FF6B00"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}

/** The hero's centerpiece — an original Rivaify merchant-OS visualization,
 * not a copy of any specific SaaS dashboard. All figures are illustrative
 * mockup data (like any product screenshot in marketing), not live claims. */
export function HomeDashboardPreview() {
  return (
    <div className="overflow-hidden rounded-window border border-white/[0.08] bg-[#0c0c0c] shadow-[0_10px_40px_rgba(0,0,0,0.5)]">
      <div className="flex items-center gap-1.5 border-b border-white/[0.06] bg-[#141414] px-4 py-3">
        <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
        <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
        <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
        <span className="ml-3 rounded-control bg-[#0c0c0c] px-3 py-1 text-[11px] font-medium text-white/40">
          app.rivaify.com/panel
        </span>
      </div>

      <div className="flex">
        <aside className="hidden w-52 shrink-0 border-r border-white/[0.06] bg-[#141414]/60 p-4 sm:block">
          <div className="mb-6 px-2">
            <span className="text-base font-extrabold tracking-tight text-white">
              Riva<span className="text-primary">ify</span>
            </span>
          </div>
          <nav className="flex flex-col gap-1">
            {SIDEBAR_ITEMS.map((item) => (
              <div
                key={item.label}
                className={`flex items-center gap-2.5 rounded-control px-3 py-2 text-[13px] font-medium ${
                  item.active ? 'bg-primary text-white' : 'text-white/50'
                }`}
              >
                <item.icon className="h-4 w-4" strokeWidth={2} />
                {item.label}
              </div>
            ))}
          </nav>
        </aside>

        <div className="min-w-0 flex-1 p-5 sm:p-7">
          <div className="mb-6 flex items-center justify-between">
            <div>
              <h3 className="text-lg font-bold text-white">Genel Bakış</h3>
              <p className="text-xs text-white/40">Mağazanın performansına hızlı bir bakış</p>
            </div>
            <div className="hidden h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/20 text-sm font-bold text-primary sm:flex">
              R
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {STAT_CARDS.map((card) => (
              <div key={card.label} className="rounded-control border border-white/[0.06] bg-[#0c0c0c] p-4">
                <p className="text-[11px] font-medium text-white/40">{card.label}</p>
                <p className="mt-1.5 text-lg font-bold text-white sm:text-xl">{card.value}</p>
                <p className="mt-1 text-[11px] font-semibold text-primary">{card.delta}</p>
              </div>
            ))}
          </div>

          <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5">
            <div className="rounded-control border border-white/[0.06] bg-[#0c0c0c] p-4 lg:col-span-3">
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-white/60">Bugünkü satışlar</p>
                <span className="text-[11px] font-medium text-white/30">Son 12 saat</span>
              </div>
              <div className="mt-3">
                <MiniChart />
              </div>
            </div>

            <div className="rounded-control border border-white/[0.06] bg-[#0c0c0c] p-4 lg:col-span-2">
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-white/60">Son siparişler</p>
                <span className="text-[11px] font-medium text-primary">Tümü</span>
              </div>
              <div className="mt-3 flex flex-col gap-2.5">
                {RECENT_ORDERS.map((order) => (
                  <div key={order.id} className="flex items-center justify-between text-[11px]">
                    <div>
                      <p className="font-semibold text-white">{order.id}</p>
                      <p className="text-white/40">{order.customer}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-semibold text-white">{order.amount}</p>
                      <p className="text-white/40">{order.status}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="mt-4 flex items-center justify-between rounded-control border border-white/[0.06] bg-[#0c0c0c] p-4">
            <div className="flex items-center gap-2.5">
              <span className="flex h-8 w-8 items-center justify-center rounded-control bg-primary/20 text-primary">
                <TrendingUp className="h-4 w-4" strokeWidth={2.5} />
              </span>
              <div>
                <p className="text-xs font-semibold text-white">Stok durumu sağlıklı</p>
                <p className="text-[11px] text-white/40">248 üründe stok senkronize</p>
              </div>
            </div>
            <div className="hidden h-1.5 w-28 overflow-hidden rounded-full bg-dark/[0.06] sm:block">
              <div className="h-full w-[86%] rounded-full bg-primary" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
