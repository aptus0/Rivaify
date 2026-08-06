import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';

/**
 * Ensures the authenticated user has a store and has completed onboarding.
 * Used to protect dashboard and commerce features from being accessed prematurely.
 */
export function RequireStore({ children }: { children: ReactNode }) {
  const { store, loading } = useAuth();

  if (loading) {
    return null;
  }
  
  if (!store || store.onboarding_status !== 'completed') {
    return <Navigate to="/onboarding" replace />;
  }

  return <>{children}</>;
}
