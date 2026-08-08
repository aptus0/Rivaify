import type { Checkout } from '../types';
import { formatMoney } from '../utils';
import { DiscountCodeInput } from './DiscountCodeInput';

export function CheckoutOrderSummary({
  checkout,
  applyingDiscount,
  onApplyDiscount,
}: {
  checkout: Checkout;
  applyingDiscount: boolean;
  onApplyDiscount: (code: string) => void;
}) {
  return (
    <aside className="border-t border-border bg-app-bg px-5 py-5 lg:sticky lg:top-0 lg:min-h-screen lg:border-l lg:border-t-0 lg:px-8 lg:py-8">
      <details className="group lg:hidden"><summary className="cursor-pointer list-none font-semibold text-dark">Sipariş özeti <span className="float-right">{formatMoney(checkout.grand_total, checkout.currency)}</span></summary><SummaryContent checkout={checkout} applyingDiscount={applyingDiscount} onApplyDiscount={onApplyDiscount} /></details>
      <div className="hidden lg:block"><h2 className="text-lg font-semibold text-dark">Sipariş özeti</h2><SummaryContent checkout={checkout} applyingDiscount={applyingDiscount} onApplyDiscount={onApplyDiscount} /></div>
    </aside>
  );
}

function SummaryContent({ checkout, applyingDiscount, onApplyDiscount }: { checkout: Checkout; applyingDiscount: boolean; onApplyDiscount: (code: string) => void }) {
  return (
    <div className="mt-5 space-y-4">
      <div className="divide-y divide-border border-y border-border">
        {checkout.cart.items.map((item) => (
          <div key={item.id} className="flex items-start justify-between gap-4 py-4"><div><p className="font-medium text-dark">{item.product.title}</p><p className="text-sm text-muted">{item.variant.title} · {item.quantity} adet</p></div><p className="shrink-0 font-medium text-dark">{formatMoney(item.line_total, checkout.currency)}</p></div>
        ))}
      </div>
      <DiscountCodeInput submitting={applyingDiscount} onApply={onApplyDiscount} />
      <dl className="space-y-2 text-sm"><div className="flex justify-between text-muted"><dt>Ara toplam</dt><dd>{formatMoney(checkout.subtotal, checkout.currency)}</dd></div>{checkout.discount_total !== '0.00' && <div className="flex justify-between text-emerald-700"><dt>İndirim</dt><dd>-{formatMoney(checkout.discount_total, checkout.currency)}</dd></div>}<div className="flex justify-between text-muted"><dt>Kargo</dt><dd>{formatMoney(checkout.shipping_total, checkout.currency)}</dd></div><div className="flex justify-between text-muted"><dt>Vergi</dt><dd>{formatMoney(checkout.tax_total, checkout.currency)}</dd></div><div className="flex justify-between border-t border-border pt-3 text-base font-semibold text-dark"><dt>Toplam</dt><dd>{formatMoney(checkout.grand_total, checkout.currency)}</dd></div></dl>
    </div>
  );
}