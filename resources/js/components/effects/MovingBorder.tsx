import type { CSSProperties, ReactNode } from 'react';

interface MovingBorderProps {
  /** Ring line thickness in px. Defaults to 1. */
  width?: number;
  /** Full rotation duration in seconds — lower is faster. Defaults to 8s. */
  speed?: number;
  opacity?: number;
  paused?: boolean;
  className?: string;
  children: ReactNode;
}

/** A standalone rotating conic-gradient ring, no halo — the lighter-weight
 * sibling of RivaCard's spectrum variant for chrome that wants the
 * signature ring without pointer tracking (mega menu tiles, primary CTAs,
 * the Payments page's provider-switch transition). */
export function MovingBorder({ width = 1, speed = 8, opacity = 1, paused = false, className = '', children }: MovingBorderProps) {
  const style = {
    '--moving-border-width': `${width}px`,
    '--moving-border-speed': `${speed}s`,
    '--moving-border-opacity': opacity,
    '--moving-border-play': paused ? 'paused' : 'running',
  } as CSSProperties;

  return (
    <div className={`moving-border ${className}`} style={style}>
      {children}
    </div>
  );
}
