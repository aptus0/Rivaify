import { useState, type FormEvent } from 'react';
import { motion } from 'framer-motion';
import { CheckCircle2, Loader2, Mail } from 'lucide-react';

type SubmissionState = 'idle' | 'submitting' | 'success' | 'error';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

async function submitEarlyAccessEmail(email: string): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 900));
  void email;
}

export function EarlyAccess() {
  const [email, setEmail] = useState('');
  const [status, setStatus] = useState<SubmissionState>('idle');
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!EMAIL_PATTERN.test(email)) {
      setError('Lütfen geçerli bir e-posta adresi gir.');
      setStatus('error');
      return;
    }

    setError(null);
    setStatus('submitting');

    try {
      await submitEarlyAccessEmail(email);
      setStatus('success');
    } catch {
      setError('Bir şeyler ters gitti, lütfen tekrar dene.');
      setStatus('error');
    }
  };

  return (
    <section id="erken-erisim" className="relative overflow-hidden px-6 py-24 lg:px-8 lg:py-32">
      <div
        className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70%_60%_at_50%_40%,rgba(255,107,0,0.10),rgba(255,107,0,0)_70%)]"
        aria-hidden="true"
      />

      <motion.div
        initial={{ opacity: 0, y: 24 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
        className="mx-auto max-w-xl rounded-3xl border border-dark/[0.07] bg-white p-8 text-center shadow-[0_24px_60px_-24px_rgba(17,17,17,0.15)] sm:p-12"
      >
        <h2 className="text-2xl font-extrabold tracking-tight text-dark sm:text-3xl">
          Rivaify açıldığında <span className="text-primary">ilk sen haberdar ol.</span>
        </h2>
        <p className="mx-auto mt-4 max-w-sm text-sm leading-relaxed text-dark/50">
          E-posta adresini bırak, lansman ve erken erişim fırsatlarını sana gönderelim.
        </p>

        {status === 'success' ? (
          <motion.div
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className="mt-8 flex items-center justify-center gap-2 rounded-xl bg-surface-orange px-5 py-4 text-sm font-semibold text-primary"
          >
            <CheckCircle2 className="h-5 w-5 shrink-0" strokeWidth={2} />
            Harika! Seni erken erişim listesine ekledik.
          </motion.div>
        ) : (
          <form onSubmit={handleSubmit} noValidate className="mt-8">
            <div className="flex flex-col gap-3 sm:flex-row">
              <div className="relative flex-1">
                <Mail className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-dark/30" />
                <input
                  type="email"
                  value={email}
                  onChange={(event) => {
                    setEmail(event.target.value);
                    if (status === 'error') {
                      setStatus('idle');
                      setError(null);
                    }
                  }}
                  placeholder="ornek@eposta.com"
                  aria-label="E-posta adresi"
                  aria-invalid={status === 'error'}
                  disabled={status === 'submitting'}
                  className="w-full rounded-full border border-dark/10 bg-surface py-3.5 pl-11 pr-4 text-sm text-dark outline-none transition-colors focus:border-primary/40 focus:bg-white disabled:opacity-60"
                />
              </div>
              <button
                type="submit"
                disabled={status === 'submitting'}
                className="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3.5 text-sm font-semibold text-white shadow-[0_8px_24px_-8px_rgba(255,107,0,0.6)] transition-transform hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-70"
              >
                {status === 'submitting' && <Loader2 className="h-4 w-4 animate-spin" />}
                Listeye Katıl
              </button>
            </div>
            {error && <p className="mt-3 text-left text-xs font-medium text-primary sm:text-center">{error}</p>}
          </form>
        )}
      </motion.div>
    </section>
  );
}
