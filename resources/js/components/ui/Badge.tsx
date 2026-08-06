import type { ReactNode } from 'react';

type BadgeVariant = 'default' | 'outline' | 'soon' | 'onDark';

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
  default: 'border-primary/20 bg-surface-orange text-primary',
  outline: 'border-dark/10 bg-white text-dark/60',
  soon: 'border-dark/10 bg-dark/[0.04] text-dark/45',
  onDark: 'border-white/15 bg-white/[0.06] text-primary-soft',
};

interface BadgeProps {
  children: ReactNode;
  variant?: BadgeVariant;
  className?: string;
}

/** Status pill — used sparingly. For unreleased integrations, prefer variant="soon"
 * with copy like "Yakında" / "Planlanıyor" rather than implying availability. */
export function Badge({ children, variant = 'default', className = '' }: BadgeProps) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold ${VARIANT_CLASSES[variant]} ${className}`}
    >
      {children}
    </span>
  );
}
