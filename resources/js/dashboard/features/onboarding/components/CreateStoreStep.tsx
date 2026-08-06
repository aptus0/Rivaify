import { useState, type FormEvent } from 'react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { Input } from '../../../components/ui/Input';
import { describeApiError } from '../../../utils/errors';
import { createStore } from '../../store/api/storeApi';
import { useAuth } from '../../../app/providers/AuthProvider';

export function CreateStoreStep() {
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      await createStore(String(new FormData(event.currentTarget).get('name')));
      await refresh();
    } catch (err) {
      setError(describeApiError(err).message ?? 'Mağaza oluşturulamadı.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        <div>
          <h2 className="text-lg font-semibold text-dark">Mağazanı oluştur</h2>
          <p className="mt-1 text-sm text-muted">Rivaify'da satışa başlamak için önce mağazana bir isim ver.</p>
        </div>
        <Input label="Mağaza adı" name="name" required placeholder="Rivaify Butik" />
        {error && <p className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Oluşturuluyor…' : 'Devam Et'}
        </Button>
      </form>
    </Card>
  );
}
