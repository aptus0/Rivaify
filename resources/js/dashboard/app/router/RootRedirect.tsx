import { Navigate } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';
import { DashboardPage } from '../../features/dashboard/pages/DashboardPage';

/** "/" resolves to onboarding or the dashboard depending on how far the
 * merchant has gotten — see docs/ARCHITECTURE.md's onboarding state
 * machine (brief §9). */
export function RootRedirect() {
  const { store, loading } = useAuth();

  if (loading) {
    return null;
  }
  if (!store || store.onboarding_status !== 'completed') {
    return <Navigate to="/onboarding" replace />;
  }

  return <DashboardPage />;
}
