import type { ShippingQuote } from '../types';
import { formatMoney } from '../utils';

export function CheckoutShippingMethods({
  quotes,
  selectedId,
  submitting,
  onSelect,
}: {
  quotes: ShippingQuote[];
  selectedId: string | null;
  submitting: boolean;
  onSelect: (id: string) => void;
}) {
  return (
    <section className="space-y-4">
      <div><h2 className="text-lg font-semibold text-dark">Teslimat</h2><p className="mt-1 text-sm text-muted">Siparişini nasıl almak istersin?</p></div>
      <div className="space-y-2">
        {quotes.map((quote) => (
          <button key={quote.id} disabled={submitting} onClick={() => onSelect(quote.id)} className={`flex w-full items-center justify-between gap-4 rounded-md border p-4 text-left transition ${selectedId === quote.id ? 'border-primary bg-surface-orange' : 'border-border bg-card hover:border-muted'}`}>
            <span><span className="block font-medium text-dark">{quote.name}</span><span className="mt-1 block text-sm text-muted">{quote.estimated_days_min && quote.estimated_days_max ? `${quote.estimated_days_min}-${quote.estimated_days_max} iş günü` : 'Teslimat süresi kargo firması tarafından belirlenir'}</span></span>
            <span className="font-semibold text-dark">{formatMoney(quote.amount, quote.currency)}</span>
          </button>
        ))}
      </div>
      {quotes.length === 0 && <p className="rounded-md border border-border p-4 text-sm text-muted">Bu adrese uygun bir kargo yöntemi bulunamadı.</p>}
    </section>
  );
}