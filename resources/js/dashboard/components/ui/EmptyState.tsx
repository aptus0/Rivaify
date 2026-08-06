import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description: string;
  action?: ReactNode;
}

/** The one empty-state layout for the whole admin app (brief §26) — orders,
 * products, collections, discounts, etc. all render through this. */
export function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-app-bg text-muted">
        <Icon size={22} />
      </div>
      <div className="space-y-1">
        <p className="text-sm font-medium text-dark">{title}</p>
        <p className="mx-auto max-w-sm text-sm text-muted">{description}</p>
      </div>
      {action}
    </div>
  );
}
