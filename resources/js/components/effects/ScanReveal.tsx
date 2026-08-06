import type { ReactNode } from 'react';

interface ScanRevealProps {
  className?: string;
  children: ReactNode;
}

/**
 * Wraps a preview surface (storefront/dashboard mock) with a faint scan-line
 * sweep that plays once on hover — "Rivaify is reading the interface", not
 * a laser scanner. Pure CSS (.spectrum-scan in resources/css/focus-spectrum.css),
 * triggered by the standard Tailwind `group`/`group-hover` pattern.
 */
export function ScanReveal({ className = '', children }: ScanRevealProps) {
  return (
    <div className={`group relative ${className}`}>
      {children}
      <div className="spectrum-scan" aria-hidden="true" />
    </div>
  );
}
