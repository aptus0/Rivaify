import {
  BarChart3,
  CreditCard,
  Package,
  Share2,
  ShoppingCart,
  Truck,
  Users,
  Warehouse,
  type LucideIcon,
} from 'lucide-react';
import { RivaCard } from '../../../effects/RivaCard';
import { Logo } from '../../../Logo';

interface ModuleNode {
  icon: LucideIcon;
  label: string;
}

// Arranged clockwise starting top-left — positions map 1:1 to the 3x3 grid
// cells below (center cell is the Rivaify node).
const MODULES: ModuleNode[] = [
  { icon: Package, label: 'Products' },
  { icon: ShoppingCart, label: 'Orders' },
  { icon: Users, label: 'Customers' },
  { icon: Warehouse, label: 'Inventory' },
  { icon: CreditCard, label: 'Payments' },
  { icon: Truck, label: 'Shipping' },
  { icon: Share2, label: 'Channels' },
  { icon: BarChart3, label: 'Analytics' },
];

// Grid coords for a 3x3 layout, center excluded (index 4). Used to draw
// static connector lines from the center to each module cell.
const CELL_COORDS = [
  [0, 0], [1, 0], [2, 0],
  [0, 1],         [2, 1],
  [0, 2], [1, 2], [2, 2],
];

export function PlatformNetwork() {
  return (
    <div className="relative mx-auto max-w-2xl">
      <svg viewBox="0 0 300 300" className="pointer-events-none absolute inset-0 -z-10 h-full w-full motion-reduce:opacity-40" aria-hidden="true">
        <defs>
          <linearGradient id="platform-network-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#FF6B00" stopOpacity="0.4" />
            <stop offset="100%" stopColor="#7957FF" stopOpacity="0.15" />
          </linearGradient>
        </defs>
        {CELL_COORDS.map(([cx, cy], index) => (
          <line
            key={index}
            x1="150"
            y1="150"
            x2={cx * 100 + 50}
            y2={cy * 100 + 50}
            stroke="url(#platform-network-gradient)"
            strokeWidth="1.5"
          />
        ))}
      </svg>

      <div className="grid grid-cols-3 gap-4">
        {MODULES.slice(0, 4).map((module) => (
          <ModuleTile key={module.label} module={module} />
        ))}
        <div className="flex items-center justify-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-showcase border border-dark/[0.08] bg-dark shadow-spectrum">
            <Logo variant="icon" />
          </div>
        </div>
        {MODULES.slice(4).map((module) => (
          <ModuleTile key={module.label} module={module} />
        ))}
      </div>
    </div>
  );
}

function ModuleTile({ module }: { module: ModuleNode }) {
  return (
    <RivaCard variant="spectrum" intensity="subtle" radius="card" className="p-4 text-center">
      <span className="mx-auto flex h-9 w-9 items-center justify-center rounded-control bg-surface-orange text-primary">
        <module.icon className="h-4 w-4" strokeWidth={2} />
      </span>
      <p className="mt-2 text-xs font-semibold text-dark">{module.label}</p>
    </RivaCard>
  );
}
