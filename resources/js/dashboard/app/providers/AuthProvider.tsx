import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { me as fetchMe } from '../../features/auth/api/authApi';
import { ApiError } from '../../lib/api';
import type { CurrentStoreSummary, CurrentUser } from '../../types';

interface AuthContextValue {
  user: CurrentUser | null;
  store: CurrentStoreSummary | null;
  loading: boolean;
  refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [store, setStore] = useState<CurrentStoreSummary | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetchMe();
      setUser(response.data.user);
      setStore(response.data.authenticated ? response.data.store : null);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        setUser(null);
        setStore(null);
      } else {
        throw error;
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return (
    <AuthContext.Provider value={{ user, store, loading, refresh }}>{children}</AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
