import { motion } from 'framer-motion';
import {
  BarChart3,
  Home,
  Megaphone,
  Package,
  Plug,
  ShoppingBag,
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
  { label: 'Entegrasyonlar', icon: Plug, active: false },
  { label: 'Online Mağaza', icon: Store, active: false },
  { label: 'Analitik', icon: BarChart3, active: false },
];

const STAT_CARDS = [
  { label: 'Toplam Satış', value: '₺128.450', delta: '+12,4%' },
  { label: 'Sipariş', value: '1.284', delta: '+6,1%' },
  { label: 'Müşteri', value: '4.892', delta: '+3,8%' },
  { label: 'Dönüşüm', value: '%4,8', delta: '+0,6%' },
];

const RECENT_ORDERS = [
  { id: '#RV-2043', customer: 'Elif Kaya', amount: '₺1.240', status: 'Tamamlandı' },
  { id: '#RV-2042', customer: 'Mert Yıldız', amount: '₺860', status: 'Hazırlanıyor' },
  { id: '#RV-2041', customer: 'Aylin Demir', amount: '₺2.150', status: 'Tamamlandı' },
  { id: '#RV-2040', customer: 'Can Öztürk', amount: '₺430', status: 'Kargoda' },
];

const CHART_POINTS = [28, 42, 35, 55, 48, 66, 58, 74, 64, 82, 76, 92];

function MiniChart() {
  const max = Math.max(...CHART_POINTS);
  const width = 100;
  const height = 100;
  const step = width / (CHART_POINTS.length - 1);

  const linePoints = CHART_POINTS.map((point, index) => {
    const x = index * step;
    const y = height - (point / max) * height;
    return `${x},${y}`;
  }).join(' ');

  const areaPoints = `0,${height} ${linePoints} ${width},${height}`;

  return (
    <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-24 w-full">
      <defs>
        <linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#FF6B00" stopOpacity="0.22" />
          <stop offset="100%" stopColor="#FF6B00" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon points={areaPoints} fill="url(#chart-fill)" />
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

export function DashboardPreview() {
  return (
    <section id="platform" className="relative px-6 pb-24 pt-4 lg:px-8 lg:pb-32">
      <div className="mx-auto max-w-6xl">
        <motion.div
          initial={{ opacity: 0, y: 40, rotateX: 8 }}
          whileInView={{ opacity: 1, y: 0, rotateX: 0 }}
          viewport={{ once: true, amount: 0.2 }}
          transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
          style={{ perspective: 1600 }}
          className="relative"
        >
          {/* floating badge - top left */}
          <motion.div
            animate={{ y: [0, -10, 0] }}
            transition={{ duration: 5, repeat: Infinity, ease: 'easeInOut' }}
            className="absolute -left-4 -top-6 z-20 hidden items-center gap-2 rounded-xl border border-dark/[0.06] bg-white px-3.5 py-2.5 shadow-[0_8px_24px_-8px_rgba(17,17,17,0.15)] sm:flex"
          >
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-orange text-primary">
              <TrendingUp className="h-4 w-4" strokeWidth={2.5} />
            </span>
            <div>
              <p className="text-[11px] font-medium text-dark/40">Bugünkü satışlar</p>
              <p className="text-sm font-bold text-dark">₺18.240</p>
            </div>
          </motion.div>

          {/* floating badge - bottom right */}
          <motion.div
            animate={{ y: [0, 10, 0] }}
            transition={{ duration: 6, repeat: Infinity, ease: 'easeInOut', delay: 0.5 }}
            className="absolute -bottom-6 -right-4 z-20 hidden items-center gap-2 rounded-xl border border-dark/[0.06] bg-white px-3.5 py-2.5 shadow-[0_8px_24px_-8px_rgba(17,17,17,0.15)] sm:flex"
          >
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-orange text-primary">
              <ShoppingBag className="h-4 w-4" strokeWidth={2.5} />
            </span>
            <div>
              <p className="text-[11px] font-medium text-dark/40">Yeni sipariş</p>
              <p className="text-sm font-bold text-dark">#RV-2044</p>
            </div>
          </motion.div>

          <div className="overflow-hidden rounded-2xl border border-dark/[0.08] bg-white shadow-[0_40px_80px_-30px_rgba(17,17,17,0.35)]">
            {/* window chrome */}
            <div className="flex items-center gap-1.5 border-b border-dark/[0.06] bg-surface px-4 py-3">
              <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
              <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
              <span className="h-2.5 w-2.5 rounded-full bg-dark/15" />
              <span className="ml-3 rounded-md bg-white px-3 py-1 text-[11px] font-medium text-dark/40">
                app.rivaify.com/panel
              </span>
            </div>

            <div className="flex">
              {/* sidebar */}
              <aside className="hidden w-52 shrink-0 border-r border-dark/[0.06] bg-surface/60 p-4 sm:block">
                <div className="mb-6 px-2">
                  <span className="text-base font-extrabold tracking-tight text-dark">
                    Riva<span className="text-primary">ify</span>
                  </span>
                </div>
                <nav className="flex flex-col gap-1">
                  {SIDEBAR_ITEMS.map((item) => (
                    <div
                      key={item.label}
                      className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium ${
                        item.active ? 'bg-primary text-white' : 'text-dark/50'
                      }`}
                    >
                      <item.icon className="h-4 w-4" strokeWidth={2} />
                      {item.label}
                    </div>
                  ))}
                </nav>
              </aside>

              {/* main content */}
              <div className="min-w-0 flex-1 p-5 sm:p-7">
                <div className="mb-6 flex items-center justify-between">
                  <div>
                    <h3 className="text-lg font-bold text-dark">Genel Bakış</h3>
                    <p className="text-xs text-dark/40">Mağazanın performansına hızlı bir bakış</p>
                  </div>
                  <div className="hidden h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-orange text-sm font-bold text-primary sm:flex">
                    R
                  </div>
                </div>

                {/* stat cards */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                  {STAT_CARDS.map((card) => (
                    <div
                      key={card.label}
                      className="rounded-xl border border-dark/[0.06] bg-white p-4"
                    >
                      <p className="text-[11px] font-medium text-dark/40">{card.label}</p>
                      <p className="mt-1.5 text-lg font-bold text-dark sm:text-xl">{card.value}</p>
                      <p className="mt-1 text-[11px] font-semibold text-primary">{card.delta}</p>
                    </div>
                  ))}
                </div>

                {/* chart + orders */}
                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5">
                  <div className="rounded-xl border border-dark/[0.06] bg-white p-4 lg:col-span-3">
                    <div className="flex items-center justify-between">
                      <p className="text-xs font-semibold text-dark/60">Bugünkü satışlar</p>
                      <span className="text-[11px] font-medium text-dark/30">Son 12 saat</span>
                    </div>
                    <div className="mt-3">
                      <MiniChart />
                    </div>
                  </div>

                  <div className="rounded-xl border border-dark/[0.06] bg-white p-4 lg:col-span-2">
                    <p className="text-xs font-semibold text-dark/60">Son siparişler</p>
                    <div className="mt-3 flex flex-col gap-2.5">
                      {RECENT_ORDERS.map((order) => (
                        <div key={order.id} className="flex items-center justify-between text-[11px]">
                          <div>
                            <p className="font-semibold text-dark">{order.id}</p>
                            <p className="text-dark/40">{order.customer}</p>
                          </div>
                          <div className="text-right">
                            <p className="font-semibold text-dark">{order.amount}</p>
                            <p className="text-dark/40">{order.status}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
