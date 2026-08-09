import { useEffect, useState } from 'react';
import { Outlet, RouterProvider, createBrowserRouter, useLocation } from 'react-router-dom';
import { trackStorefrontEvent } from './analytics';
import { getRuntime } from './api';
import type { StorefrontRuntime, StorefrontRuntimeDocument } from './types';
import { StorefrontErrorPage } from './components/error/StorefrontErrorPage';
import type { StorefrontContext } from './context';
import { CartPage } from './pages/CartPage';
import { CheckoutConfirmationPage } from './pages/CheckoutConfirmationPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { ProductPage } from './pages/ProductPage';
import { ProductsPage } from './pages/ProductsPage';
import { StorefrontHomePage } from './pages/StorefrontHomePage';
import { WishlistPage } from './pages/WishlistPage';
import { StorefrontRenderer } from './renderer/StorefrontRenderer';

function StorefrontLayout() {
  const location = useLocation();
  const [runtime, setRuntime] = useState<StorefrontRuntime | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    trackStorefrontEvent('page_view', { path: location.pathname });
  }, [location.pathname, location.search]);

  useEffect(() => {
    let active = true;
    void getRuntime()
      .then((response) => {
        if (active) setRuntime(response.data);
      })
      .catch(() => {
        if (active) setError('Mağaza şu anda kullanılamıyor.');
      });

    return () => {
      active = false;
    };
  }, []);

  if (error) return <StorefrontErrorPage status={503} />;
  if (!runtime) {
    return <main className="grid min-h-screen place-items-center bg-surface text-sm text-muted">Mağaza yükleniyor...</main>;
  }

  const globalDocument = documentWithSections(runtime.document, ['announcement', 'header']);
  const footerDocument = documentWithSections(runtime.document, ['footer']);

  return (
    <div className="min-h-screen bg-surface text-dark">
      {globalDocument.sections.length > 0 ? <StorefrontRenderer store={runtime.store} document={globalDocument} products={runtime.products} mode="published" compact /> : null}
      <Outlet context={{ store: runtime.store, runtime } satisfies StorefrontContext} />
      {footerDocument.sections.length > 0 ? <StorefrontRenderer store={runtime.store} document={footerDocument} products={runtime.products} mode="published" compact /> : null}
    </div>
  );
}

function documentWithSections(document: StorefrontRuntimeDocument, types: string[]): StorefrontRuntimeDocument {
  return {
    ...document,
    sections: document.sections.filter((section) => types.includes(section.type)),
  };
}

const router = createBrowserRouter([
  {
    element: <StorefrontLayout />,
    errorElement: <StorefrontErrorPage />,
    children: [
      { path: '/', element: <StorefrontHomePage /> },
      { path: '/products', element: <ProductsPage /> },
      { path: '/products/:slug', element: <ProductPage /> },
      { path: '/wishlist', element: <WishlistPage /> },
      { path: '/cart', element: <CartPage /> },
      { path: '/checkouts/:token', element: <CheckoutPage /> },
      { path: '/checkouts/:token/confirmation', element: <CheckoutConfirmationPage /> },
      { path: '*', element: <StorefrontErrorPage status={404} /> },
    ],
  },
]);

export function StorefrontApp() {
  return <RouterProvider router={router} />;
}
