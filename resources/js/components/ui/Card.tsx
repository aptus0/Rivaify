import type { ReactNode } from 'react';

type CardTone = 'light' | 'dark';

interface CardProps {
  children: ReactNode;
  tone?: CardTone;
  padding?: 'sm' | 'md' | 'lg';
  className?: string;
}

const PADDING = { sm: 'p-4', md: 'p-6', lg: 'p-7' } as const;

/** Plain neutral surface — pair with AuraCard when a card should also carry
 * the Chromatic Aura hover effect; this is just the shell (border/bg/radius). */
export function Card({ children, tone = 'light', padding = 'md', className = '' }: CardProps) {
  const toneClass =
    tone === 'light' ? 'border-dark/[0.07] bg-white' : 'border-white/10 bg-white/[0.03] text-white';

  return (
    <div className={`rounded-2xl border ${toneClass} ${PADDING[padding]} ${className}`}>{children}</div>
  );
}
