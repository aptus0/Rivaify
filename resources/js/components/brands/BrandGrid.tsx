import { BrandLogo } from './BrandLogo';
import { CATEGORY_LABEL, integrationsByCategory, type IntegrationCategory } from '../../data/integrations';

interface BrandGridProps {
  categories: IntegrationCategory[];
  showStatus?: boolean;
  className?: string;
}

/** Category-grouped brand grid — used on the Home ecosystem section and the
 * Integrations page. Never a flat wall of 40 logos. */
export function BrandGrid({ categories, showStatus = true, className = '' }: BrandGridProps) {
  return (
    <div className={`grid grid-cols-1 gap-6 sm:grid-cols-3 ${className}`}>
      {categories.map((category) => (
        <div key={category}>
          <p className="text-[11px] font-bold uppercase tracking-wider text-white/35">{CATEGORY_LABEL[category]}</p>
          <div className="mt-3 flex flex-wrap gap-4">
            {integrationsByCategory(category).map((integration) => (
              <BrandLogo key={integration.key} brand={integration.key} size="sm" showStatus={showStatus} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
