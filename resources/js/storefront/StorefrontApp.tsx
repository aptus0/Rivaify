import { useEffect, useState } from 'react';
import { Package, ShoppingBag } from 'lucide-react';
import { Link, Outlet, RouterProvider, createBrowserRouter, useOutletContext } from 'react-router-dom';
import { getStore } from './api';
import type { StorefrontStore } from './types';
import { CartPage } from './pages/CartPage';
import { CheckoutConfirmationPage } from './pages/CheckoutConfirmationPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { ProductPage } from './pages/ProductPage';
import { ProductsPage } from './pages/ProductsPage';

interface StorefrontContext {
  store: StorefrontStore;
}

export function useStorefront(): StorefrontContext {
  return useOutletContext<StorefrontContext>();
}

function StorefrontLayout() {
  const [store, setStore] = useState<StorefrontStore | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void getStore()
      .then((response) => {
        if (active) setStore(response.data);
      })
      .catch(() => {
        if (active) setError('Mağaza şu anda kullanılamıyor.');
      });

    return () => {
      active = false;
    };
  }, []);

  if (error) {
    return <main className="grid min-h-screen place-items-center bg-surface p-6 text-center text-sm text-muted">{error}</main>;
  }
  if (!store) {
    return <main className="grid min-h-screen place-items-center bg-surface text-sm text-muted">Mağaza yükleniyor...</main>;
  }

  return (
    <div className="min-h-screen bg-surface text-dark">
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-7">
          <Link to="/" className="flex min-w-0 items-center gap-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-dark text-sm font-bold text-white">{store.name.slice(0, 1).toUpperCase()}</span>
            <span className="truncate text-base font-semibold tracking-normal">{store.name}</span>
          </Link>
          <Link to="/cart" className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-dark hover:bg-app-bg"><ShoppingBag size={18} />Sepet</Link>
        </div>
      </header>
      <Outlet context={{ store } satisfies StorefrontContext} />
      <footer className="border-t border-border bg-card"><div className="mx-auto flex max-w-6xl items-center gap-2 px-5 py-5 text-xs text-muted sm:px-7"><Package size={14} />{store.name}</div></footer>
    </div>
  );
}

const router = createBrowserRouter([
  {
    element: <StorefrontLayout />,
    children: [
      { path: '/', element: <ProductsPage /> },
      { path: '/products/:slug', element: <ProductPage /> },
      { path: '/cart', element: <CartPage /> },
      { path: '/checkouts/:token', element: <CheckoutPage /> },
      { path: '/checkouts/:token/confirmation', element: <CheckoutConfirmationPage /> },
    ],
  },
]);

export function StorefrontApp() {
  return <RouterProvider router={router} />;
}