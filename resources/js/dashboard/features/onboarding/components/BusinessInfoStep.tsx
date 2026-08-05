import { useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { describeApiError } from '../../../utils/errors';
import { submitBusinessProfile } from '../api/onboardingApi';
import { useAuth } from '../../../app/providers/AuthProvider';

export function BusinessInfoStep() {
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    try {
      await submitBusinessProfile({
        legal_name: String(form.get('legal_name')),
        trade_name: String(form.get('trade_name') || '') || undefined,
        contact_email: String(form.get('contact_email') || '') || undefined,
        addresses: [
          {
            type: 'registered',
            line1: String(form.get('line1')),
            city: String(form.get('city')),
            country_code: 'TR',
          },
        ],
      });
      await refresh();
    } catch (err) {
      setError(describeApiError(err).message ?? 'İşletme bilgileri kaydedilemedi.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h2 className="text-lg font-medium">İşletme Bilgileri</h2>
      <Input label="Yasal ünvan" name="legal_name" required />
      <Input label="Ticari ünvan (opsiyonel)" name="trade_name" />
      <Input label="İletişim e-postası" name="contact_email" type="email" />
      <Input label="Adres" name="line1" required placeholder="Mah. Sk. No" />
      <Input label="Şehir" name="city" required />
      {error && <p className="text-sm text-red-600">{error}</p>}
      <Button type="submit" disabled={submitting}>
        {submitting ? 'Kaydediliyor…' : 'Devam Et'}
      </Button>
    </form>
  );
}
