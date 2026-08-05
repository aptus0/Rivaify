import { useState, type FormEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { describeApiError } from '../../../utils/errors';
import { login } from '../api/authApi';
import { useAuth } from '../../../app/providers/AuthProvider';

export function LoginPage() {
  const navigate = useNavigate();
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setFormError(null);

    const form = new FormData(event.currentTarget);
    try {
      await login({
        email: String(form.get('email')),
        password: String(form.get('password')),
      });
      await refresh();
      navigate('/');
    } catch (error) {
      setFormError(describeApiError(error).message ?? 'E-posta veya şifre hatalı.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="mx-auto mt-16 w-full max-w-sm">
      <h1 className="mb-6 text-2xl font-semibold">Giriş Yap</h1>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input label="E-posta" name="email" type="email" autoComplete="email" required />
        <Input label="Şifre" name="password" type="password" autoComplete="current-password" required />
        {formError && <p className="text-sm text-red-600">{formError}</p>}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Giriş yapılıyor…' : 'Giriş Yap'}
        </Button>
      </form>
      <p className="mt-4 text-sm text-neutral-600">
        Hesabın yok mu?{' '}
        <Link to="/register" className="font-medium underline">
          Kayıt ol
        </Link>
      </p>
    </div>
  );
}
