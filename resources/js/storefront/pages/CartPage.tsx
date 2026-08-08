import { useEffect, useState } from 'react';
import { ArrowRight, Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { getCart, removeCartItem, startCheckout, updateCartItem } from '../api';
import type { Cart } from '../types';
import { formatMoney } from '../utils';

export function CartPage() {
  const navigate = useNavigate();
  const [cart, setCart] = useState<Cart | null>(null);
  const [busyItemId, setBusyItemId] = useState<string | null>(null);
  const [startingCheckout, setStartingCheckout] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void getCart().then((response) => { if (active) setCart(response.data); }).catch(() => { if (active) setError('Sepet yüklenemedi.'); });
    return () => { active = false; };
  }, []);

  async function updateItem(itemId: string, quantity: number) {
    setBusyItemId(itemId);
    setError(null);
    try {
      const response = quantity === 0 ? await removeCartItem(itemId) : await updateCartItem(itemId, quantity);
      setCart(response.data);
    } catch {
      setError('Sepet güncellenemedi.');
    } finally {
      setBusyItemId(null);
    }
  }

  async function checkout() {
    setStartingCheckout(true);
    setError(null);
    try {
      const response = await startCheckout();
      navigate(`/checkouts/${response.data.token}`);
    } catch {
      setError('Checkout başlatılamadı.');
    } finally {
      setStartingCheckout(false);
    }
  }

  if (!cart) return <main className="mx-auto max-w-5xl px-5 py-10 text-sm text-muted sm:px-7">Sepet yükleniyor...</main>;

  return (
    <main className="mx-auto min-h-[calc(100vh-9rem)] max-w-5xl px-5 py-8 sm:px-7 sm:py-10">
      <h1 className="text-2xl font-semibold text-dark">Sepet</h1>
      {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
      {cart.items.length === 0 ? (
        <div className="mt-8 grid min-h-64 place-items-center border border-dashed border-border text-center"><div><ShoppingBag size={28} className="mx-auto text-muted" /><p className="mt-3 text-sm text-muted">Sepetin henüz boş.</p><Link to="/" className="mt-4 inline-block text-sm font-medium text-primary-hover">Ürünlere dön</Link></div></div>
      ) : (
        <div className="mt-7 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <div className="divide-y divide-border border-y border-border">
            {cart.items.map((item) => <div key={item.id} className="flex items-start gap-4 py-5"><div className="grid h-20 w-20 shrink-0 place-items-center bg-app-bg text-muted"><ShoppingBag size={22} /></div><div className="min-w-0 flex-1"><p className="font-medium text-dark">{item.product.title}</p><p className="mt-1 text-sm text-muted">{item.variant.title}</p><p className="mt-2 text-sm font-medium text-dark">{formatMoney(item.unit_price, cart.currency)}</p><div className="mt-3 inline-flex items-center rounded-md border border-border"><button disabled={busyItemId === item.id} onClick={() => void updateItem(item.id, item.quantity - 1)} className="p-2 text-muted hover:text-dark disabled:opacity-50" aria-label="Adedi azalt"><Minus size={15} /></button><span className="w-8 text-center text-sm font-medium">{item.quantity}</span><button disabled={busyItemId === item.id} onClick={() => void updateItem(item.id, item.quantity + 1)} className="p-2 text-muted hover:text-dark disabled:opacity-50" aria-label="Adedi artır"><Plus size={15} /></button></div></div><div className="flex flex-col items-end gap-3"><button disabled={busyItemId === item.id} onClick={() => void updateItem(item.id, 0)} className="text-muted hover:text-red-600" aria-label="Ürünü kaldır"><Trash2 size={17} /></button><p className="font-semibold text-dark">{formatMoney(item.line_total, cart.currency)}</p></div></div>)}
          </div>
          <aside className="h-fit border border-border bg-app-bg p-5"><h2 className="font-semibold text-dark">Sipariş özeti</h2><dl className="mt-5 space-y-3 text-sm"><div className="flex justify-between text-muted"><dt>Ara toplam</dt><dd>{formatMoney(cart.subtotal, cart.currency)}</dd></div>{cart.discount_total !== '0.00' && <div className="flex justify-between text-emerald-700"><dt>İndirim</dt><dd>-{formatMoney(cart.discount_total, cart.currency)}</dd></div>}<div className="flex justify-between border-t border-border pt-3 text-base font-semibold text-dark"><dt>Toplam</dt><dd>{formatMoney(cart.grand_total, cart.currency)}</dd></div></dl><button disabled={startingCheckout} onClick={() => void checkout()} className="mt-6 flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50">{startingCheckout ? 'Yönlendiriliyor...' : 'Checkout’a devam et'}<ArrowRight size={17} /></button></aside>
        </div>
      )}
    </main>
  );
}