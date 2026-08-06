import type { Integration } from '../../data/integrations';

interface BrandMarqueeProps {
  integrations: Integration[];
  className?: string;
}

function MarqueeItem({ integration }: { integration: Integration }) {
  const Icon = integration.icon;
  return (
    <div className="group flex shrink-0 items-center gap-2 rounded-full border border-white/10 bg-[#0c0c0c] px-4 py-2">
      <span className="flex h-6 w-6 items-center justify-center rounded-control bg-dark/[0.06] text-white/40 transition-colors group-hover:bg-primary/20 group-hover:text-primary">
        <Icon className="h-3.5 w-3.5" strokeWidth={2} />
      </span>
      <span className="text-xs font-semibold text-white/60">{integration.name}</span>
    </div>
  );
}

/** Infinite horizontal logo strip — brief: "Do not show 40 logos randomly.
 * Group them. Use monochrome logos normally; on hover restore official
 * color." (Here: muted icon chip → orange on hover, until real SVGs land.)
 * Duplicates the list once for a seamless CSS-only loop; pauses on hover
 * and prefers-reduced-motion (see .animate-marquee in resources/css/app.css). */
export function BrandMarquee({ integrations, className = '' }: BrandMarqueeProps) {
  return (
    <div className={`overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)] ${className}`}>
      <div className="flex w-max animate-marquee gap-3">
        {[...integrations, ...integrations].map((integration, index) => (
          <MarqueeItem key={`${integration.key}-${index}`} integration={integration} />
        ))}
      </div>
    </div>
  );
}
