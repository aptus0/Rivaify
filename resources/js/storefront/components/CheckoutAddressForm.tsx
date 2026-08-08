import { useState, type FormEvent } from 'react';
import type { AddressInput } from '../types';

const EMPTY_ADDRESS: AddressInput = {
  first_name: '', last_name: '', country_code: 'TR', address_line_1: '', province: '', district: '', postal_code: '', phone: '',
};

function AddressFields({ value, onChange, prefix }: { value: AddressInput; onChange: (value: AddressInput) => void; prefix: string }) {
  function set(field: keyof AddressInput, next: string) { onChange({ ...value, [field]: next }); }

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <label className="text-sm font-medium text-dark">Ad<input required value={value.first_name} onChange={(event) => set('first_name', event.target.value)} name={`${prefix}-first-name`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark">Soyad<input required value={value.last_name} onChange={(event) => set('last_name', event.target.value)} name={`${prefix}-last-name`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark sm:col-span-2">Adres<input required value={value.address_line_1} onChange={(event) => set('address_line_1', event.target.value)} name={`${prefix}-address`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark">İl<input value={value.province ?? ''} onChange={(event) => set('province', event.target.value)} name={`${prefix}-province`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark">İlçe<input value={value.district ?? ''} onChange={(event) => set('district', event.target.value)} name={`${prefix}-district`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark">Posta kodu<input value={value.postal_code ?? ''} onChange={(event) => set('postal_code', event.target.value)} name={`${prefix}-postal-code`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="text-sm font-medium text-dark">Telefon<input value={value.phone ?? ''} onChange={(event) => set('phone', event.target.value)} name={`${prefix}-phone`} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
    </div>
  );
}

export function CheckoutAddressForm({
  initial,
  submitting,
  onSubmit,
}: {
  initial: AddressInput | null;
  submitting: boolean;
  onSubmit: (shipping: AddressInput, billingSameAsShipping: boolean, billing?: AddressInput) => void;
}) {
  const [shipping, setShipping] = useState<AddressInput>(initial ?? EMPTY_ADDRESS);
  const [billingSame, setBillingSame] = useState(true);
  const [billing, setBilling] = useState<AddressInput>(EMPTY_ADDRESS);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit(shipping, billingSame, billingSame ? undefined : billing);
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      <div><h2 className="text-lg font-semibold text-dark">Teslimat adresi</h2><p className="mt-1 text-sm text-muted">Kargo seçeneğini adresine göre göstereceğiz.</p></div>
      <AddressFields value={shipping} onChange={setShipping} prefix="shipping" />
      <label className="flex items-center gap-2 text-sm text-dark"><input type="checkbox" checked={billingSame} onChange={(event) => setBillingSame(event.target.checked)} className="h-4 w-4 accent-primary" />Fatura adresim aynı</label>
      {!billingSame && <div className="border-t border-border pt-5"><h3 className="mb-4 font-medium text-dark">Fatura adresi</h3><AddressFields value={billing} onChange={setBilling} prefix="billing" /></div>}
      <button disabled={submitting} className="w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50">{submitting ? 'Kaydediliyor...' : 'Kargo seçeneklerini gör'}</button>
    </form>
  );
}