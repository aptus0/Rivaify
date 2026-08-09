import { ArrowLeft, Home, RotateCcw } from 'lucide-react';
import { Link, isRouteErrorResponse, useRouteError } from 'react-router-dom';

const COPY: Record<number, { title: string; message: string }> = {
  404: { title: 'Sayfayı bulamadık', message: 'Aradığın bağlantı değişmiş ya da kaldırılmış olabilir.' },
  419: { title: 'Oturum yenilenmeli', message: 'Sayfayı yenileyip tekrar devam edebilirsin.' },
  500: { title: 'Bir şeyler yolunda gitmedi', message: 'Alışveriş deneyimini hemen toparlamaya çalışıyoruz.' },
  503: { title: 'Kısa bir bakım molası', message: 'Mağaza birazdan yeniden hazır olacak.' },
};

function requestUid(): string {
  const existing = new URLSearchParams(window.location.search).get('uid');
  if (existing) return existing;

  const uid = `RVF-${Date.now().toString(36).toUpperCase()}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;
  const url = new URL(window.location.href);
  url.searchParams.set('uid', uid);
  window.history.replaceState(window.history.state, '', url);

  return uid;
}

function statusFromError(error: unknown, fallback: number): number {
  if (isRouteErrorResponse(error)) return error.status;
  if (error && typeof error === 'object' && 'status' in error && typeof error.status === 'number') return error.status;

  return fallback;
}

export function StorefrontErrorPage({ status: forcedStatus, storeName = 'Rivaify' }: { status?: number; storeName?: string }) {
  const routeError = useRouteError();
  const status = forcedStatus ?? statusFromError(routeError, 500);
  const copy = COPY[status] ?? COPY[500];
  const uid = requestUid();

  return (
    <main className="relative grid min-h-[70vh] place-items-center overflow-hidden bg-[#fffaf5] px-5 py-16 text-zinc-950">
      <div className="pointer-events-none absolute left-[12%] top-[14%] h-28 w-28 rotate-12 rounded-2xl bg-[#ff6b00]/10 [transform:perspective(720px)_rotateX(58deg)_rotateZ(22deg)]" aria-hidden="true" />
      <div className="pointer-events-none absolute bottom-[16%] right-[14%] h-24 w-24 rounded-2xl bg-[#ff6b00]/15 shadow-2xl shadow-[#ff6b00]/20 [transform:perspective(640px)_rotateX(50deg)_rotateY(24deg)]" aria-hidden="true" />
      <section className="relative w-full max-w-xl text-center">
        <p className="text-sm font-semibold text-[#ff6b00]">{storeName}</p>
        <div className="mt-6 text-[clamp(5rem,20vw,11rem)] font-black leading-none tracking-normal text-[#ff6b00] drop-shadow-[0_22px_40px_rgba(255,107,0,0.22)]">{status}</div>
        <h1 className="mt-4 text-3xl font-semibold tracking-normal sm:text-5xl">{copy.title}</h1>
        <p className="mx-auto mt-4 max-w-md text-sm leading-7 text-zinc-600 sm:text-base">{copy.message}</p>
        <p className="mx-auto mt-7 w-fit max-w-full rounded-full border border-zinc-200 bg-white/75 px-4 py-2 text-xs font-semibold text-zinc-700 shadow-sm">
          Destek kodu: <span className="text-zinc-950">{uid}</span>
        </p>
        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <button type="button" onClick={() => window.history.back()} className="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 shadow-sm transition hover:bg-zinc-50">
            <ArrowLeft size={16} /> Geri dön
          </button>
          <button type="button" onClick={() => window.location.reload()} className="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 shadow-sm transition hover:bg-zinc-50">
            <RotateCcw size={16} /> Yenile
          </button>
          <Link to="/" className="inline-flex items-center justify-center gap-2 rounded-md bg-[#ff6b00] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff6b00]/20 transition hover:bg-[#df5200]">
            <Home size={16} /> Ana sayfa
          </Link>
        </div>
      </section>
    </main>
  );
}
