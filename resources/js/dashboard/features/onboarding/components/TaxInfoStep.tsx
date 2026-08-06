import { useMemo, useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { describeApiError } from '../../../utils/errors';
import { submitTaxProfile } from '../api/onboardingApi';
import { useAuth } from '../../../app/providers/AuthProvider';
import { TURKEY_PROVINCES } from '../../../data/turkeyProvinces';
import { TAX_OFFICES_BY_PROVINCE } from '../../../data/turkeyTaxOffices';

export function TaxInfoStep() {
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [province, setProvince] = useState('');
  const [taxOffice, setTaxOffice] = useState('');

  const taxOffices = useMemo(() => (province ? TAX_OFFICES_BY_PROVINCE[province] ?? [] : []), [province]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    try {
      await submitTaxProfile({
        tax_number: String(form.get('tax_number')),
        legal_entity_name: String(form.get('legal_entity_name')),
        tax_office: taxOffice,
      });
      await refresh();
    } catch (err) {
      setError(describeApiError(err).message ?? 'Vergi bilgileri kaydedilemedi.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} className="flex flex-col gap-6">
        <div>
          <h2 className="text-lg font-semibold text-dark">Vergi Bilgileri</h2>
          <p className="mt-1 text-sm text-muted">Vergi levhandaki bilgilerle birebir eşleşmesine dikkat et.</p>
        </div>

        <div className="flex flex-col gap-4">
          <Input label="Vergi numarası" name="tax_number" required inputMode="numeric" placeholder="10 haneli VKN / 11 haneli TCKN" />
          <Input label="Şirket unvanı" name="legal_entity_name" required />

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Select
              label="Vergi dairesinin ili"
              required
              placeholder="İl seçin"
              value={province}
              onChange={(e) => {
                setProvince(e.target.value);
                setTaxOffice('');
              }}
            >
              {TURKEY_PROVINCES.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </Select>
            <Select
              label="Vergi dairesi"
              name="tax_office"
              required
              disabled={!province}
              placeholder={province ? 'Vergi dairesi seçin' : 'Önce il seçin'}
              value={taxOffice}
              onChange={(e) => setTaxOffice(e.target.value)}
            >
              {taxOffices.map((office) => (
                <option key={office} value={office}>
                  {office}
                </option>
              ))}
            </Select>
          </div>
        </div>

        {error && <p className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Kaydediliyor…' : 'Devam Et'}
        </Button>
      </form>
    </Card>
  );
}
