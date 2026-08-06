import { Link } from '@inertiajs/react';
import { PLATFORM_MENU } from '../../../data/navigation';

interface PlatformMenuProps {
  onNavigate: () => void;
}

export function PlatformMenu({ onNavigate }: PlatformMenuProps) {
  return (
    <div className="grid grid-cols-4 gap-6">
      {PLATFORM_MENU.map((column) => (
        <div key={column.title}>
          <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">{column.title}</p>
          <ul className="mt-3 flex flex-col gap-3">
            {column.items.map((item) => (
              <li key={item.label}>
                <Link
                  href={item.href}
                  onClick={onNavigate}
                  className="group -mx-2 block rounded-control px-2 py-1 transition-colors hover:bg-surface-orange"
                >
                  <p className="text-sm font-semibold text-dark group-hover:text-primary">{item.label}</p>
                  <p className="mt-0.5 text-xs leading-snug text-dark/45">{item.description}</p>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}
