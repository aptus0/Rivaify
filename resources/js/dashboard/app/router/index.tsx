import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { GuestLayout } from '../layouts/GuestLayout';
import { OnboardingLayout } from '../layouts/OnboardingLayout';
import { RequireAuth } from './RequireAuth';
import { RequireStore } from './RequireStore';
import { RootRedirect } from './RootRedirect';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { RegisterPage } from '../../features/auth/pages/RegisterPage';
import { VerifyEmailPage } from '../../features/auth/pages/VerifyEmailPage';
import { OnboardingPage } from '../../features/onboarding/pages/OnboardingPage';
import { DashboardPage } from '../../features/dashboard/pages/DashboardPage';
import { ProductsPage } from '../../features/products/pages/ProductsPage';
import { ProductEditorPage } from '../../features/products/pages/ProductEditorPage';
import { RequireAdmin } from './RequireAdmin';
import { AdminLayout } from '../../features/admin/pages/AdminLayout';
import { VerificationQueue } from '../../features/admin/pages/VerificationQueue';
import { MerchantManagement } from '../../features/admin/pages/MerchantManagement';

// Served on its own host (app.rivaify.com, brief §11) via Route::domain()
// in routes/web.php — no path prefix, so basename is just '/'. Requires a
// DNS/hosts entry for app.rivaify.com pointing at this server; visiting by
// bare IP/localhost hits the marketing site instead (see routes/web.php).
export const router = createBrowserRouter(
  [
    {
      path: '/',
      element: <RootRedirect />,
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
          <RequireStore>
            <AppLayout />
          </RequireStore>
        </RequireAuth>
      ),
      children: [
        { index: true, element: <DashboardPage /> },
      ],
    },
    {
      path: '/products',
      element: (
        <RequireAuth>
          <RequireStore>
            <AppLayout />
          </RequireStore>
        </RequireAuth>
      ),
      children: [
        { index: true, element: <ProductsPage /> },
        { path: 'create', element: <ProductEditorPage /> },
        { path: ':productId', element: <ProductEditorPage /> },
      ],
    },
    {
      path: '/admin',
      element: (
        <RequireAuth>
          <RequireAdmin>
            <AdminLayout />
          </RequireAdmin>
        </RequireAuth>
      ),
      children: [
        { path: 'queue', element: <VerificationQueue /> },
        { path: 'merchants', element: <MerchantManagement /> },
      ],
    },
  ],
  { basename: '/' },
);
