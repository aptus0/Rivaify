import { motion } from 'framer-motion';
import { Package, PlusCircle, Share2, Wallet, type LucideIcon } from 'lucide-react';
import { useReducedMotion } from '../../../hooks/useReducedMotion';

interface Chip {
  icon: LucideIcon;
  label: string;
  position: string;
  delay: number;
  duration: number;
}

const CHIPS: Chip[] = [
  { icon: PlusCircle, label: 'Yeni Sipariş', position: '-left-4 -top-6 sm:-left-8', delay: 0, duration: 5 },
  { icon: Share2, label: 'Kanallar bağlı', position: '-right-3 top-10 sm:-right-8', delay: 0.6, duration: 6 },
  { icon: Package, label: 'Stok senkronize', position: '-left-3 bottom-16 sm:-left-10', delay: 1.1, duration: 5.5 },
  { icon: Wallet, label: '₺4.840 bugün', position: '-right-4 -bottom-5 sm:-right-6', delay: 0.3, duration: 6.5 },
];

/** 3–4 small floating chips around the hero dashboard preview — kept sparse
 * and slow-moving on purpose (brief: "never make the interface chaotic"). */
export function FloatingChips() {
  const reducedMotion = useReducedMotion();

  return (
    <>
      {CHIPS.map((chip) => (
        <motion.div
          key={chip.label}
          animate={reducedMotion ? undefined : { y: [0, -7, 0] }}
          transition={{ duration: chip.duration, repeat: Infinity, ease: 'easeInOut', delay: chip.delay }}
          className={`absolute z-20 hidden items-center gap-2 rounded-xl border border-dark/[0.07] bg-white px-3.5 py-2.5 shadow-[0_12px_28px_-10px_rgba(13,17,23,0.2)] sm:flex ${chip.position}`}
        >
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-orange text-primary">
            <chip.icon className="h-4 w-4" strokeWidth={2.5} />
          </span>
          <p className="text-sm font-bold text-dark">{chip.label}</p>
        </motion.div>
      ))}
    </>
  );
}
