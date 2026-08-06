import { useMemo, useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { describeApiError } from '../../../utils/errors';
import { submitBusinessProfile } from '../api/onboardingApi';
import { useAuth } from '../../../app/providers/AuthProvider';
import { TURKEY_DISTRICTS_BY_PROVINCE, TURKEY_PROVINCES } from '../../../data/turkeyProvinces';

export function BusinessInfoStep() {
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [province, setProvince] = useState('');
  const [district, setDistrict] = useState('');

  const districts = useMemo(() => (province ? TURKEY_DISTRICTS_BY_PROVINCE[province] ?? [] : []), [province]);

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
            city: province,
            state: district,
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
    <Card>
      <form onSubmit={handleSubmit} className="flex flex-col gap-6">
        <div>
          <h2 className="text-lg font-semibold text-dark">İşletme Bilgileri</h2>
          <p className="mt-1 text-sm text-muted">Rivaify'da bu bilgiler yasal fatura ve doğrulama süreçlerinde kullanılır.</p>
        </div>

        <div className="flex flex-col gap-4">
          <h3 className="text-sm font-semibold text-neutral-500 uppercase tracking-wide">Firma Bilgileri</h3>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input label="Yasal ünvan" name="legal_name" required />
            <Input label="Ticari ünvan (opsiyonel)" name="trade_name" />
          </div>
          <Input label="İletişim e-postası" name="contact_email" type="email" />
        </div>

        <div className="flex flex-col gap-4">
          <h3 className="text-sm font-semibold text-neutral-500 uppercase tracking-wide">Adres</h3>
          <Input label="Açık adres" name="line1" required placeholder="Mah. Sk. No" />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Select
              label="İl"
              name="city"
              required
              placeholder="İl seçin"
              value={province}
              onChange={(e) => {
                setProvince(e.target.value);
                setDistrict('');
              }}
            >
              {TURKEY_PROVINCES.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </Select>
            <Select
              label="İlçe"
              name="state"
              required
              disabled={!province}
              placeholder={province ? 'İlçe seçin' : 'Önce il seçin'}
              value={district}
              onChange={(e) => setDistrict(e.target.value)}
            >
              {districts.map((d) => (
                <option key={d} value={d}>
                  {d}
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
