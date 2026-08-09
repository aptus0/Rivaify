import { Navigate } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';
import { GuestLayout } from '../layouts/GuestLayout';
import { LoginPage } from '../../features/auth/pages/LoginPage';

/** "/" is the whole point of app.rivaify.com having its own host (brief
 * feedback: "app.rivaify.com direkt login ekranı olsun") — an unauthenticated
 * visitor sees the login form right here, at "/", with no redirect to
 * "/login" and no URL change. Authenticated visitors fall through to
 * onboarding or the dashboard depending on how far they've gotten. */
export function RootRedirect() {
  const { user, store, loading } = useAuth();

  if (loading) {
    return null;
  }
  if (!user) {
    return (
      <GuestLayout>
        <LoginPage />
      </GuestLayout>
    );
  }
  // TEMPORARILY DISABLED 2026-08-08: outbound mail (Gmail SMTP) isn't
  // sending yet, so the verify-email gate would lock everyone out. Re-enable
  // this check once mail delivery is confirmed working.
  // if (!user.email_verified) {
  //   return <Navigate to="/verify-email" replace />;
  // }
  if (!store || store.onboarding_status !== 'completed') {
    return <Navigate to="/onboarding" replace />;
  }

  return <Navigate to="/dashboard" replace />;
}
