import type { ReactNode } from 'react';
import { usePointerPosition } from '../../hooks/usePointerPosition';
import { useReducedMotion } from '../../hooks/useReducedMotion';

interface PointerGlowProps {
  className?: string;
  children: ReactNode;
}

/**
 * Room-scale ambient cursor glow for hero/product sections — desktop only,
 * very faint (see .pointer-glow in resources/css/focus-spectrum.css for the
 * actual 0.08-alpha radial gradient). Never replaces the native cursor.
 * CSS already disables this on touch (`@media (hover: none)`) and reduced
 * motion; skipping the pointermove listener here too avoids doing the work
 * at all rather than relying purely on the CSS to hide the result.
 */
export function PointerGlow({ className = '', children }: PointerGlowProps) {
  const { ref, handlePointerMove, handlePointerLeave } = usePointerPosition<HTMLDivElement>();
  const reducedMotion = useReducedMotion();

  return (
    <div
      ref={reducedMotion ? undefined : ref}
      onPointerMove={reducedMotion ? undefined : handlePointerMove}
      onPointerLeave={reducedMotion ? undefined : handlePointerLeave}
      className={`relative isolate ${className}`}
    >
      {!reducedMotion && <div aria-hidden="true" className="pointer-glow pointer-glow--active" />}
      {children}
    </div>
  );
}
