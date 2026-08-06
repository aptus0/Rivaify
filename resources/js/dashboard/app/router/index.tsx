import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { GuestLayout } from '../layouts/GuestLayout';
import { OnboardingLayout } from '../layouts/OnboardingLayout';
import { RequireAuth } from './RequireAuth';
import { RootRedirect } from './RootRedirect';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { RegisterPage } from '../../features/auth/pages/RegisterPage';
import { VerifyEmailPage } from '../../features/auth/pages/VerifyEmailPage';
import { OnboardingPage } from '../../features/onboarding/pages/OnboardingPage';
import { DashboardPage } from '../../features/dashboard/pages/DashboardPage';

// basename: '/app' — this repo currently serves the merchant dashboard SPA
// under a path prefix on the same host as the landing page, since local
// dev has no app.rivaify.com DNS entry. In production this becomes its
// own subdomain (brief §11) and basename reverts to '/'; see
// docs/ARCHITECTURE.md for the target domain split.
export const router = createBrowserRouter(
  [
    {
      path: '/',
      element: (
        <RequireAuth>
          <RootRedirect />
        </RequireAuth>
      ),
    },
    {
      path: '/login',
      element: (
        <GuestLayout>
          <LoginPage />
        </GuestLayout>
      ),
    },
    {
      path: '/register',
      element: (
        <GuestLayout>
          <RegisterPage />
        </GuestLayout>
      ),
    },
    {
      path: '/verify-email',
      element: (
        <GuestLayout>
          <VerifyEmailPage />
        </GuestLayout>
      ),
    },
    {
      path: '/onboarding',
      element: (
        <RequireAuth>
          <OnboardingLayout>
            <OnboardingPage />
          </OnboardingLayout>
        </RequireAuth>
      ),
    },
    {
      path: '/dashboard',
      element: (
        <RequireAuth>
          <AppLayout />
        </RequireAuth>
      ),
      children: [{ index: true, element: <DashboardPage /> }],
    },
  ],
  { basename: '/app' },
);
