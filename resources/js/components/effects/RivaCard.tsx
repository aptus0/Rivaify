import type { CSSProperties, HTMLAttributes, ReactNode } from 'react';
import { usePointerPosition } from '../../hooks/usePointerPosition';

type RivaCardVariant = 'default' | 'subtle' | 'dark' | 'glass' | 'highlight' | 'spectrum';
type Intensity = 'subtle' | 'medium' | 'strong';

const INTENSITY_MAP: Record<Intensity, { ring: number; halo: number }> = {
  subtle: { ring: 0.45, halo: 0.32 },
  medium: { ring: 0.75, halo: 0.5 },
  strong: { ring: 1, halo: 0.65 },
};

// Tailwind's scanner needs full literal class names in source — template
// interpolation like `rounded-${radius}` is invisible to it, hence a map.
const RADIUS_CLASSES = {
  control: 'rounded-control',
  card: 'rounded-card',
  showcase: 'rounded-showcase',
  window: 'rounded-window',
} as const;

const VARIANT_CLASSES: Record<RivaCardVariant, string> = {
  default: 'border border-white/10 bg-white/[0.03] text-white shadow-lg backdrop-blur-sm',
  subtle: 'border border-white/5 bg-white/[0.02] text-white/90',
  dark: 'border border-white/10 bg-black/60 text-white backdrop-blur-md',
  glass: 'border border-white/15 bg-white/[0.06] text-white backdrop-blur-xl',
  highlight: 'border-2 border-primary/40 bg-primary/10 text-white shadow-[0_0_15px_rgba(255,107,0,0.1)]',
  // spectrum's own visible surface is transparent — the riva-card::before/
  // ::after pseudo-elements (focus-spectrum.css) supply the border+glow.
  spectrum: 'border border-white/10 bg-white/[0.03] text-white',
};

interface RivaCardProps extends HTMLAttributes<HTMLDivElement> {
  variant?: RivaCardVariant;
  /** Only meaningful for variant="spectrum" — how strong the ring/halo get at full hover. */
  intensity?: Intensity;
  /** Keeps the spectrum effect faintly visible without hovering. */
  ambient?: boolean;
  /** Set false to skip pointer tracking on a spectrum card that never needs it. */
  interactive?: boolean;
  radius?: 'control' | 'card' | 'showcase' | 'window';
  className?: string;
  children: ReactNode;
}

/**
 * The Rivaify semantic card system — a HeroUI-style compound component
 * (RivaCard.Header/Title/Description/Content/Footer) with six variants.
 * variant="spectrum" additionally carries the Focus Spectrum pointer-
 * reactive glow (resources/css/focus-spectrum.css); the other five are
 * purely static visual shells.
 */
export function RivaCard({
  variant = 'default',
  intensity = 'medium',
  ambient = false,
  interactive = true,
  radius = 'card',
  className = '',
  children,
  style,
  ...rest
}: RivaCardProps) {
  const { ref, handlePointerMove, handlePointerLeave } = usePointerPosition<HTMLDivElement>();
  const isSpectrum = variant === 'spectrum';
  const { ring, halo } = INTENSITY_MAP[intensity];

  const spectrumStyle = isSpectrum
    ? ({
        '--riva-card-opacity': ring,
        '--riva-card-halo-opacity': halo,
        '--riva-card-ambient-opacity': ring * 0.6,
        '--riva-card-halo-ambient-opacity': halo * 0.55,
      } as CSSProperties)
    : undefined;

  return (
    <div
      ref={isSpectrum && interactive ? ref : undefined}
      onPointerMove={isSpectrum && interactive ? handlePointerMove : undefined}
      onPointerLeave={isSpectrum && interactive ? handlePointerLeave : undefined}
      style={{ ...spectrumStyle, ...style }}
      className={`${RADIUS_CLASSES[radius]} ${VARIANT_CLASSES[variant]} ${isSpectrum ? `riva-card ${ambient ? 'riva-card--ambient' : ''}` : ''} ${className}`}
      {...rest}
    >
      {children}
    </div>
  );
}

RivaCard.Header = function RivaCardHeader({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`flex items-start justify-between gap-4 ${className}`}>{children}</div>;
};

RivaCard.Title = function RivaCardTitle({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <h3 className={`text-base font-bold ${className}`}>{children}</h3>;
};

RivaCard.Description = function RivaCardDescription({
  children,
  className = '',
}: {
  children: ReactNode;
  className?: string;
}) {
  return <p className={`mt-1.5 text-sm leading-relaxed opacity-60 ${className}`}>{children}</p>;
};

RivaCard.Content = function RivaCardContent({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`mt-4 ${className}`}>{children}</div>;
};

RivaCard.Footer = function RivaCardFooter({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`mt-4 flex items-center justify-between gap-3 ${className}`}>{children}</div>;
};
