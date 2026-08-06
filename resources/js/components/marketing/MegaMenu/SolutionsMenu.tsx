import { Link } from '@inertiajs/react';
import { SOLUTIONS_MENU } from '../../../data/navigation';

interface SolutionsMenuProps {
  onNavigate: () => void;
}

export function SolutionsMenu({ onNavigate }: SolutionsMenuProps) {
  return (
    <div className="grid grid-cols-3 gap-3">
      {SOLUTIONS_MENU.map((item) => (
        <Link
          key={item.label}
          href={item.href}
          onClick={onNavigate}
          className="group rounded-control border border-transparent p-3 transition-colors hover:border-dark/[0.06] hover:bg-surface-orange"
        >
          <p className="text-sm font-semibold text-dark group-hover:text-primary">{item.label}</p>
          <p className="mt-1 text-xs leading-snug text-dark/45">{item.description}</p>
        </Link>
      ))}
    </div>
  );
}
