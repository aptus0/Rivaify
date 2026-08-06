import { Badge } from '../ui/Badge';
import { getIntegration, STATUS_LABEL } from '../../data/integrations';

type LogoSize = 'sm' | 'md' | 'lg';

const BADGE_SIZE: Record<LogoSize, string> = {
  sm: 'h-8 w-8',
  md: 'h-11 w-11',
  lg: 'h-14 w-14',
};

const GLYPH_SIZE: Record<LogoSize, string> = {
  sm: 'h-3.5 w-3.5',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
};

interface BrandLogoProps {
  brand: string;
  size?: LogoSize;
  monochrome?: boolean;
  showName?: boolean;
  showStatus?: boolean;
  className?: string;
}

/**
 * Centralized brand asset renderer. No real third-party logo files exist in
 * this repo yet (Instagram/Stripe/PayTR/etc. integrations aren't actually
 * built — see data/integrations.ts) — this renders a neutral icon + name
 * today. Once licensed SVGs land under public/brands/{key}.svg, only
 * `Integration.logoPath` needs to be set; this component's API and every
 * call site stay the same.
 */
export function BrandLogo({ brand, size = 'md', monochrome = false, showName = true, showStatus = false, className = '' }: BrandLogoProps) {
  const integration = getIntegration(brand);
  if (!integration) return null;

  const Icon = integration.icon;

  return (
    <div className={`flex flex-col items-center gap-2 ${className}`}>
      {integration.logoPath ? (
        <img
          src={integration.logoPath}
          alt={integration.name}
          className={`${BADGE_SIZE[size]} object-contain ${monochrome ? 'opacity-60 grayscale transition-all hover:opacity-100 hover:grayscale-0' : ''}`}
        />
      ) : (
        <span
          className={`flex items-center justify-center rounded-control ${BADGE_SIZE[size]} ${
            monochrome ? 'bg-dark/[0.05] text-white/45' : 'bg-primary/20 text-primary'
          }`}
        >
          <Icon className={GLYPH_SIZE[size]} strokeWidth={2} />
        </span>
      )}
      {showName && <span className="text-xs font-semibold text-white/70">{integration.name}</span>}
      {showStatus && <Badge variant="soon">{STATUS_LABEL[integration.status]}</Badge>}
    </div>
  );
}
