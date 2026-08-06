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
import { OrdersPage } from '../../features/orders/pages/OrdersPage';
import { OrderDetailPage } from '../../features/orders/pages/OrderDetailPage';
import { CustomersPage } from '../../features/customers/pages/CustomersPage';
import { CustomerDetailPage } from '../../features/customers/pages/CustomerDetailPage';

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
          <AppLayout />
        </RequireAuth>
      ),
      children: [
        { index: true, element: <DashboardPage /> },
        { path: 'orders', element: <OrdersPage /> },
        { path: 'orders/:orderId', element: <OrderDetailPage /> },
        { path: 'customers', element: <CustomersPage /> },
        { path: 'customers/:customerId', element: <CustomerDetailPage /> },
      ],
    },
  ],
  { basename: '/' },
);
