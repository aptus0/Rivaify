import { useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Select } from '../../../components/ui/Select';
import { describeApiError } from '../../../utils/errors';
import { submitVerificationRequest, uploadVerificationDocument, type DocumentType } from '../../verification/api/verificationApi';
import { useAuth } from '../../../app/providers/AuthProvider';

const DOCUMENT_TYPES: { value: DocumentType['type']; label: string }[] = [
  { value: 'tax_certificate', label: 'Vergi Levhası' },
  { value: 'identity', label: 'Kimlik' },
  { value: 'signature_circular', label: 'İmza Sirküleri' },
  { value: 'business_license', label: 'İşletme Ruhsatı' },
];

export function DocumentsStep() {
  const { refresh } = useAuth();
  const [uploaded, setUploaded] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleUpload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setUploading(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const file = form.get('file') as File | null;
    const type = form.get('type') as DocumentType['type'];
    if (!file || file.size === 0) {
      setError('Lütfen bir dosya seç.');
      setUploading(false);
      return;
    }
    try {
      await uploadVerificationDocument(file, type);
      setUploaded((prev) => [...prev, `${file.name} (${type})`]);
      event.currentTarget.reset();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Belge yüklenemedi.');
    } finally {
      setUploading(false);
    }
  }

  async function handleSubmitForReview() {
    setSubmitting(true);
    setError(null);
    try {
      await submitVerificationRequest();
      await refresh();
    } catch (err) {
      setError(describeApiError(err).message ?? 'Başvuru gönderilemedi.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card>
      <div className="flex flex-col gap-6">
        <div>
          <h2 className="text-lg font-semibold text-dark">Belgeler</h2>
          <p className="mt-1 text-sm text-muted">
            Doğrulama için vergi levhanı, kimliğini ve varsa imza sirkülerini yükle.
          </p>
        </div>

        <form onSubmit={handleUpload} className="flex flex-col gap-3 rounded-lg border border-dashed border-neutral-300 p-4">
          <Select label="Belge türü" name="type" placeholder="Belge türü seçin" required>
            {DOCUMENT_TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </Select>
          <div className="flex flex-col gap-1">
            <label htmlFor="file" className="text-sm font-medium text-neutral-700">
              Dosya
            </label>
            <input
              id="file"
              type="file"
              name="file"
              accept=".pdf,.jpg,.jpeg,.png"
              required
              className="rounded-md border border-neutral-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-app-bg file:px-3 file:py-1 file:text-sm file:font-medium file:text-dark"
            />
          </div>
          <Button type="submit" disabled={uploading}>
            {uploading ? 'Yükleniyor…' : 'Belge Yükle'}
          </Button>
        </form>

        {uploaded.length > 0 && (
          <ul className="flex flex-col gap-2 text-sm text-neutral-700">
            {uploaded.map((name) => (
              <li key={name} className="flex items-center gap-2 rounded-md bg-app-bg px-3 py-2">
                <span aria-hidden className="text-green-600">✓</span>
                {name}
              </li>
            ))}
          </ul>
        )}

        {error && <p className="text-sm text-red-600">{error}</p>}

        <Button onClick={() => void handleSubmitForReview()} disabled={submitting || uploaded.length === 0}>
          {submitting ? 'Gönderiliyor…' : 'Doğrulama Başvurusunu Gönder'}
        </Button>
      </div>
    </Card>
  );
}
