import { useEffect, useState } from 'react';
import { ArrowUpRight, Package } from 'lucide-react';
import { Link } from 'react-router-dom';
import { listProducts } from '../api';
import { useStorefront } from '../context';
import type { StorefrontProduct } from '../types';
import { formatMoney } from '../utils';

export function ProductsPage() {
  const { store } = useStorefront();
  const [products, setProducts] = useState<StorefrontProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void listProducts()
      .then((response) => {
        if (active) setProducts(response.data);
      })
      .catch(() => {
        if (active) setError('Ürünler yüklenemedi.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  return (
    <main className="mx-auto min-h-[calc(100vh-9rem)] max-w-6xl px-5 py-10 sm:px-7">
      <div className="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div><p className="text-sm font-medium text-primary-hover">{store.name}</p><h1 className="mt-1 text-2xl font-semibold text-dark">Mağaza</h1></div>
        <p className="text-sm text-muted">{products.length} ürün</p>
      </div>
      {loading ? <p className="text-sm text-muted">Ürünler yükleniyor...</p> : error ? <p className="text-sm text-red-600">{error}</p> : products.length === 0 ? (
        <div className="grid min-h-56 place-items-center border border-dashed border-border text-center"><div><Package size={24} className="mx-auto text-muted" /><p className="mt-3 text-sm text-muted">Henüz satışta ürün yok.</p></div></div>
      ) : (
        <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((product) => {
            const startingVariant = product.variants[0];
            const featuredMedia = product.media.find((item) => item.is_featured) ?? product.media[0];
            return (
              <Link key={product.id} to={`/products/${product.slug}`} className="group border-b border-border pb-4">
                <div className="grid aspect-[4/3] place-items-center overflow-hidden bg-app-bg text-muted transition group-hover:bg-surface-orange">{featuredMedia ? <img src={featuredMedia.url} alt={featuredMedia.alt_text ?? product.title} loading="lazy" className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" /> : <Package size={40} strokeWidth={1.25} />}</div>
                <div className="mt-4 flex items-start justify-between gap-3"><div><h2 className="font-medium text-dark">{product.title}</h2><p className="mt-1 text-sm text-muted">{startingVariant ? formatMoney(startingVariant.price, store.currency) : 'Fiyat bilgisi yakında'}</p></div><ArrowUpRight size={18} className="mt-0.5 shrink-0 text-muted transition group-hover:text-primary" /></div>
              </Link>
            );
          })}
        </div>
      )}
    </main>
  );
}
