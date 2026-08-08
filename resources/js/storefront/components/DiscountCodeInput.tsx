import { useState, type FormEvent } from 'react';

export function DiscountCodeInput({ submitting, onApply }: { submitting: boolean; onApply: (code: string) => void }) {
  const [code, setCode] = useState('');

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (code.trim()) onApply(code.trim());
  }

  return (
    <form onSubmit={submit} className="flex gap-2 border-t border-border pt-4">
      <label className="sr-only" htmlFor="discount-code">İndirim kodu</label>
      <input id="discount-code" value={code} onChange={(event) => setCode(event.target.value)} placeholder="İndirim kodu" className="min-w-0 flex-1 rounded-md border border-border px-3 py-2 text-sm uppercase outline-none focus:border-primary" />
      <button disabled={submitting || !code.trim()} className="shrink-0 rounded-md border border-border px-3 py-2 text-sm font-medium text-dark hover:bg-app-bg disabled:opacity-50">Uygula</button>
    </form>
  );
}