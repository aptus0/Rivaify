import { useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { describeApiError } from '../../../utils/errors';
import { submitTaxProfile } from '../api/onboardingApi';
import { useAuth } from '../../../app/providers/AuthProvider';

export function TaxInfoStep() {
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    try {
      await submitTaxProfile({
        tax_number: String(form.get('tax_number')),
        legal_entity_name: String(form.get('legal_entity_name')),
        tax_office: String(form.get('tax_office') || '') || undefined,
      });
      await refresh();
    } catch (err) {
      setError(describeApiError(err).message ?? 'Vergi bilgileri kaydedilemedi.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h2 className="text-lg font-medium">Vergi Bilgileri</h2>
      <Input label="Vergi numarası" name="tax_number" required />
      <Input label="Vergi dairesi (opsiyonel)" name="tax_office" />
      <Input label="Şirket unvanı" name="legal_entity_name" required />
      {error && <p className="text-sm text-red-600">{error}</p>}
      <Button type="submit" disabled={submitting}>
        {submitting ? 'Kaydediliyor…' : 'Devam Et'}
      </Button>
    </form>
  );
}
