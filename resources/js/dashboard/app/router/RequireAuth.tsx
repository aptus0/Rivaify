import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';

/** Redirects to /login unless authenticated; to /verify-email unless the
 * email is verified — every protected route needs both checks, so this
 * is the one place that encodes the rule. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) {
    return null;
  }
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  // TEMPORARILY DISABLED 2026-08-08: outbound mail (Gmail SMTP) isn't
  // sending yet, so the verify-email gate would lock everyone out. Re-enable
  // this check once mail delivery is confirmed working.
  // if (!user.email_verified) {
  //   return <Navigate to="/verify-email" replace />;
  // }

  return <>{children}</>;
}
