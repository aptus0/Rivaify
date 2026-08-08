import { useState, type FormEvent } from 'react';

export interface ContactInput {
  email: string;
  first_name: string;
  last_name: string;
  phone: string;
  accepts_marketing: boolean;
}

export function CheckoutContactForm({
  initial,
  submitting,
  onSubmit,
}: {
  initial: Partial<ContactInput>;
  submitting: boolean;
  onSubmit: (input: ContactInput) => void;
}) {
  const [form, setForm] = useState<ContactInput>({
    email: initial.email ?? '',
    first_name: initial.first_name ?? '',
    last_name: initial.last_name ?? '',
    phone: initial.phone ?? '',
    accepts_marketing: initial.accepts_marketing ?? false,
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit(form);
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div><h2 className="text-lg font-semibold text-dark">İletişim</h2><p className="mt-1 text-sm text-muted">Sipariş bilgilerini buradan göndereceğiz.</p></div>
      <label className="block text-sm font-medium text-dark">E-posta<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-medium text-dark">Ad<input required value={form.first_name} onChange={(event) => setForm({ ...form, first_name: event.target.value })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
        <label className="block text-sm font-medium text-dark">Soyad<input required value={form.last_name} onChange={(event) => setForm({ ...form, last_name: event.target.value })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      </div>
      <label className="block text-sm font-medium text-dark">Telefon<input type="tel" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 text-sm outline-none focus:border-primary" /></label>
      <label className="flex items-start gap-2 text-sm text-muted"><input type="checkbox" checked={form.accepts_marketing} onChange={(event) => setForm({ ...form, accepts_marketing: event.target.checked })} className="mt-0.5 h-4 w-4 accent-primary" />Kampanya ve yeni ürün haberlerini almak istiyorum.</label>
      <button disabled={submitting} className="w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50">{submitting ? 'Kaydediliyor...' : 'Teslimat bilgilerine devam et'}</button>
    </form>
  );
}