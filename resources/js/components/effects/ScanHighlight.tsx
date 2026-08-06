import type { ReactNode } from 'react';

interface ScanHighlightProps {
  className?: string;
  children: ReactNode;
}

/**
 * Wraps a preview surface (storefront/dashboard mock) with a faint scan-line
 * sweep that plays once on hover — "AI is understanding the interface", not
 * a laser scanner. Pure CSS (aura-scan in resources/css/aura.css), triggered
 * by the standard Tailwind `group`/`group-hover` pattern already used across
 * this codebase, so it needs no JS state of its own.
 */
export function ScanHighlight({ className = '', children }: ScanHighlightProps) {
  return (
    <div className={`group relative ${className}`}>
      {children}
      <div className="aura-scan" aria-hidden="true" />
    </div>
  );
}
