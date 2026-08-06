import type { ReactNode } from 'react';
import { useIntersection } from '../../hooks/useIntersection';

interface FocusCornersProps {
  children: ReactNode;
  className?: string;
}

/**
 * Four small "recognition corners" that animate inward ~6px once an
 * important product mockup scrolls into view (dashboard, theme builder,
 * checkout, analytics, integration demos). Plays once — see
 * .focus-corner-* in resources/css/focus-spectrum.css for the per-corner
 * color sequence (orange/purple/blue/magenta) and inward-motion offsets.
 */
export function FocusCorners({ children, className = '' }: FocusCornersProps) {
  const { ref, isIntersecting } = useIntersection<HTMLDivElement>({ threshold: 0.3 });
  const visible = isIntersecting ? 'focus-corner--visible' : '';

  return (
    <div ref={ref} className={`relative ${className}`}>
      {children}
      <span className={`focus-corner focus-corner-tl ${visible}`} aria-hidden="true" />
      <span className={`focus-corner focus-corner-tr ${visible}`} aria-hidden="true" />
      <span className={`focus-corner focus-corner-bl ${visible}`} aria-hidden="true" />
      <span className={`focus-corner focus-corner-br ${visible}`} aria-hidden="true" />
    </div>
  );
}
