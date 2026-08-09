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
import { ProductsPage } from '../../features/products/pages/ProductsPage';
import { ProductEditorPage } from '../../features/products/pages/ProductEditorPage';
import { OrdersPage } from '../../features/orders/pages/OrdersPage';
import { OrderDetailPage } from '../../features/orders/pages/OrderDetailPage';
import { CustomersPage } from '../../features/customers/pages/CustomersPage';
import { CustomerDetailPage } from '../../features/customers/pages/CustomerDetailPage';
import { AnalyticsPage } from '../../features/analytics/pages/AnalyticsPage';
import { MarketingPage } from '../../features/marketing/pages/MarketingPage';
import { CategoriesPage } from '../../features/catalog/pages/CategoriesPage';
import { CollectionsPage } from '../../features/catalog/pages/CollectionsPage';
import { InventoryPage } from '../../features/inventory/pages/InventoryPage';
import { FinancePage } from '../../features/finance/pages/FinancePage';
import { FulfillmentPage } from '../../features/fulfillment/pages/FulfillmentPage';
import { DiscountsPage } from '../../features/discounts/pages/DiscountsPage';
import { ReturnsPage } from '../../features/returns/pages/ReturnsPage';
import { IntegrationsPage } from '../../features/settings/pages/IntegrationsPage';
import { SettingsPage } from '../../features/settings/pages/SettingsPage';
import { OnlineStorePage } from '../../features/online-store/pages/OnlineStorePage';
import { OnlineStoreSectionPage } from '../../features/online-store/pages/OnlineStoreSectionPage';
import { ThemeLibraryPage } from '../../features/online-store/pages/ThemeLibraryPage';
import { StoreBuilderPage } from '../../features/online-store/pages/StoreBuilderPage';
import { RouteErrorPage } from '../../components/error/RouteErrorPage';

// Served on its own host (app.rivaify.com, brief §11) via Route::domain()
// in routes/web.php — no path prefix, so basename is just '/'. Requires a
// DNS/hosts entry for app.rivaify.com pointing at this server; visiting by
// bare IP/localhost hits the marketing site instead (see routes/web.php).
const routes = [
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
      ],
    },
    {
      path: '/orders',
      element: <RequireAuth><AppLayout /></RequireAuth>,
      children: [{ index: true, element: <OrdersPage /> }, { path: ':orderId', element: <OrderDetailPage /> }],
    },
    {
      path: '/customers',
      element: <RequireAuth><AppLayout /></RequireAuth>,
      children: [{ index: true, element: <CustomersPage /> }, { path: ':customerId', element: <CustomerDetailPage /> }],
    },
    {
      path: '/products',
      element: (
        <RequireAuth>
          <AppLayout />
        </RequireAuth>
      ),
      children: [
        { index: true, element: <ProductsPage /> },
        { path: 'create', element: <ProductEditorPage /> },
        { path: ':productId', element: <ProductEditorPage /> },
      ],
    },
    { path: '/analytics', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <AnalyticsPage /> }] },
    { path: '/marketing', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <MarketingPage /> }] },
    { path: '/categories', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <CategoriesPage /> }] },
    { path: '/collections', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <CollectionsPage /> }] },
    { path: '/inventory', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <InventoryPage /> }] },
    { path: '/fulfillment', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <FulfillmentPage /> }] },
    { path: '/returns', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <ReturnsPage /> }] },
    { path: '/finance', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <FinancePage /> }] },
    { path: '/discounts', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <DiscountsPage /> }] },
    { path: '/channels', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <IntegrationsPage section="channels" /> }] },
    { path: '/channels/online-store', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <OnlineStorePage /> }] },
    { path: '/online-store', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <OnlineStorePage /> }] },
    { path: '/online-store/themes', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <ThemeLibraryPage /> }, { path: 'library', element: <ThemeLibraryPage /> }] },
    { path: '/online-store/:section', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <OnlineStoreSectionPage /> }] },
    { path: '/online-store/themes/:themeId/editor', element: <RequireAuth><StoreBuilderPage /></RequireAuth> },
    { path: '/online-store/themes/:themeId', element: <RequireAuth><StoreBuilderPage /></RequireAuth> },
    { path: '/store-builder/:themeId', element: <RequireAuth><StoreBuilderPage /></RequireAuth> },
    { path: '/apps', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <IntegrationsPage section="apps" /> }] },
    { path: '/settings', element: <RequireAuth><AppLayout /></RequireAuth>, children: [{ index: true, element: <SettingsPage /> }] },
    { path: '*', element: <RouteErrorPage status={404} /> },
  ].map((route) => ({ errorElement: <RouteErrorPage />, ...route }));

export const router = createBrowserRouter(
  routes,
  { basename: '/' },
);
