import type { ReactNode } from 'react';

interface BentoGridProps {
  children: ReactNode;
  className?: string;
}

/** A strict 12-column grid — brief: "Avoid random unequal layouts." */
export function BentoGrid({ children, className = '' }: BentoGridProps) {
  return <div className={`grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 ${className}`}>{children}</div>;
}
