import type { ReactNode } from 'react';
import { usePointerPosition } from '../../hooks/usePointerPosition';

interface CursorAuraProps {
  className?: string;
  children: ReactNode;
}

/**
 * A room-scale version of AuraCard's halo — for sections (hero, final CTA)
 * where the whole section should feel like it's tracking the pointer, not
 * just one card. Renders its own stacking context so the glow sits behind
 * `children` regardless of their own z-index.
 */
export function CursorAura({ className = '', children }: CursorAuraProps) {
  const { ref, handlePointerMove, handlePointerLeave } = usePointerPosition<HTMLDivElement>();

  return (
    <div
      ref={ref}
      onPointerMove={handlePointerMove}
      onPointerLeave={handlePointerLeave}
      className={`relative isolate ${className}`}
    >
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 -z-10 opacity-70 [background:radial-gradient(560px_circle_at_var(--mouse-x,50%)_var(--mouse-y,20%),color-mix(in_srgb,var(--aura-orange)_16%,transparent),color-mix(in_srgb,var(--aura-purple)_10%,transparent)_45%,transparent_72%)] motion-reduce:hidden"
      />
      {children}
    </div>
  );
}
