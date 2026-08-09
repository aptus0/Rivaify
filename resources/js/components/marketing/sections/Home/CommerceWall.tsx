import { useRef, useState, useEffect } from 'react';
import { motion, useScroll, useTransform } from 'framer-motion';
import { Camera } from 'lucide-react';
import { RivaCard } from '../../../effects/RivaCard';
import { useReducedMotion } from '../../../../hooks/useReducedMotion';

const CHART_POINTS = [22, 34, 28, 46, 40, 58, 50, 68, 60, 78];

function MiniSparkline() {
  const max = Math.max(...CHART_POINTS);
  const points = CHART_POINTS.map((point, index) => `${(index / (CHART_POINTS.length - 1)) * 100},${36 - (point / max) * 36}`).join(
    ' ',
  );
  return (
    <svg viewBox="0 0 100 36" preserveAspectRatio="none" className="h-9 w-full">
      <polyline points={points} fill="none" stroke="#FF6B00" strokeWidth="3" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

/** Four states the "alive" order card cycles through — the one deliberate
 * differentiator from a static Shopier-style collage: one card visibly
 * carries a single order through Rivaify's pipeline. */
const LIVE_STATES = [
  { label: 'Yeni Sipariş', detail: '#10482 · ₺1.249', dot: 'bg-primary' },
  { label: 'Ödeme başarılı', detail: '#10482 · ₺1.249', dot: 'bg-emerald-400' },
  { label: 'Stok güncellendi', detail: '24 → 23 adet', dot: 'bg-primary' },
  { label: 'Gönderi oluşturuldu', detail: 'Yurtiçi Kargo', dot: 'bg-emerald-400' },
] as const;

function useLiveCardState() {
  const reducedMotion = useReducedMotion();
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (reducedMotion) return;
    const id = window.setInterval(() => setIndex((current) => (current + 1) % LIVE_STATES.length), 2600);
    return () => window.clearInterval(id);
  }, [reducedMotion]);

  return LIVE_STATES[index];
}

function WallCardShell({
  bg = 'white',
  width = 250,
  spectrum = false,
  className = '',
  children,
}: {
  bg?: 'white' | 'peach' | 'soft' | 'gray' | 'dark' | 'primary';
  width?: number;
  spectrum?: boolean;
  className?: string;
  children: React.ReactNode;
}) {
  const BG_CLASSES: Record<typeof bg, string> = {
    white: 'bg-white text-dark border-dark/[0.08]',
    peach: 'bg-[#FFE4CF] text-dark border-transparent',
    soft: 'bg-surface-orange text-dark border-transparent',
    gray: 'bg-[#F4F4F5] text-dark border-transparent',
    dark: 'bg-dark text-white border-white/10',
    primary: 'bg-primary text-white border-transparent',
  };

  const card = (
    <div
      style={{ width }}
      className={`rounded-showcase border p-5 shadow-floating transition-transform duration-300 hover:-translate-y-1 hover:scale-[1.015] ${BG_CLASSES[bg]} ${className}`}
    >
      {children}
    </div>
  );

  if (!spectrum) return card;

  return (
    <RivaCard variant="spectrum" intensity="medium" radius="showcase" className="!border-0 !bg-transparent !p-0 !shadow-none">
      {card}
    </RivaCard>
  );
}

function StoreCard() {
  return (
    <WallCardShell width={260}>
      <p className="text-sm font-bold">Rivaify</p>
      <div className="mt-3 overflow-hidden rounded-control border border-dark/[0.06]">
        <div className="h-8 bg-surface" />
        <div className="grid grid-cols-3 gap-1 bg-white p-1.5">
          <div className="col-span-3 h-12 rounded bg-[#FFE4CF]" />
          <div className="h-8 rounded bg-surface" />
          <div className="h-8 rounded bg-surface" />
          <div className="h-8 rounded bg-surface" />
        </div>
      </div>
      <p className="mt-3 text-[11px] font-medium text-dark/35">rivaify.com</p>
    </WallCardShell>
  );
}

function ProductCard() {
  return (
    <WallCardShell width={220} bg="white">
      <div className="h-20 rounded-control bg-gradient-to-br from-[#FFE4CF] to-surface-orange" />
      <p className="mt-3 text-sm font-bold">Nike Air Max</p>
      <p className="text-[11px] text-dark/40">Black / 42</p>
      <div className="mt-2 flex items-center justify-between">
        <span className="text-sm font-bold text-primary">₺4.499</span>
        <span className="text-[11px] text-dark/35">Stok 24</span>
      </div>
    </WallCardShell>
  );
}

function InstagramCard() {
  return (
    <WallCardShell width={200} bg="gray" className="flex items-center gap-3 py-4">
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white text-primary">
        <Camera className="h-4 w-4" strokeWidth={2} />
      </span>
      <div>
        <p className="text-sm font-bold">Instagram</p>
        <p className="flex items-center gap-1.5 text-[11px] text-dark/40">
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" /> Bağlı
        </p>
      </div>
    </WallCardShell>
  );
}

function LiveOrderCard() {
  const state = useLiveCardState();
  return (
    <WallCardShell width={250} bg="dark" spectrum>
      <p className="text-[11px] font-bold uppercase tracking-wider text-white/40">Canlı sipariş</p>
      <p className="mt-2 text-base font-bold">{state.label}</p>
      <p className="mt-1 text-[13px] text-white/50">{state.detail}</p>
      <p className="mt-4 flex items-center gap-1.5 text-[11px] font-semibold text-white/70">
        <span className={`h-1.5 w-1.5 rounded-full ${state.dot} transition-colors duration-300`} /> Rivaify Payments
      </p>
    </WallCardShell>
  );
}

function CategoryCard() {
  return (
    <WallCardShell width={280} bg="peach">
      <div className="h-24 rounded-control bg-white/50" />
      <p className="mt-3 text-sm font-bold">Kadın Giyim</p>
      <p className="text-[11px] text-dark/45">248 ürün · 6 kategori</p>
    </WallCardShell>
  );
}

function InventoryCard() {
  return (
    <WallCardShell width={200} bg="white">
      <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">Stok</p>
      <p className="mt-2 flex items-baseline gap-2 text-lg font-bold">
        24 <span className="text-dark/25">→</span> <span className="text-primary">23</span>
      </p>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-dark/[0.06]">
        <div className="h-full w-[92%] rounded-full bg-primary" />
      </div>
    </WallCardShell>
  );
}

function AnalyticsCard() {
  return (
    <WallCardShell width={250} spectrum>
      <div className="flex items-center justify-between">
        <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">Rivaify Analitik</p>
        <span className="text-[11px] font-semibold text-primary">+18,4%</span>
      </div>
      <div className="mt-2">
        <MiniSparkline />
      </div>
    </WallCardShell>
  );
}

function PaymentCard() {
  return (
    <WallCardShell width={210} bg="primary" className="flex items-center gap-3 py-4">
      <img
        src="/marketing-icons/payment-badge.png"
        alt=""
        className="h-10 w-10 shrink-0 rounded-full object-contain ring-2 ring-white/40"
      />
      <div>
        <p className="text-sm font-bold">Ödeme Başarılı</p>
        <p className="text-[11px] text-white/75">2 saniye önce</p>
      </div>
    </WallCardShell>
  );
}

function ShippingCard() {
  return (
    <WallCardShell width={230} bg="gray" className="flex items-center gap-3 py-4">
      <img src="/marketing-icons/delivery-truck.png" alt="" className="h-10 w-10 shrink-0 object-contain" />
      <div>
        <p className="text-sm font-bold">Gönderi oluşturuldu</p>
        <p className="text-[11px] text-dark/40">Yurtiçi Kargo</p>
      </div>
    </WallCardShell>
  );
}

const COLUMNS: { cards: (() => React.JSX.Element)[]; parallax: [number, number] }[] = [
  { cards: [StoreCard, ProductCard, InstagramCard], parallax: [0, -80] },
  { cards: [LiveOrderCard, CategoryCard, InventoryCard], parallax: [-40, 40] },
  { cards: [AnalyticsCard, PaymentCard, ShippingCard], parallax: [20, -100] },
];

const COLUMN_ENTRANCE_DELAY = [0.62, 0.7, 0.78] as const;

function WallColumn({ index }: { index: 0 | 1 | 2 }) {
  const reducedMotion = useReducedMotion();
  const column = COLUMNS[index];
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start end', 'end start'] });
  const y = useTransform(scrollYProgress, [0, 1], reducedMotion ? [0, 0] : column.parallax);

  return (
    <motion.div
      ref={ref}
      style={{ y }}
      initial={{ opacity: 0, y: reducedMotion ? 0 : 32, scale: reducedMotion ? 1 : 0.96 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ duration: reducedMotion ? 0.2 : 0.7, delay: reducedMotion ? 0 : COLUMN_ENTRANCE_DELAY[index], ease: [0.22, 1, 0.36, 1] }}
      className={`flex flex-col gap-5 ${index === 1 ? 'mt-16' : index === 2 ? 'mt-32' : ''}`}
    >
      {column.cards.map((Card, cardIndex) => (
        <Card key={cardIndex} />
      ))}
    </motion.div>
  );
}

/** Desktop-only ("Rivaify Commerce Wall") — three columns of store/product/
 * commerce-event/UI cards, staggered downward left-to-right and tilted a
 * few degrees, deliberately bleeding past the section's right/bottom edge.
 * Not a Shopier photo collage: every card is a real Rivaify surface
 * (storefront, order, payment, inventory, shipment, analytics), and one
 * card (LiveOrderCard) actually cycles through an order's lifecycle. */
export function CommerceWall() {
  return (
    <div className="pointer-events-none absolute inset-y-0 right-0 hidden w-[64%] overflow-hidden lg:block xl:w-[58%]">
      <div
        className="pointer-events-auto absolute top-1/2 right-[-8%] grid w-[900px] -translate-y-1/2 grid-cols-3 gap-6 rotate-[5deg]"
        style={{ transformOrigin: 'center' }}
      >
        <WallColumn index={0} />
        <WallColumn index={1} />
        <WallColumn index={2} />
      </div>
    </div>
  );
}
