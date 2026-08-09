import { useEffect, useState } from 'react';
import { Outlet, useOutletContext } from 'react-router-dom';
import { AppHeader } from '../../components/navigation/AppHeader';
import { AppSidebar } from '../../components/navigation/AppSidebar';
import { useAuth } from '../providers/AuthProvider';

interface AppOutletContext {
  setTitle: (title: string) => void;
}


export function usePageTitle(title: string): void {
  const { setTitle } = useOutletContext<AppOutletContext>();
  useEffect(() => setTitle(title), [title, setTitle]);
}

export function AppLayout() {
  const { user, store } = useAuth();
  const [title, setTitle] = useState('Ana Sayfa');
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

  // RootRedirect only ever routes here once user+store are both resolved —
  // this guard is defensive, not an expected runtime path.
  if (!user || !store) {
    return null;
  }

  return (
    <div className="flex min-h-screen bg-app-bg">
      <AppSidebar mobileOpen={mobileSidebarOpen} onCloseMobile={() => setMobileSidebarOpen(false)} />

      <div className="flex min-w-0 flex-1 flex-col">
        <AppHeader
          title={title}
          user={user}
          store={store}
          onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
        />
        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          <Outlet context={{ setTitle } satisfies AppOutletContext} />
        </main>
      </div>
    </div>
  );
}
