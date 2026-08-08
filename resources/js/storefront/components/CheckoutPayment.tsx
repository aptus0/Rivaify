import { CreditCard, LockKeyhole } from 'lucide-react';

export function CheckoutPayment({ submitting, onPay }: { submitting: boolean; onPay: () => void }) {
  return (
    <section className="space-y-4">
      <div><h2 className="text-lg font-semibold text-dark">Ödeme</h2><p className="mt-1 text-sm text-muted">Ödeme yöntemini seç ve siparişini tamamla.</p></div>
      <div className="flex items-center gap-3 rounded-md border border-primary bg-surface-orange p-4"><CreditCard size={20} className="text-primary" /><div><p className="font-medium text-dark">Kart ile ödeme</p><p className="text-sm text-muted">Ödeme sağlayıcısı yönlendirmesiyle tamamlanır.</p></div></div>
      <button disabled={submitting} onClick={onPay} className="flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"><LockKeyhole size={16} />{submitting ? 'Ödeme işleniyor...' : 'Siparişi tamamla'}</button>
    </section>
  );
}