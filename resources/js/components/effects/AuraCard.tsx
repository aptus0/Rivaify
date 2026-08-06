import type { CSSProperties, ReactNode } from 'react';
import { usePointerPosition } from '../../hooks/usePointerPosition';

type Intensity = 'subtle' | 'medium' | 'strong';

const INTENSITY_MAP: Record<Intensity, { ring: number; halo: number }> = {
  subtle: { ring: 0.45, halo: 0.32 },
  medium: { ring: 0.75, halo: 0.5 },
  strong: { ring: 1, halo: 0.65 },
};

interface AuraCardProps {
  /** How strong the ring/halo get at full hover. Defaults to 'medium'. */
  intensity?: Intensity;
  /** Keeps the aura faintly visible without hovering — used sparingly (final CTA, hero). */
  ambient?: boolean;
  /** Set false for static/non-hoverable surfaces (still shows the ambient look if ambient=true). */
  interactive?: boolean;
  className?: string;
  children: ReactNode;
}

/**
 * The Rivaify "Chromatic Commerce Aura" card shell — a neutral surface by
 * default, with a pointer-reactive multicolor glow (see resources/css/aura.css
 * for the actual ring + halo implementation). Pointer tracking only runs
 * while the pointer is over this specific card (React's onPointerMove is
 * scoped to the element, not a window listener), and `interactive={false}`
 * skips attaching it entirely for cards that never need it.
 */
export function AuraCard({
  intensity = 'medium',
  ambient = false,
  interactive = true,
  className = '',
  children,
}: AuraCardProps) {
  const { ref, handlePointerMove, handlePointerLeave } = usePointerPosition<HTMLDivElement>();
  const { ring, halo } = INTENSITY_MAP[intensity];

  const style = {
    '--aura-card-opacity': ring,
    '--aura-card-halo-opacity': halo,
    '--aura-card-ambient-opacity': ring * 0.6,
    '--aura-card-halo-ambient-opacity': halo * 0.55,
  } as CSSProperties;

  return (
    <div
      ref={interactive ? ref : undefined}
      onPointerMove={interactive ? handlePointerMove : undefined}
      onPointerLeave={interactive ? handlePointerLeave : undefined}
      style={style}
      className={`aura-card ${ambient ? 'aura-card--ambient' : ''} ${className}`}
    >
      {children}
    </div>
  );
}
