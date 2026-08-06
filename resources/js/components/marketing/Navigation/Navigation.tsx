import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Menu, X } from 'lucide-react';
import { Logo } from '../../Logo';
import { Button } from '../../ui/Button';
import { LOGIN_URL, NAV_LINKS, PLATFORM_MENU, REGISTER_URL } from '../../../constants/site';

export function Navigation() {
  const [scrolled, setScrolled] = useState(false);
  const [platformOpen, setPlatformOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const closeTimer = useRef<number | null>(null);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 8);
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobileOpen]);

  function openPlatform() {
    if (closeTimer.current) window.clearTimeout(closeTimer.current);
    setPlatformOpen(true);
  }

  function scheduleClosePlatform() {
    closeTimer.current = window.setTimeout(() => setPlatformOpen(false), 120);
  }

  return (
    <header
      className={`fixed inset-x-0 top-0 z-50 transition-all duration-300 ${
        scrolled
          ? 'border-b border-dark/[0.07] bg-white/85 shadow-[0_1px_0_0_rgba(13,17,23,0.04)] backdrop-blur-md'
          : 'border-b border-transparent bg-white/60 backdrop-blur-sm'
      }`}
    >
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
        <a href="#top" className="shrink-0">
          <Logo />
        </a>

        <nav className="hidden items-center gap-1 md:flex" aria-label="Ana navigasyon">
          <div
            className="relative"
            onMouseEnter={openPlatform}
            onMouseLeave={scheduleClosePlatform}
          >
            <button
              type="button"
              aria-expanded={platformOpen}
              onClick={() => setPlatformOpen((prev) => !prev)}
              className="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium text-dark/70 transition-colors hover:bg-dark/[0.04] hover:text-dark"
            >
              Platform
              <ChevronDown className={`h-3.5 w-3.5 transition-transform ${platformOpen ? 'rotate-180' : ''}`} />
            </button>

            <AnimatePresence>
              {platformOpen && (
                <motion.div
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: 8 }}
                  transition={{ duration: 0.18, ease: [0.22, 1, 0.36, 1] }}
                  role="menu"
                  className="absolute left-1/2 top-full mt-3 w-[640px] -translate-x-1/2 rounded-2xl border border-dark/[0.08] bg-white p-6 shadow-[0_24px_64px_-24px_rgba(13,17,23,0.25)]"
                >
                  <div className="grid grid-cols-3 gap-6">
                    {PLATFORM_MENU.map((column) => (
                      <div key={column.title}>
                        <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">
                          {column.title}
                        </p>
                        <ul className="mt-3 flex flex-col gap-3">
                          {column.items.map((item) => (
                            <li key={item.label}>
                              <a
                                href={item.href}
                                role="menuitem"
                                onClick={() => setPlatformOpen(false)}
                                className="group block rounded-lg -mx-2 px-2 py-1 transition-colors hover:bg-surface-orange"
                              >
                                <p className="text-sm font-semibold text-dark group-hover:text-primary">
                                  {item.label}
                                </p>
                                <p className="mt-0.5 text-xs leading-snug text-dark/45">{item.description}</p>
                              </a>
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>

          {NAV_LINKS.map((link) => (
            <a
              key={link.href}
              href={link.href}
              className="rounded-full px-4 py-2 text-sm font-medium text-dark/70 transition-colors hover:bg-dark/[0.04] hover:text-dark"
            >
              {link.label}
            </a>
          ))}
        </nav>

        <div className="hidden items-center gap-2 md:flex">
          <Button href={LOGIN_URL} variant="ghost" size="md">
            Giriş Yap
          </Button>
          <Button href={REGISTER_URL} variant="primary" size="md">
            Mağazanı Oluştur
          </Button>
        </div>

        <button
          type="button"
          onClick={() => setMobileOpen((prev) => !prev)}
          className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-dark md:hidden"
          aria-label={mobileOpen ? 'Menüyü kapat' : 'Menüyü aç'}
        >
          {mobileOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
        </button>
      </div>

      <AnimatePresence>
        {mobileOpen && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
            className="overflow-hidden border-t border-dark/[0.06] bg-white md:hidden"
          >
            <div className="flex flex-col gap-1 px-6 py-4">
              <p className="px-3 pt-2 text-[11px] font-bold uppercase tracking-wider text-dark/35">Platform</p>
              {PLATFORM_MENU.flatMap((column) => column.items).map((item) => (
                <a
                  key={item.label}
                  href={item.href}
                  onClick={() => setMobileOpen(false)}
                  className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium text-dark/70 transition-colors hover:bg-surface-orange hover:text-dark"
                >
                  {item.label}
                </a>
              ))}
              <div className="my-2 border-t border-dark/[0.06]" />
              {NAV_LINKS.map((link) => (
                <a
                  key={link.href}
                  href={link.href}
                  onClick={() => setMobileOpen(false)}
                  className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium text-dark/70 transition-colors hover:bg-surface-orange hover:text-dark"
                >
                  {link.label}
                </a>
              ))}
              <div className="mt-3 flex flex-col gap-2">
                <Button href={LOGIN_URL} variant="secondary" fullWidthOnMobile>
                  Giriş Yap
                </Button>
                <Button href={REGISTER_URL} variant="primary" fullWidthOnMobile>
                  Mağazanı Oluştur
                </Button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  );
}
