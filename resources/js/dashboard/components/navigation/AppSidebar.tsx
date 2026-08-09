import {
  BarChart3,
  Home,
  Megaphone,
  Package,
  PanelLeftClose,
  PanelLeftOpen,
  Percent,
  Plug,
  Settings,
  Share2,
  ShoppingCart,
  Users,
  WalletCards,
  X,
  type LucideIcon,
} from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { Logo } from '../../../components/Logo';
import { useAuth } from '../../app/providers/AuthProvider';
import type { StorePermission } from '../../types';

interface NavItem {
  label: string;
  icon: LucideIcon;
  href?: string;
  permission?: StorePermission;
  children?: Array<{ label: string; href?: string; permission?: StorePermission }>;
}

const NAV_ITEMS: NavItem[] = [
  { label: 'Ana Sayfa', icon: Home, href: '/dashboard' },
  { label: 'Siparişler', icon: ShoppingCart, href: '/orders', permission: 'orders.view', children: [
    { label: 'Tüm Siparişler', href: '/orders', permission: 'orders.view' },
    { label: 'Hazırlanacaklar', href: '/fulfillment', permission: 'orders.view' },
    { label: 'Kargoya Hazır', href: '/fulfillment?status=ready_to_ship', permission: 'orders.view' },
    { label: 'Gönderiler', href: '/fulfillment?tab=shipments', permission: 'orders.view' },
    { label: 'İadeler', href: '/returns', permission: 'orders.view' },
  ] },
  { label: 'Ürünler', icon: Package, href: '/products', permission: 'products.view', children: [
    { label: 'Ürünler', href: '/products', permission: 'products.view' },
    { label: 'Kategoriler', href: '/categories', permission: 'products.view' },
    { label: 'Koleksiyonlar', href: '/collections', permission: 'products.view' },
    { label: 'Envanter', href: '/inventory', permission: 'inventory.view' },
  ] },
  { label: 'Müşteriler', icon: Users, href: '/customers', permission: 'customers.view' },
  { label: 'Pazarlama', icon: Megaphone, href: '/marketing', permission: 'marketing.view' },
  { label: 'İndirimler', icon: Percent, href: '/discounts', permission: 'discounts.view' },
  { label: 'Satış Kanalları', icon: Share2, href: '/channels', permission: 'integrations.view', children: [
    { label: 'Kanallar', href: '/channels', permission: 'integrations.view' },
    { label: 'Online Mağaza', href: '/online-store', permission: 'themes.view' },
    { label: 'Temalar', href: '/online-store/themes', permission: 'themes.view' },
    { label: 'Sayfalar', href: '/online-store/pages', permission: 'themes.view' },
    { label: 'Navigasyon', href: '/online-store/navigation', permission: 'themes.view' },
    { label: 'Domainler', href: '/online-store/domains', permission: 'themes.view' },
  ] },
  { label: 'Analitik', icon: BarChart3, href: '/analytics', permission: 'analytics.view' },
  { label: 'Finans', icon: WalletCards, href: '/finance', permission: 'analytics.view' },
  { label: 'Uygulamalar', icon: Plug, href: '/apps', permission: 'integrations.view' },
];

function SidebarContent({ collapsed = false }: { collapsed?: boolean }) {
  const { store } = useAuth();
  const can = (permission?: StorePermission) => permission === undefined || store?.permissions === undefined || store.permissions.includes(permission);
  const visibleItems = NAV_ITEMS.filter((item) => can(item.permission));

  return (
    <div className="flex h-full flex-col">
      <div className={`${collapsed ? 'px-3' : 'px-5'} py-5`}>
        <Logo variant={collapsed ? 'icon' : 'horizontal'} />
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3">
        {visibleItems.map((item) =>
          item.children ? (
            <div key={item.label}>
              {item.href ? (
                <NavLink
                  to={item.href}
                  title={collapsed ? item.label : undefined}
                  className={({ isActive }) =>
                    `flex items-center ${collapsed ? 'justify-center px-2' : 'justify-between px-3'} rounded-md py-2 text-sm font-medium transition ${
                      isActive ? 'bg-surface-orange text-primary-hover' : 'text-dark hover:bg-app-bg'
                    }`
                  }
                >
                  <span className="flex items-center gap-3"><item.icon size={18} />{!collapsed && item.label}</span>
                </NavLink>
              ) : (
                <div className={`flex cursor-not-allowed items-center ${collapsed ? 'justify-center px-2' : 'justify-between px-3'} rounded-md py-2 text-sm font-medium text-muted`} title={collapsed ? item.label : undefined}>
                  <span className="flex items-center gap-3"><item.icon size={18} />{!collapsed && item.label}</span>
                  {!collapsed && <span className="rounded-full bg-app-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">Yakında</span>}
                </div>
              )}
              {!collapsed && <div className="ml-9 space-y-0.5 border-l border-border pl-3">
                {item.children.filter((child) => can(child.permission)).map((child) => child.href ? (
                  <NavLink key={child.label} to={child.href} className={({ isActive }) => `block py-1 text-sm ${isActive ? 'font-medium text-primary-hover' : 'text-muted hover:text-dark'}`}>{child.label}</NavLink>
                ) : <p key={child.label} className="cursor-not-allowed py-1 text-sm text-muted/70">{child.label}</p>)}
              </div>}
            </div>
          ) : item.href ? (
            <NavLink
              key={item.label}
              to={item.href}
              title={collapsed ? item.label : undefined}
              className={({ isActive }) =>
                `flex items-center ${collapsed ? 'justify-center px-2' : 'gap-3 px-3'} rounded-md py-2 text-sm font-medium transition ${
                  isActive ? 'bg-surface-orange text-primary-hover' : 'text-dark hover:bg-app-bg'
                }`
              }
            >
              <item.icon size={18} />
              {!collapsed && item.label}
            </NavLink>
          ) : (
            <div key={item.label}>
              <div className={`flex cursor-not-allowed items-center ${collapsed ? 'justify-center px-2' : 'justify-between px-3'} rounded-md py-2 text-sm font-medium text-muted`} title={collapsed ? item.label : undefined}>
                <span className="flex items-center gap-3">
                  <item.icon size={18} />
                  {!collapsed && item.label}
                </span>
                {!collapsed && <span className="rounded-full bg-app-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                  Yakında
                </span>}
              </div>
            </div>
          ),
        )}
      </nav>

      <div className="border-t border-border px-3 py-3">
        {can('settings.view') && <NavLink to="/settings" title={collapsed ? 'Ayarlar' : undefined} className={`flex items-center ${collapsed ? 'justify-center px-2' : 'gap-3 px-3'} rounded-md py-2 text-sm font-medium text-muted hover:bg-app-bg hover:text-dark`}>
          <Settings size={18} /> {!collapsed && 'Ayarlar'}
        </NavLink>}
      </div>
    </div>
  );
}

export function AppSidebar({ mobileOpen, onCloseMobile }: { mobileOpen: boolean; onCloseMobile: () => void }) {
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    setCollapsed(window.localStorage.getItem('rivaify:sidebar:collapsed') === 'true');
  }, []);

  function toggleCollapsed() {
    setCollapsed((current) => {
      const next = !current;
      window.localStorage.setItem('rivaify:sidebar:collapsed', String(next));

      return next;
    });
  }

  return (
    <>
      <aside className={`hidden shrink-0 border-r border-border bg-card transition-[width] duration-200 lg:block ${collapsed ? 'w-20' : 'w-64'}`}>
        <div className="flex h-full flex-col">
          <div className="flex justify-end px-3 pt-3">
            <button
              type="button"
              onClick={toggleCollapsed}
              className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark"
              aria-label={collapsed ? 'Menüyü genişlet' : 'Menüyü daralt'}
              title={collapsed ? 'Menüyü genişlet' : 'Menüyü daralt'}
            >
              {collapsed ? <PanelLeftOpen size={18} /> : <PanelLeftClose size={18} />}
            </button>
          </div>
          <SidebarContent collapsed={collapsed} />
        </div>
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
