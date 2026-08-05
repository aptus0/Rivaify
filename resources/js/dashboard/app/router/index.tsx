import { createBrowserRouter } from 'react-router-dom';
import { CenteredLayout } from '../layouts/CenteredLayout';
import { RequireAuth } from './RequireAuth';
import { RootRedirect } from './RootRedirect';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { RegisterPage } from '../../features/auth/pages/RegisterPage';
import { VerifyEmailPage } from '../../features/auth/pages/VerifyEmailPage';
import { OnboardingPage } from '../../features/onboarding/pages/OnboardingPage';

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
        <CenteredLayout>
          <RequireAuth>
            <RootRedirect />
          </RequireAuth>
        </CenteredLayout>
      ),
    },
    {
      path: '/login',
      element: (
        <CenteredLayout>
          <LoginPage />
        </CenteredLayout>
      ),
    },
    {
      path: '/register',
      element: (
        <CenteredLayout>
          <RegisterPage />
        </CenteredLayout>
      ),
    },
    {
      path: '/verify-email',
      element: (
        <CenteredLayout>
          <VerifyEmailPage />
        </CenteredLayout>
      ),
    },
    {
      path: '/onboarding',
      element: (
        <CenteredLayout>
          <RequireAuth>
            <OnboardingPage />
          </RequireAuth>
        </CenteredLayout>
      ),
    },
  ],
  { basename: '/app' },
);
