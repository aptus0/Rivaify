import { useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { useAuth } from '../../../app/providers/AuthProvider';
import { resendVerificationEmail } from '../api/authApi';

export function VerifyEmailPage() {
  const { user, refresh } = useAuth();
  const [sent, setSent] = useState(false);
  const [sending, setSending] = useState(false);

  async function handleResend() {
    setSending(true);
    try {
      await resendVerificationEmail();
      setSent(true);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="text-center">
      <h1 className="mb-2 text-2xl font-semibold text-dark">E-postanı Doğrula</h1>
      <p className="mb-6 text-sm text-muted">
        {user?.email} adresine bir doğrulama bağlantısı gönderdik. Bağlantıya tıkladıktan sonra aşağıdaki
        butona bas.
      </p>
      <div className="flex flex-col gap-3">
        <Button onClick={() => void refresh()}>Doğruladım, devam et</Button>
        <button
          type="button"
          onClick={() => void handleResend()}
          disabled={sending}
          className="text-sm text-primary hover:text-primary-hover disabled:opacity-50"
        >
          {sent ? 'Tekrar gönderildi' : 'E-postayı tekrar gönder'}
        </button>
      </div>
    </div>
  );
}
