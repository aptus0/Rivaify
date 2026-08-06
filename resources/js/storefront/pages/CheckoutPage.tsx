// TEMPORARY placeholder — unblocks the storefront build (vite.config.js
// already registers resources/js/storefront/main.tsx as an entry, and
// StorefrontApp.tsx imports this page, but the real implementation wasn't
// written yet as of 2026-08-06). Replace with the actual checkout flow.
export function CheckoutPage() {
  return (
    <div className="mx-auto max-w-xl px-4 py-16 text-center">
      <h1 className="text-xl font-semibold">Ödeme</h1>
      <p className="mt-2 text-sm text-neutral-500">Bu sayfa henüz yapım aşamasında.</p>
    </div>
  );
}
