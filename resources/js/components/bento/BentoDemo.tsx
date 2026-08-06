import type { ReactNode } from 'react';
import { Radio } from 'lucide-react';

interface BentoDemoProps {
  label?: string;
  children: ReactNode;
  className?: string;
}

/** Bento tile variant for a small live/interactive demo — badges itself so
 * visitors know the tile responds to their input. */
export function BentoDemo({ label = 'Canlı önizleme', children, className = '' }: BentoDemoProps) {
  return (
    <div className={className}>
      <div className="mb-3 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-surface-orange px-2.5 py-1 text-[10px] font-semibold text-primary">
        <Radio className="h-3 w-3" strokeWidth={2.5} />
        {label}
      </div>
      {children}
    </div>
  );
}
