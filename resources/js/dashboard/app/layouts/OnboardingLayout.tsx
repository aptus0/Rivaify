import type { ReactNode } from 'react';
import { Logo } from '../../../components/Logo';
import { logout } from '../../features/auth/api/authApi';
import { useAuth } from '../providers/AuthProvider';

/** Onboarding shell (brief §19) — no app sidebar/header here, just the
 * brand mark and an escape hatch. The step-by-step progress itself stays
 * inside OnboardingPage (server-driven via onboarding_step), so this layout
 * only owns the chrome around it. */
export function OnboardingLayout({ children }: { children: ReactNode }) {
  const { refresh } = useAuth();

  async function handleLogout() {
    await logout();
    await refresh();
  }

  return (
    <div className="min-h-screen bg-app-bg">
      <header className="flex items-center justify-between border-b border-border bg-card px-6 py-4">
        <Logo />
        <div className="flex items-center gap-4 text-sm text-muted">
          <a href="mailto:destek@rivaify.com" className="hover:text-dark">
            Yardıma mı ihtiyacın var?
          </a>
          <button onClick={() => void handleLogout()} className="hover:text-dark">
            Çıkış Yap
          </button>
        </div>
      </header>
      <main className="px-4 py-12">{children}</main>
    </div>
  );
}
