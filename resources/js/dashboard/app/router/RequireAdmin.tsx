import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';

export function RequireAdmin() {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="flex h-screen items-center justify-center">Loading...</div>;
  }

  if (!user || !user.is_rivaify_admin) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}
