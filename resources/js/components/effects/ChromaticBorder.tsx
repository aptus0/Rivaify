import type { CSSProperties, ReactNode } from 'react';

interface ChromaticBorderProps {
  /** Ring line thickness in px. Defaults to 1 (brief: "1px gradient perimeter"). */
  width?: number;
  /** Full rotation duration in seconds — lower is faster. Defaults to 8s. */
  speed?: number;
  /** 0–1. Defaults to 1 (fully visible) — callers that only want it on hover
   * should toggle this via their own hover state instead of relying on CSS
   * :hover, since ChromaticBorder is meant for always-on decorative use
   * (mega menu cards, nav CTA) rather than AuraCard's pointer-reactive one. */
  opacity?: number;
  paused?: boolean;
  className?: string;
  children: ReactNode;
}

/** A standalone rotating conic-gradient ring, no halo — the lighter-weight
 * sibling of AuraCard for chrome that wants the signature ring without the
 * pointer-tracking glow (e.g. mega menu tiles, the primary nav CTA). */
export function ChromaticBorder({
  width = 1,
  speed = 8,
  opacity = 1,
  paused = false,
  className = '',
  children,
}: ChromaticBorderProps) {
  const style = {
    '--chromatic-border-width': `${width}px`,
    '--chromatic-border-speed': `${speed}s`,
    '--chromatic-border-opacity': opacity,
    '--chromatic-border-play': paused ? 'paused' : 'running',
  } as CSSProperties;

  return (
    <div className={`chromatic-border ${className}`} style={style}>
      {children}
    </div>
  );
}
