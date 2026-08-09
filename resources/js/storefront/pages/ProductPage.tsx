import { useEffect, useState } from 'react';
import { ArrowLeft, Package, ShoppingBag } from 'lucide-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { addCartItem, getProduct } from '../api';
import { trackStorefrontEvent } from '../analytics';
import { useStorefront } from '../context';
import type { StorefrontProduct } from '../types';
import { formatMoney } from '../utils';

export function ProductPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { store } = useStorefront();
  const [product, setProduct] = useState<StorefrontProduct | null>(null);
  const [selectedVariantId, setSelectedVariantId] = useState<string | null>(null);
  const [selectedMediaId, setSelectedMediaId] = useState<string | null>(null);
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
        trackStorefrontEvent('product_view', { product_id: response.data.id });
        setSelectedVariantId((response.data.variants.find((variant) => variant.available) ?? response.data.variants[0])?.id ?? null);
        setSelectedMediaId((response.data.media.find((item) => item.is_featured) ?? response.data.media[0])?.id ?? null);
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
      trackStorefrontEvent('add_to_cart', { product_id: product?.id });
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
  const selectedMedia = product.media.find((item) => item.id === selectedMediaId) ?? product.media[0];

  return (
    <main className="mx-auto max-w-6xl px-5 py-8 sm:px-7 sm:py-10">
      <Link to="/" className="mb-6 inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"><ArrowLeft size={16} /> Mağaza</Link>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.8fr)] lg:gap-12">
        <div><div className="grid aspect-square place-items-center overflow-hidden bg-app-bg text-muted">{selectedMedia ? <img src={selectedMedia.url} alt={selectedMedia.alt_text ?? product.title} className="h-full w-full object-contain" /> : <Package size={72} strokeWidth={1.1} />}</div>{product.media.length > 1 && <div className="mt-3 grid grid-cols-5 gap-2">{product.media.map((media) => <button key={media.id} onClick={() => setSelectedMediaId(media.id)} className={`aspect-square overflow-hidden rounded-md border ${media.id === selectedMedia?.id ? 'border-primary' : 'border-border'}`}><img src={media.url} alt={media.alt_text ?? product.title} className="h-full w-full object-cover" /></button>)}</div>}</div>
        <div className="flex flex-col">
          <p className="text-sm font-medium text-primary-hover">{store.name}</p>
          <h1 className="mt-2 text-2xl font-semibold text-dark">{product.title}</h1>
          {selectedVariant && <div className="mt-3 flex items-center gap-3"><p className="text-xl font-semibold text-dark">{formatMoney(selectedVariant.price, store.currency)}</p>{selectedVariant.compare_at_price && Number(selectedVariant.compare_at_price) > Number(selectedVariant.price) && <p className="text-sm text-muted line-through">{formatMoney(selectedVariant.compare_at_price, store.currency)}</p>}</div>}
          {product.description && <div className="prose prose-sm mt-5 max-w-none text-muted" dangerouslySetInnerHTML={{ __html: product.description }} />}
          <div className="mt-7 space-y-3"><p className="text-sm font-medium text-dark">Varyant</p><div className="flex flex-wrap gap-2">{product.variants.map((variant) => <button key={variant.id} disabled={!variant.available} onClick={() => setSelectedVariantId(variant.id)} className={`rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40 ${variant.id === selectedVariantId ? 'border-primary bg-surface-orange text-primary-hover' : 'border-border text-dark hover:border-muted'}`}>{variant.title}{!variant.available ? ' · Tükendi' : ''}</button>)}</div></div>
          <div className="mt-7 flex items-end gap-3"><label className="flex flex-col gap-1 text-sm font-medium text-dark">Adet<select value={quantity} onChange={(event) => setQuantity(Number(event.target.value))} className="rounded-md border border-border bg-card px-3 py-2.5 text-sm outline-none focus:border-primary">{[1, 2, 3, 4, 5].filter((value) => selectedVariant?.available_quantity === null || value <= selectedVariant.available_quantity).map((value) => <option key={value} value={value}>{value}</option>)}</select></label><button disabled={!selectedVariant?.available || adding} onClick={addToCart} className="flex flex-1 items-center justify-center gap-2 rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"><ShoppingBag size={17} />{adding ? 'Ekleniyor...' : selectedVariant?.available ? 'Sepete ekle' : 'Stokta yok'}</button></div>
          {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
        </div>
      </div>
    </main>
  );
}
