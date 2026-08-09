import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Bell, Command, HelpCircle, Menu, Package, Search, ShoppingCart, Users, WalletCards, X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { getWorkspaceNotifications, searchWorkspace, type WorkspaceNotification, type WorkspaceSearchResult } from '../../features/workspace/api/workspaceApi';
import type { CurrentStoreSummary, CurrentUser, StorePermission } from '../../types';
import { StoreSwitcher } from './StoreSwitcher';
import { UserMenu } from './UserMenu';

interface AppHeaderProps {
  title: string;
  user: CurrentUser;
  store: CurrentStoreSummary;
  onOpenMobileSidebar: () => void;
}

const commands: Array<{ id: string; label: string; hint: string; path: string; icon: typeof Package; permission: StorePermission }> = [
  { id: 'orders', label: 'Siparişlerde ara', hint: 'Sipariş numarası', path: '/orders', icon: ShoppingCart, permission: 'orders.view' },
  { id: 'products', label: 'Ürünlerde ara', hint: 'Ürün adı, SKU veya barkod', path: '/products', icon: Package, permission: 'products.view' },
  { id: 'customers', label: 'Müşterilerde ara', hint: 'Ad, e-posta veya telefon', path: '/customers', icon: Users, permission: 'customers.view' },
  { id: 'product-create', label: 'Yeni ürün oluştur', hint: 'Hızlı işlem', path: '/products/create', icon: Package, permission: 'products.manage' },
];

const resultIcons = { product: Package, order: ShoppingCart, customer: Users } as const;
const notificationIcons = { order: ShoppingCart, inventory: AlertTriangle, payment: WalletCards, integration: WalletCards } as const;

function notificationTone(tone: WorkspaceNotification['tone']): string {
  if (tone === 'success') return 'bg-emerald-50 text-emerald-700';
  if (tone === 'danger') return 'bg-red-50 text-red-700';
  return 'bg-amber-50 text-amber-700';
}

function formatNotificationDate(value: string | null): string {
  if (!value) return 'Yapılandırma gerekli';
  return new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

export function AppHeader({ title, user, store, onOpenMobileSidebar }: AppHeaderProps) {
  const navigate = useNavigate();
  const [palette, setPalette] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [searchResults, setSearchResults] = useState<WorkspaceSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [notificationItems, setNotificationItems] = useState<WorkspaceNotification[]>([]);
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const can = (permission: StorePermission) => store.permissions === undefined || store.permissions.includes(permission);

  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setPalette(true);
      }
      if (event.key === 'Escape') {
        setPalette(false);
        setNotificationsOpen(false);
      }
    };
    window.addEventListener('keydown', listener);
    return () => window.removeEventListener('keydown', listener);
  }, []);

  useEffect(() => {
    let active = true;
    setNotificationsLoading(true);
    void getWorkspaceNotifications()
      .then((response) => { if (active) setNotificationItems(response.data); })
      .catch(() => { if (active) setNotificationItems([]); })
      .finally(() => { if (active) setNotificationsLoading(false); });
    return () => { active = false; };
  }, [store.id]);

  useEffect(() => {
    const trimmed = query.trim();
    if (trimmed.length < 2) {
      setSearchResults([]);
      setSearching(false);
      return;
    }

    let active = true;
    setSearching(true);
    const timer = window.setTimeout(() => {
      void searchWorkspace(trimmed)
        .then((response) => { if (active) setSearchResults(response.data); })
        .catch(() => { if (active) setSearchResults([]); })
        .finally(() => { if (active) setSearching(false); });
    }, 250);
    return () => { active = false; window.clearTimeout(timer); };
  }, [query]);

  const quickResults = useMemo(
    () => commands.filter((item) => can(item.permission) && `${item.label} ${item.hint}`.toLocaleLowerCase('tr').includes(query.toLocaleLowerCase('tr'))),
    [query, store.permissions],
  );

  const go = (path: string) => {
    setPalette(false);
    setNotificationsOpen(false);
    setQuery('');
    navigate(path);
  };

  const showLiveResults = query.trim().length >= 2;

  return <>
    <header className="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-card/95 px-4 py-3 backdrop-blur lg:px-6">
      <div className="flex items-center gap-3">
        <button onClick={onOpenMobileSidebar} className="text-muted hover:text-dark lg:hidden" aria-label="Menüyü aç"><Menu size={20} /></button>
        <h1 className="text-lg font-semibold text-dark">{title}</h1>
      </div>
      <div className="flex items-center gap-3">
        <button onClick={() => setPalette(true)} className="relative hidden w-56 items-center rounded-md border border-border bg-app-bg py-1.5 pl-9 pr-3 text-left text-sm text-muted hover:border-primary/40 md:flex">
          <Search size={15} className="absolute left-3" />
          <span className="flex-1">Rivaify'da ara...</span>
          <kbd className="rounded border border-border bg-card px-1.5 text-[10px]">⌘K</kbd>
        </button>
        {can('settings.view') && <button onClick={() => navigate('/settings')} className="text-muted hover:text-dark" title="Yardım ve ayarlar"><HelpCircle size={19} /></button>}
        <button onClick={() => setNotificationsOpen(true)} className="relative text-muted hover:text-dark" aria-label={`Bildirimler${notificationItems.length ? `, ${notificationItems.length} uyarı` : ''}`}>
          <Bell size={19} />
          {notificationItems.length > 0 && <span className="absolute -right-1 -top-1 h-2 w-2 rounded-full bg-primary ring-2 ring-white" />}
        </button>
        <div className="h-6 w-px bg-border" />
        <StoreSwitcher store={store} />
        <UserMenu user={user} />
      </div>
    </header>

    {palette && <div className="fixed inset-0 z-50 flex items-start justify-center bg-dark/35 px-4 pt-[12vh] backdrop-blur-sm" onMouseDown={() => setPalette(false)}>
      <div className="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-spectrum" onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-center gap-3 border-b border-border px-4">
          <Search size={20} className="text-muted" />
          <input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Ürün, sipariş veya müşteri ara..." className="w-full bg-transparent py-4 text-sm outline-none" />
          <button onClick={() => setPalette(false)} aria-label="Aramayı kapat"><X size={18} className="text-muted" /></button>
        </div>
        <div className="max-h-[55vh] overflow-y-auto p-2">
          <p className="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-muted">{showLiveResults ? 'Mağaza sonuçları' : 'Hızlı işlemler'}</p>
          {!showLiveResults && quickResults.map((item) => <button key={item.id} onClick={() => go(item.path)} className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-app-bg">
            <span className="rounded-lg bg-surface-orange p-2 text-primary"><item.icon size={18} /></span>
            <span className="flex-1"><span className="block text-sm font-semibold text-dark">{item.label}</span><span className="block text-xs text-muted">{item.hint}</span></span>
            <Command size={15} className="text-muted" />
          </button>)}
          {showLiveResults && searching && <div className="space-y-2 p-3">{[1, 2, 3].map((item) => <div key={item} className="h-14 animate-pulse rounded-xl bg-app-bg" />)}</div>}
          {showLiveResults && !searching && searchResults.map((item) => {
            const Icon = resultIcons[item.type];
            return <button key={`${item.type}-${item.id}`} onClick={() => go(item.path)} className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-app-bg">
              <span className="rounded-lg bg-surface-orange p-2 text-primary"><Icon size={18} /></span>
              <span className="min-w-0 flex-1"><span className="block truncate text-sm font-semibold text-dark">{item.title}</span><span className="block truncate text-xs text-muted">{item.description}</span></span>
            </button>;
          })}
          {showLiveResults && !searching && !searchResults.length && <p className="px-3 py-8 text-center text-sm text-muted">Bu mağazada eşleşen kayıt bulunamadı.</p>}
        </div>
      </div>
    </div>}

    {notificationsOpen && <div className="fixed inset-0 z-50 bg-dark/25" onMouseDown={() => setNotificationsOpen(false)}>
      <aside className="ml-auto flex h-full w-full max-w-md flex-col bg-card shadow-spectrum" onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-border p-5">
          <div><h2 className="font-semibold text-dark">Bildirimler</h2><p className="text-xs text-muted">Sipariş, stok ve entegrasyon durumları</p></div>
          <button onClick={() => setNotificationsOpen(false)} aria-label="Bildirimleri kapat"><X size={20} /></button>
        </div>
        <div className="flex-1 overflow-y-auto p-3">
          {notificationsLoading && <div className="space-y-3">{[1, 2, 3].map((item) => <div key={item} className="h-20 animate-pulse rounded-xl bg-app-bg" />)}</div>}
          {!notificationsLoading && !notificationItems.length && <div className="flex h-full flex-col items-center justify-center p-8 text-center">
            <span className="rounded-2xl bg-surface-orange p-4 text-primary"><Bell size={25} /></span>
            <h3 className="mt-4 font-semibold">Her şey yolunda</h3>
            <p className="mt-2 max-w-xs text-sm leading-6 text-muted">Yeni sipariş veya operasyon uyarısı oluştuğunda burada görünecek.</p>
          </div>}
          {!notificationsLoading && notificationItems.map((item) => {
            const Icon = notificationIcons[item.type];
            return <button key={item.id} onClick={() => go(item.path)} className="mb-2 flex w-full items-start gap-3 rounded-xl border border-border p-4 text-left hover:bg-app-bg">
              <span className={`rounded-lg p-2 ${notificationTone(item.tone)}`}><Icon size={17} /></span>
              <span className="min-w-0 flex-1"><span className="block text-sm font-semibold text-dark">{item.title}</span><span className="mt-0.5 block text-xs leading-5 text-muted">{item.description}</span><span className="mt-1 block text-[11px] text-muted">{formatNotificationDate(item.created_at)}</span></span>
            </button>;
          })}
        </div>
      </aside>
    </div>}
  </>;
}
