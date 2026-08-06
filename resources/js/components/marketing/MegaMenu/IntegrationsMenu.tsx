import { Link } from '@inertiajs/react';
import { integrationsByCategory, STATUS_LABEL, type IntegrationCategory } from '../../../data/integrations';
import { Badge } from '../../ui/Badge';

interface IntegrationsMenuProps {
  onNavigate: () => void;
}

const GROUPS: { title: string; category: IntegrationCategory }[] = [
  { title: 'Social', category: 'social' },
  { title: 'Payments', category: 'payment' },
  { title: 'Shipping', category: 'shipping' },
];

export function IntegrationsMenu({ onNavigate }: IntegrationsMenuProps) {
  return (
    <div className="grid grid-cols-3 gap-6">
      {GROUPS.map((group) => (
        <div key={group.category}>
          <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">{group.title}</p>
          <ul className="mt-3 flex flex-col gap-2.5">
            {integrationsByCategory(group.category).map((integration) => {
              const Icon = integration.icon;
              return (
                <li key={integration.key}>
                  <Link
                    href="/integrations"
                    onClick={onNavigate}
                    className="group flex items-center justify-between gap-2 rounded-control -mx-2 px-2 py-1.5 transition-colors hover:bg-surface-orange"
                  >
                    <span className="flex items-center gap-2.5">
                      <span className="flex h-7 w-7 items-center justify-center rounded-control bg-dark/[0.05] text-dark/45 group-hover:bg-white group-hover:text-primary">
                        <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                      </span>
                      <span className="text-sm font-medium text-dark group-hover:text-primary">{integration.name}</span>
                    </span>
                    <Badge variant="soon" className="shrink-0 px-2 py-0.5 text-[10px]">
                      {STATUS_LABEL[integration.status]}
                    </Badge>
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
      <div className="col-span-3 border-t border-dark/[0.06] pt-3 text-right">
        <Link href="/integrations" onClick={onNavigate} className="text-xs font-semibold text-primary hover:underline">
          Tüm entegrasyonları gör →
        </Link>
      </div>
    </div>
  );
}
