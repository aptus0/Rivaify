import { Bell, HelpCircle, Menu, Search } from 'lucide-react';
import { StoreSwitcher } from './StoreSwitcher';
import { UserMenu } from './UserMenu';
import type { CurrentStoreSummary, CurrentUser } from '../../types';

interface AppHeaderProps {
  title: string;
  user: CurrentUser;
  store: CurrentStoreSummary;
  onOpenMobileSidebar: () => void;
}

export function AppHeader({ title, user, store, onOpenMobileSidebar }: AppHeaderProps) {
  return (
    <header className="flex items-center justify-between border-b border-border bg-card px-4 py-3 lg:px-6">
      <div className="flex items-center gap-3">
        <button
          onClick={onOpenMobileSidebar}
          className="text-muted hover:text-dark lg:hidden"
          aria-label="Menüyü aç"
        >
          <Menu size={20} />
        </button>
        <h1 className="text-lg font-semibold text-dark">{title}</h1>
      </div>

      <div className="flex items-center gap-3">
        <div className="relative hidden md:block">
          <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input
            disabled
            placeholder="Ara..."
            title="Yakında"
            className="w-48 cursor-not-allowed rounded-md border border-border bg-app-bg py-1.5 pl-9 pr-3 text-sm text-muted placeholder:text-muted"
          />
        </div>

        <button disabled title="Yakında" className="cursor-not-allowed text-muted">
          <HelpCircle size={19} />
        </button>
        <button disabled title="Yakında" className="cursor-not-allowed text-muted">
          <Bell size={19} />
        </button>

        <div className="h-6 w-px bg-border" />

        <StoreSwitcher store={store} />
        <UserMenu user={user} />
      </div>
    </header>
  );
}
