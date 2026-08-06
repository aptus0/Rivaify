import type { ReactNode } from 'react';

type BadgeTone = 'success' | 'warning' | 'neutral' | 'primary';

interface BadgeProps {
  tone?: BadgeTone;
  children: ReactNode;
}

const TONE_CLASSES: Record<BadgeTone, string> = {
  success: 'bg-emerald-50 text-emerald-700',
  warning: 'bg-amber-50 text-amber-700',
  neutral: 'bg-app-bg text-muted',
  primary: 'bg-surface-orange text-primary-hover',
};

export function Badge({ tone = 'neutral', children }: BadgeProps) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${TONE_CLASSES[tone]}`}
    >
      {children}
    </span>
  );
}

/** A tone-mapped Badge with a status dot, for store/order/product lifecycle
 * states (brief §15: "● Yayında" / "● İncelemede"). */
export function StatusBadge({ tone, label }: { tone: BadgeTone; label: string }) {
  const dotClass: Record<BadgeTone, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    neutral: 'bg-muted',
    primary: 'bg-primary',
  };

  return (
    <Badge tone={tone}>
      <span className={`h-1.5 w-1.5 rounded-full ${dotClass[tone]}`} />
      {label}
    </Badge>
  );
}
