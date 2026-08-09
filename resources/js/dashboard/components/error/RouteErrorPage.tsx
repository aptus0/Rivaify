import { ArrowLeft, Home, RotateCcw } from 'lucide-react';
import { Link, isRouteErrorResponse, useRouteError } from 'react-router-dom';
import { Logo } from '../../../components/Logo';

const COPY: Record<number, { title: string; message: string }> = {
  400: { title: 'İstek tamamlanamadı', message: 'Sayfayı yenileyip tekrar deneyebilirsin.' },
  401: { title: 'Oturum açman gerekiyor', message: 'Devam etmek için hesabına giriş yapmalısın.' },
  403: { title: 'Bu alana erişimin yok', message: 'Bu sayfayı görüntülemek için gerekli yetkin olmayabilir.' },
  404: { title: 'Aradığın sayfayı bulamadık', message: 'Bağlantı değişmiş ya da sayfa taşınmış olabilir.' },
  419: { title: 'Oturum süren doldu', message: 'Güvenliğin için sayfayı yenileyip tekrar devam edebilirsin.' },
  429: { title: 'Çok hızlı gidiyoruz', message: 'Kısa bir ara verip tekrar deneyebilirsin.' },
  500: { title: 'Bir şeyler yolunda gitmedi', message: 'İşlemi şu anda tamamlayamadık. Destek koduyla hızlıca yardımcı olabiliriz.' },
  502: { title: 'Geçici bir bağlantı sorunu var', message: 'Rivaify şu anda gerekli yanıta ulaşamadı. Birazdan tekrar deneyebilirsin.' },
  503: { title: 'Kısa bir bakım molası', message: 'Rivaify kısa süreli bakımda ya da yoğunluk altında.' },
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

function ErrorScene() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <div className="absolute left-[10%] top-[18%] h-24 w-24 rotate-12 rounded-2xl bg-primary/10 shadow-2xl shadow-primary/20 [transform:perspective(700px)_rotateX(58deg)_rotateZ(22deg)]" />
      <div className="absolute right-[12%] top-[14%] h-32 w-32 rounded-full border border-primary/20 bg-white/45 shadow-2xl shadow-primary/10 [transform:perspective(760px)_rotateY(-36deg)]" />
      <div className="absolute bottom-[12%] left-[16%] h-36 w-36 rounded-[2rem] bg-zinc-950/[.04] [transform:perspective(800px)_rotateX(64deg)_rotateZ(-18deg)]" />
      <div className="absolute bottom-[18%] right-[18%] h-20 w-20 rounded-2xl bg-primary/15 shadow-2xl shadow-primary/20 [transform:perspective(640px)_rotateX(50deg)_rotateY(24deg)]" />
    </div>
  );
}

export function RouteErrorPage({ status: forcedStatus }: { status?: number }) {
  const routeError = useRouteError();
  const status = forcedStatus ?? statusFromError(routeError, 500);
  const copy = COPY[status] ?? COPY[500];
  const uid = requestUid();

  return (
    <main className="relative grid min-h-screen place-items-center overflow-hidden bg-[#fffaf5] px-5 py-10 text-zinc-950">
      <ErrorScene />
      <section className="relative w-full max-w-2xl text-center">
        <Logo className="mx-auto h-8" />
        <div className="mt-10 text-[clamp(6rem,22vw,13rem)] font-black leading-none tracking-normal text-primary drop-shadow-[0_22px_40px_rgba(255,107,0,0.22)]">
          {status}
        </div>
        <h1 className="mt-4 text-3xl font-semibold tracking-normal sm:text-5xl">{copy.title}</h1>
        <p className="mx-auto mt-4 max-w-lg text-sm leading-7 text-zinc-600 sm:text-base">{copy.message}</p>
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
          <Link to="/dashboard" className="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary-hover">
            <Home size={16} /> Ana sayfa
          </Link>
        </div>
      </section>
    </main>
  );
}
