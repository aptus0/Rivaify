interface SpotlightProps {
  className?: string;
  /** CSS color to center the spotlight on — defaults to brand orange. */
  color?: string;
}

/**
 * A large, static (non-pointer-tracked) decorative radial highlight for
 * hero backdrops — the Aceternity-style "Spotlight", reinterpreted. Distinct
 * from PointerGlow (which follows the cursor): this is fixed, meant to sit
 * behind a hero as a single ambient wash. Position/size via `className`
 * (e.g. "inset-x-0 top-0 h-[560px]").
 */
export function Spotlight({ className = '', color = 'rgba(255, 107, 0, 0.14)' }: SpotlightProps) {
  return (
    <div
      aria-hidden="true"
      className={`pointer-events-none absolute -z-10 ${className}`}
      style={{ background: `radial-gradient(60% 50% at 50% 0%, ${color}, transparent 70%)` }}
    />
  );
}
