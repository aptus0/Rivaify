import { useEffect, useState } from 'react';
import { ArrowLeft, Package, ShoppingBag } from 'lucide-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { addCartItem, getProduct } from '../api';
import { useStorefront } from '../StorefrontApp';
import type { StorefrontProduct } from '../types';
import { formatMoney } from '../utils';

export function ProductPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { store } = useStorefront();
  const [product, setProduct] = useState<StorefrontProduct | null>(null);
  const [selectedVariantId, setSelectedVariantId] = useState<string | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!slug) return;
    let active = true;
    void getProduct(slug)
      .then((response) => {
        if (!active) return;
        setProduct(response.data);
        setSelectedVariantId(response.data.variants[0]?.id ?? null);
      })
      .catch(() => {
        if (active) setError('Ürün bulunamadı.');
      });

    return () => {
      active = false;
    };
  }, [slug]);

  async function addToCart() {
    if (!selectedVariantId) return;
    setAdding(true);
    setError(null);
    try {
      await addCartItem(selectedVariantId, quantity);
      navigate('/cart');
    } catch {
      setError('Ürün sepete eklenemedi.');
    } finally {
      setAdding(false);
    }
  }

  if (error && !product) return <main className="mx-auto max-w-6xl px-5 py-10 text-sm text-red-600 sm:px-7">{error}</main>;
  if (!product) return <main className="mx-auto max-w-6xl px-5 py-10 text-sm text-muted sm:px-7">Ürün yükleniyor...</main>;

  const selectedVariant = product.variants.find((variant) => variant.id === selectedVariantId) ?? product.variants[0];

  return (
    <main className="mx-auto max-w-6xl px-5 py-8 sm:px-7 sm:py-10">
      <Link to="/" className="mb-6 inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"><ArrowLeft size={16} /> Mağaza</Link>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.8fr)] lg:gap-12">
        <div className="grid aspect-square place-items-center bg-app-bg text-muted"><Package size={72} strokeWidth={1.1} /></div>
        <div className="flex flex-col">
          <p className="text-sm font-medium text-primary-hover">{store.name}</p>
          <h1 className="mt-2 text-2xl font-semibold text-dark">{product.title}</h1>
          {selectedVariant && <p className="mt-3 text-xl font-semibold text-dark">{formatMoney(selectedVariant.price, store.currency)}</p>}
          {product.description && <p className="mt-5 whitespace-pre-line text-sm leading-6 text-muted">{product.description}</p>}
          <div className="mt-7 space-y-3"><p className="text-sm font-medium text-dark">Varyant</p><div className="flex flex-wrap gap-2">{product.variants.map((variant) => <button key={variant.id} onClick={() => setSelectedVariantId(variant.id)} className={`rounded-md border px-3 py-2 text-sm ${variant.id === selectedVariantId ? 'border-primary bg-surface-orange text-primary-hover' : 'border-border text-dark hover:border-muted'}`}>{variant.title}</button>)}</div></div>
          <div className="mt-7 flex items-end gap-3"><label className="flex flex-col gap-1 text-sm font-medium text-dark">Adet<select value={quantity} onChange={(event) => setQuantity(Number(event.target.value))} className="rounded-md border border-border bg-card px-3 py-2.5 text-sm outline-none focus:border-primary">{[1, 2, 3, 4, 5].map((value) => <option key={value} value={value}>{value}</option>)}</select></label><button disabled={!selectedVariant || adding} onClick={addToCart} className="flex flex-1 items-center justify-center gap-2 rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"><ShoppingBag size={17} />{adding ? 'Ekleniyor...' : 'Sepete ekle'}</button></div>
          {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
        </div>
      </div>
    </main>
  );
}