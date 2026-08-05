import { useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
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
    <div className="flex flex-col gap-6">
      <div>
        <h2 className="mb-4 text-lg font-medium">Belgeler</h2>
        <form onSubmit={handleUpload} className="flex flex-col gap-3">
          <select name="type" className="rounded-md border border-neutral-300 px-3 py-2 text-sm" required>
            {DOCUMENT_TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
          <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required className="text-sm" />
          <Button type="submit" disabled={uploading}>
            {uploading ? 'Yükleniyor…' : 'Belge Yükle'}
          </Button>
        </form>
        {uploaded.length > 0 && (
          <ul className="mt-3 list-inside list-disc text-sm text-neutral-600">
            {uploaded.map((name) => (
              <li key={name}>{name}</li>
            ))}
          </ul>
        )}
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}

      <Button onClick={() => void handleSubmitForReview()} disabled={submitting || uploaded.length === 0}>
        {submitting ? 'Gönderiliyor…' : 'Doğrulama Başvurusunu Gönder'}
      </Button>
    </div>
  );
}
