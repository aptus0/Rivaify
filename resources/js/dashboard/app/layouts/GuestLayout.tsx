import type { ReactNode } from 'react';
import { Logo } from '../../../components/Logo';
import { ParticleBackground } from '../../../components/effects/ParticleBackground';

/** Login/Register/VerifyEmail shell (brief §17, updated per later feedback
 * to a single centered card over an interactive dot-field background
 * instead of the earlier dark split-panel layout). */
export function GuestLayout({ children }: { children: ReactNode }) {
  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-white px-4 py-12">
      <ParticleBackground />

      <div className="relative w-full max-w-sm">
        <div className="mb-8 flex justify-center">
          <Logo />
        </div>
        <div className="rounded-2xl border border-border bg-white/90 p-8 shadow-xl shadow-dark/5 backdrop-blur-sm">
          {children}
        </div>
      </div>
    </div>
  );
}
