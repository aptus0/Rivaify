import { motion } from 'framer-motion';
import { Camera, Package, PlusCircle, Wallet, type LucideIcon } from 'lucide-react';
import { useReducedMotion } from '../../../../hooks/useReducedMotion';

interface Chip {
  icon: LucideIcon;
  label: string;
  position: string;
  delay: number;
  duration: number;
}

// Exactly four, per brief — "never make the interface chaotic."
const CHIPS: Chip[] = [
  { icon: PlusCircle, label: 'Yeni sipariş', position: '-left-4 -top-6 sm:-left-8', delay: 0, duration: 5 },
  { icon: Package, label: 'Stok senkronize', position: '-right-3 top-10 sm:-right-8', delay: 0.6, duration: 6 },
  { icon: Camera, label: 'Instagram kataloğu', position: '-left-3 bottom-16 sm:-left-10', delay: 1.1, duration: 5.5 },
  { icon: Wallet, label: '₺4.840 bugün', position: '-right-4 -bottom-5 sm:-right-6', delay: 0.3, duration: 6.5 },
];

export function HomeFloatingChips() {
  const reducedMotion = useReducedMotion();

  return (
    <>
      {CHIPS.map((chip) => (
        <motion.div
          key={chip.label}
          animate={reducedMotion ? undefined : { y: [0, -6, 0] }}
          transition={{ duration: chip.duration, repeat: Infinity, ease: 'easeInOut', delay: chip.delay }}
          className={`absolute z-20 hidden items-center gap-2 rounded-control border border-white/[0.07] bg-[#0c0c0c] px-3.5 py-2.5 shadow-floating sm:flex ${chip.position}`}
        >
          <span className="flex h-8 w-8 items-center justify-center rounded-control bg-primary/20 text-primary">
            <chip.icon className="h-4 w-4" strokeWidth={2.5} />
          </span>
          <p className="text-sm font-bold text-white">{chip.label}</p>
        </motion.div>
      ))}
    </>
  );
}
