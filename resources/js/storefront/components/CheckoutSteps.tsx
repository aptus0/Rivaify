import type { Checkout } from '../types';

const STEPS = ['Bilgiler', 'Teslimat', 'Ödeme'] as const;

function stepIndex(status: Checkout['status']): number {
  if (status === 'initiated' || status === 'customer_information') return 0;
  if (status === 'address' || status === 'shipping') return 1;
  return 2;
}

export function CheckoutSteps({ status }: { status: Checkout['status'] }) {
  const active = stepIndex(status);

  return (
    <ol className="flex items-center gap-2 text-sm" aria-label="Checkout adımları">
      {STEPS.map((step, index) => (
        <li key={step} className="flex items-center gap-2">
          {index > 0 && <span className="h-px w-5 bg-border sm:w-8" />}
          <span className={index <= active ? 'font-medium text-dark' : 'text-muted'}>{step}</span>
        </li>
      ))}
    </ol>
  );
}