import { useEffect, useState, type FormEvent } from 'react';
import { Check, CircleAlert, KeyRound, Save, ShieldCheck, UserRound } from 'lucide-react';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ApiError } from '../../../lib/api';
import {
  updateAccountPassword,
  updateAccountProfile,
  type AccountPasswordPayload,
  type AccountProfilePayload,
} from '../api/settingsApi';

const INPUT_CLASS = 'mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary focus:ring-2 focus:ring-primary/10';

export function AccountSettings() {
  const { user, refresh } = useAuth();
  const [profile, setProfile] = useState<AccountProfilePayload>({ name: '', email: '' });
  const [password, setPassword] = useState<AccountPasswordPayload>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [profileSaving, setProfileSaving] = useState(false);
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [profileErrors, setProfileErrors] = useState<Record<string, string[]>>({});
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
  const [profileMessage, setProfileMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
  const [passwordMessage, setPasswordMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    if (user) setProfile({ name: user.name, email: user.email });
  }, [user]);

  async function saveProfile(event: FormEvent) {
    event.preventDefault();
    setProfileSaving(true);
    setProfileErrors({});
    setProfileMessage(null);
    try {
      await updateAccountProfile(profile);
      await refresh();
      setProfileMessage({ tone: 'success', text: 'Hesap bilgileriniz güncellendi.' });
    } catch (requestError) {
      const errors = requestError instanceof ApiError ? requestError.validationErrors ?? {} : {};
      setProfileErrors(errors);
      setProfileMessage({ tone: 'error', text: firstError(errors) ?? 'Hesap bilgileri güncellenemedi.' });
    } finally {
      setProfileSaving(false);
    }
  }

  async function savePassword(event: FormEvent) {
    event.preventDefault();
    setPasswordSaving(true);
    setPasswordErrors({});
    setPasswordMessage(null);
    try {
      await updateAccountPassword(password);
      setPassword({ current_password: '', password: '', password_confirmation: '' });
      setPasswordMessage({ tone: 'success', text: 'Parolanız güvenle güncellendi.' });
    } catch (requestError) {
      const errors = requestError instanceof ApiError ? requestError.validationErrors ?? {} : {};
      setPasswordErrors(errors);
      setPasswordMessage({ tone: 'error', text: firstError(errors) ?? 'Parola güncellenemedi.' });
    } finally {
      setPasswordSaving(false);
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <form id="account" className="scroll-mt-24" onSubmit={(event) => void saveProfile(event)}>
        <Card className="h-full">
          <div className="mb-5">
            <h3 className="flex items-center gap-2 font-semibold text-dark"><UserRound size={18} className="text-primary" />Hesap profili</h3>
            <p className="mt-1 text-sm text-muted">Oturum açarken kullandığınız kişisel ad ve e-posta bilgileri.</p>
          </div>
          {profileMessage && <InlineMessage {...profileMessage} />}
          <div className="space-y-4">
            <label className="block text-sm font-medium text-dark">Ad soyad<input required autoComplete="name" value={profile.name} onChange={(event) => setProfile((current) => ({ ...current, name: event.target.value }))} className={INPUT_CLASS} />{profileErrors.name?.[0] && <FieldError>{profileErrors.name[0]}</FieldError>}</label>
            <label className="block text-sm font-medium text-dark">E-posta<input required type="email" autoComplete="email" value={profile.email} onChange={(event) => setProfile((current) => ({ ...current, email: event.target.value }))} className={INPUT_CLASS} />{profileErrors.email?.[0] && <FieldError>{profileErrors.email[0]}</FieldError>}</label>
          </div>
          <div className="mt-5 flex justify-end"><Button type="submit" fullWidth={false} disabled={profileSaving || !user}><Save size={16} />{profileSaving ? 'Kaydediliyor…' : 'Profili kaydet'}</Button></div>
        </Card>
      </form>

      <form id="security" className="scroll-mt-24" onSubmit={(event) => void savePassword(event)}>
        <Card className="h-full">
          <div className="mb-5">
            <h3 className="flex items-center gap-2 font-semibold text-dark"><ShieldCheck size={18} className="text-primary" />Hesap güvenliği</h3>
            <p className="mt-1 text-sm text-muted">Parolanızı değiştirmek için mevcut parolanızı doğrulayın.</p>
          </div>
          {passwordMessage && <InlineMessage {...passwordMessage} />}
          <div className="space-y-4">
            <label className="block text-sm font-medium text-dark">Mevcut parola<input required type="password" autoComplete="current-password" value={password.current_password} onChange={(event) => setPassword((current) => ({ ...current, current_password: event.target.value }))} className={INPUT_CLASS} />{passwordErrors.current_password?.[0] && <FieldError>{passwordErrors.current_password[0]}</FieldError>}</label>
            <label className="block text-sm font-medium text-dark">Yeni parola<input required type="password" autoComplete="new-password" value={password.password} onChange={(event) => setPassword((current) => ({ ...current, password: event.target.value }))} className={INPUT_CLASS} />{passwordErrors.password?.[0] && <FieldError>{passwordErrors.password[0]}</FieldError>}</label>
            <label className="block text-sm font-medium text-dark">Yeni parola tekrarı<input required type="password" autoComplete="new-password" value={password.password_confirmation} onChange={(event) => setPassword((current) => ({ ...current, password_confirmation: event.target.value }))} className={INPUT_CLASS} /></label>
          </div>
          <div className="mt-5 flex justify-end"><Button type="submit" fullWidth={false} disabled={passwordSaving}><KeyRound size={16} />{passwordSaving ? 'Güncelleniyor…' : 'Parolayı güncelle'}</Button></div>
        </Card>
      </form>
    </div>
  );
}

function InlineMessage({ tone, text }: { tone: 'success' | 'error'; text: string }) {
  return <div className={`mb-4 flex items-start gap-2 rounded-md border px-3 py-2 text-xs ${tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{tone === 'success' ? <Check size={15} /> : <CircleAlert size={15} />}<span>{text}</span></div>;
}

function FieldError({ children }: { children: string }) {
  return <span className="mt-1 block text-xs text-red-600">{children}</span>;
}

function firstError(errors: Record<string, string[]>): string | null {
  return Object.values(errors).find((messages) => messages.length > 0)?.[0] ?? null;
}
