import { useState, type FormEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { describeApiError } from '../../../utils/errors';
import { register } from '../api/authApi';
import { useAuth } from '../../../app/providers/AuthProvider';

export function RegisterPage() {
  const navigate = useNavigate();
  const { refresh } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    const form = new FormData(event.currentTarget);
    try {
      await register({
        name: String(form.get('name')),
        email: String(form.get('email')),
        password: String(form.get('password')),
        password_confirmation: String(form.get('password_confirmation')),
      });
      await refresh();
      navigate('/onboarding');
    } catch (error) {
      const described = describeApiError(error);
      setFieldErrors(described.fieldErrors);
      setFormError(described.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="mx-auto mt-16 w-full max-w-sm">
      <h1 className="mb-6 text-2xl font-semibold">Kayıt Ol</h1>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input label="Ad Soyad" name="name" autoComplete="name" required error={fieldErrors.name} />
        <Input label="E-posta" name="email" type="email" autoComplete="email" required error={fieldErrors.email} />
        <Input
          label="Şifre"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          error={fieldErrors.password}
        />
        <Input
          label="Şifre (tekrar)"
          name="password_confirmation"
          type="password"
          autoComplete="new-password"
          required
        />
        {formError && <p className="text-sm text-red-600">{formError}</p>}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Gönderiliyor…' : 'Kayıt Ol'}
        </Button>
      </form>
      <p className="mt-4 text-sm text-neutral-600">
        Zaten hesabın var mı?{' '}
        <Link to="/login" className="font-medium underline">
          Giriş yap
        </Link>
      </p>
    </div>
  );
}
