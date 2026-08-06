import type { ReactNode } from 'react';
import { Logo } from '../../../components/Logo';

/** Login/Register/VerifyEmail shell (brief §17): brand panel + form on
 * desktop, form only on mobile — the brand panel is simply hidden below
 * `lg`, not rearranged, so it never competes with the form for space. */
export function GuestLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen bg-app-bg">
      <div className="hidden w-1/2 flex-col justify-between bg-dark p-12 text-white lg:flex">
        <Logo />
        <div>
          <p className="text-3xl font-semibold">
            Commerce
            <br />
            reimagined.
          </p>
          <p className="mt-4 max-w-sm text-sm text-white/60">
            Mağazanı kur, sosyal kanallarını bağla, satışını tek yerden yönet.
          </p>
        </div>
        <p className="text-xs text-white/40">&copy; {new Date().getFullYear()} Rivaify</p>
      </div>

      <div className="flex flex-1 flex-col items-center justify-center px-4 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-8 lg:hidden">
            <Logo />
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}
