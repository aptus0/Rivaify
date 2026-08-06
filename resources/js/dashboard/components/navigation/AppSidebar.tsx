import {
  BarChart3,
  Home,
  Megaphone,
  Package,
  Percent,
  Plug,
  Settings,
  Share2,
  ShoppingCart,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { Logo } from '../../../components/Logo';

interface NavItem {
  label: string;
  icon: LucideIcon;
  href?: string;
  children?: Array<{ label: string; href?: string }>;
}

const NAV_ITEMS: NavItem[] = [
  { label: 'Ana Sayfa', icon: Home, href: '/dashboard' },
  { label: 'Siparişler', icon: ShoppingCart },
  { label: 'Ürünler', icon: Package, href: '/products', children: [
    { label: 'Ürünler', href: '/products' },
    { label: 'Kategoriler' },
    { label: 'Koleksiyonlar' },
    { label: 'Envanter' },
  ] },
  { label: 'Müşteriler', icon: Users },
  { label: 'Pazarlama', icon: Megaphone },
  { label: 'İndirimler', icon: Percent },
  { label: 'Satış Kanalları', icon: Share2, children: [{ label: 'Online Mağaza' }, { label: 'Instagram' }, { label: 'Facebook' }, { label: 'TikTok' }] },
  { label: 'Analitik', icon: BarChart3 },
  { label: 'Uygulamalar', icon: Plug },
];

function SidebarContent() {
  return (
    <div className="flex h-full flex-col">
      <div className="px-5 py-5">
        <Logo />
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3">
        {NAV_ITEMS.map((item) =>
          item.children ? (
            <div key={item.label}>
              {item.href ? (
                <NavLink
                  to={item.href}
                  className={({ isActive }) =>
                    `flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition ${
                      isActive ? 'bg-surface-orange text-primary-hover' : 'text-dark hover:bg-app-bg'
                    }`
                  }
                >
                  <span className="flex items-center gap-3"><item.icon size={18} />{item.label}</span>
                </NavLink>
              ) : (
                <div className="flex cursor-not-allowed items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-muted">
                  <span className="flex items-center gap-3"><item.icon size={18} />{item.label}</span>
                  <span className="rounded-full bg-app-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">Yakında</span>
                </div>
              )}
              <div className="ml-9 space-y-0.5 border-l border-border pl-3">
                {item.children.map((child) => child.href ? (
                  <NavLink key={child.label} to={child.href} className={({ isActive }) => `block py-1 text-sm ${isActive ? 'font-medium text-primary-hover' : 'text-muted hover:text-dark'}`}>{child.label}</NavLink>
                ) : <p key={child.label} className="cursor-not-allowed py-1 text-sm text-muted/70">{child.label}</p>)}
              </div>
            </div>
          ) : item.href ? (
            <NavLink
              key={item.label}
              to={item.href}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
                  isActive ? 'bg-surface-orange text-primary-hover' : 'text-dark hover:bg-app-bg'
                }`
              }
            >
              <item.icon size={18} />
              {item.label}
            </NavLink>
          ) : (
            <div key={item.label}>
              <div className="flex cursor-not-allowed items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-muted">
                <span className="flex items-center gap-3">
                  <item.icon size={18} />
                  {item.label}
                </span>
                <span className="rounded-full bg-app-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                  Yakında
                </span>
              </div>
            </div>
          ),
        )}
      </nav>

      <div className="border-t border-border px-3 py-3">
        <div className="flex cursor-not-allowed items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted">
          <Settings size={18} />
          Ayarlar
        </div>
      </div>
    </div>
  );
}

export function AppSidebar({ mobileOpen, onCloseMobile }: { mobileOpen: boolean; onCloseMobile: () => void }) {
  return (
    <>
      <aside className="hidden w-64 shrink-0 border-r border-border bg-card lg:block">
        <SidebarContent />
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 z-30 lg:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={onCloseMobile} />
          <div className="relative h-full w-64 bg-card shadow-xl">
            <button
              onClick={onCloseMobile}
              className="absolute right-3 top-5 text-muted hover:text-dark"
              aria-label="Menüyü kapat"
            >
              <X size={20} />
            </button>
            <SidebarContent />
          </div>
        </div>
      )}
    </>
  );
}
