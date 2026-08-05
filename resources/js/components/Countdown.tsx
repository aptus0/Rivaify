import { AnimatePresence, motion } from 'framer-motion';
import { useCountdown } from '../hooks/useCountdown';
import { LAUNCH_DATE } from '../constants/launch';

interface UnitCardProps {
  value: number;
  label: string;
  delay: number;
}

function UnitCard({ value, label, delay }: UnitCardProps) {
  const display = String(value).padStart(2, '0');

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ duration: 0.5, delay, ease: [0.22, 1, 0.36, 1] }}
      className="flex w-20 flex-col items-center gap-1 rounded-2xl border border-dark/[0.06] bg-white px-4 py-5 shadow-[0_2px_16px_-4px_rgba(17,17,17,0.06)] sm:w-28 sm:py-6"
    >
      <div className="relative h-9 overflow-hidden sm:h-11">
        <AnimatePresence mode="popLayout">
          <motion.span
            key={display}
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            transition={{ duration: 0.3, ease: 'easeOut' }}
            className="block text-3xl font-extrabold tabular-nums text-dark sm:text-4xl"
          >
            {display}
          </motion.span>
        </AnimatePresence>
      </div>
      <span className="text-[11px] font-semibold uppercase tracking-wider text-primary">{label}</span>
    </motion.div>
  );
}

export function Countdown() {
  const { days, hours, minutes, seconds } = useCountdown(LAUNCH_DATE);

  const units = [
    { value: days, label: 'Gün' },
    { value: hours, label: 'Saat' },
    { value: minutes, label: 'Dakika' },
    { value: seconds, label: 'Saniye' },
  ];

  return (
    <section className="border-y border-dark/[0.06] bg-surface-orange/40 py-16">
      <div className="mx-auto max-w-4xl px-6 text-center lg:px-8">
        <p className="text-sm font-semibold uppercase tracking-wide text-dark/40">
          Rivaify&apos;ın lansmanına kalan süre
        </p>

        <div className="mt-8 flex items-center justify-center gap-3 sm:gap-5">
          {units.map((unit, index) => (
            <UnitCard key={unit.label} value={unit.value} label={unit.label} delay={index * 0.08} />
          ))}
        </div>
      </div>
    </section>
  );
}
