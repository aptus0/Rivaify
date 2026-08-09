import { Heart, Package, ShoppingBag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { getRuntime } from '../api';
import type { StorefrontProduct, StorefrontRuntime } from '../types';
import { formatMoney } from '../utils';

const WISHLIST_KEY = 'rivaify:wishlist:v1';

function readWishlist(): string[] {
  try {
    const raw = window.localStorage.getItem(WISHLIST_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function writeWishlist(ids: string[]): void {
  try {
    window.localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
  } catch {
    // Wishlist storage is intentionally non-critical.
  }
}

function WishlistProduct({ product, currency, onRemove }: { product: StorefrontProduct; currency: string; onRemove: () => void }) {
  const media = product.media.find((item) => item.is_featured) ?? product.media[0];
  const variant = product.variants[0];

  return (
    <article className="grid gap-4 rounded-md border border-zinc-200 bg-white p-3 sm:grid-cols-[9rem_minmax(0,1fr)]">
      <Link to={`/products/${product.slug}`} className="grid aspect-[4/5] place-items-center overflow-hidden rounded-md bg-zinc-100 text-zinc-400">
        {media ? <img src={media.url} alt={media.alt_text ?? product.title} className="h-full w-full object-cover" /> : <Package className="h-8 w-8" />}
      </Link>
      <div className="flex min-w-0 flex-col justify-between gap-4 py-1">
        <div>
          <Link to={`/products/${product.slug}`} className="font-semibold text-zinc-950 hover:text-zinc-600">{product.title}</Link>
          <p className="mt-2 text-sm text-zinc-600">{variant ? formatMoney(variant.price, currency) : 'Fiyat yakında'}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link to={`/products/${product.slug}`} className="inline-flex items-center gap-2 rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800"><ShoppingBag className="h-4 w-4" />Ürüne git</Link>
          <button type="button" onClick={onRemove} className="inline-flex items-center gap-2 rounded-md border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"><Heart className="h-4 w-4 fill-current" />Kaldır</button>
        </div>
      </div>
    </article>
  );
}

export function WishlistPage() {
  const [runtime, setRuntime] = useState<StorefrontRuntime | null>(null);
  const [ids, setIds] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setIds(readWishlist());
    let active = true;
    void getRuntime()
      .then((response) => {
        if (active) setRuntime(response.data);
      })
      .catch(() => {
        if (active) setError('Favoriler şu anda yüklenemedi.');
      });

    return () => {
      active = false;
    };
  }, []);

  const products = useMemo(() => {
    if (!runtime) return [];
    return runtime.products.filter((product) => ids.includes(product.id));
  }, [runtime, ids]);

  function remove(productId: string) {
    const next = ids.filter((id) => id !== productId);
    setIds(next);
    writeWishlist(next);
  }

  if (error) return <main className="grid min-h-[50vh] place-items-center px-5 py-16 text-center text-sm text-red-600">{error}</main>;
  if (!runtime) return <main className="grid min-h-[50vh] place-items-center px-5 py-16 text-sm text-zinc-500">Favoriler yükleniyor...</main>;

  return (
    <main className="bg-zinc-50 px-5 py-10 sm:px-7">
      <div className="mx-auto max-w-5xl">
        <div className="mb-7">
          <p className="text-xs font-semibold uppercase tracking-[.18em] text-zinc-500">Wishlist</p>
          <h1 className="mt-2 text-3xl font-semibold text-zinc-950">Favorilerim</h1>
          <p className="mt-2 text-sm text-zinc-600">Beğendiğiniz ürünleri burada saklayın ve sonra kolayca geri dönün.</p>
        </div>

        {products.length === 0 ? (
          <div className="grid min-h-72 place-items-center rounded-md border border-dashed border-zinc-300 bg-white text-center">
            <div>
              <Heart className="mx-auto h-8 w-8 text-zinc-400" />
              <p className="mt-4 font-semibold text-zinc-950">Henüz favori ürününüz yok.</p>
              <p className="mt-2 text-sm text-zinc-500">Ürün kartlarındaki kalp ikonuyla listenizi oluşturabilirsiniz.</p>
              <Link to="/products" className="mt-5 inline-flex rounded-md bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Ürünleri keşfet</Link>
            </div>
          </div>
        ) : (
          <div className="grid gap-3">
            {products.map((product) => <WishlistProduct key={product.id} product={product} currency={runtime.store.currency} onRemove={() => remove(product.id)} />)}
          </div>
        )}
      </div>
    </main>
  );
}
