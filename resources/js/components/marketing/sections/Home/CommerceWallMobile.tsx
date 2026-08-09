import { useEffect, useState } from 'react';
import { Camera, CheckCircle2, Truck } from 'lucide-react';
import { useReducedMotion } from '../../../../hooks/useReducedMotion';

const LIVE_STATES = [
  { label: 'Yeni Sipariş', detail: '#10482 · ₺1.249' },
  { label: 'Ödeme başarılı', detail: '#10482 · ₺1.249' },
  { label: 'Stok güncellendi', detail: '24 → 23 adet' },
  { label: 'Gönderi oluşturuldu', detail: 'Yurtiçi Kargo' },
] as const;

const STRIP_CARDS = [
  { icon: Camera, label: 'Instagram', detail: 'Bağlı' },
  { icon: CheckCircle2, label: 'Ödeme Başarılı', detail: '2 sn önce' },
  { icon: Truck, label: 'Gönderi Oluşturuldu', detail: 'Yurtiçi Kargo' },
];

/** Mobile hero visual — not a shrunk desktop wall (brief explicitly rules
 * that out): one featured live panel plus a horizontally scrollable strip,
 * so nothing forces the viewport wider than it is. */
export function CommerceWallMobile() {
  const reducedMotion = useReducedMotion();
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (reducedMotion) return;
    const id = window.setInterval(() => setIndex((current) => (current + 1) % LIVE_STATES.length), 2600);
    return () => window.clearInterval(id);
  }, [reducedMotion]);

  const state = LIVE_STATES[index];

  return (
    <div>
      <div className="rounded-showcase border border-white/10 bg-dark p-5 text-white shadow-floating">
        <p className="text-[11px] font-bold uppercase tracking-wider text-white/40">Canlı sipariş</p>
        <p className="mt-2 text-lg font-bold">{state.label}</p>
        <p className="mt-1 text-sm text-white/50">{state.detail}</p>
      </div>

      <div className="mt-4 -mx-6 flex gap-3 overflow-x-auto px-6 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {STRIP_CARDS.map((card) => (
          <div
            key={card.label}
            className="flex w-[200px] shrink-0 items-center gap-3 rounded-card border border-dark/[0.08] bg-white p-4"
          >
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-surface-orange text-primary">
              <card.icon className="h-4 w-4" strokeWidth={2} />
            </span>
            <div>
              <p className="text-xs font-bold text-dark">{card.label}</p>
              <p className="text-[11px] text-dark/40">{card.detail}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
