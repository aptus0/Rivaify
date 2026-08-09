import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Menu, X } from 'lucide-react';
import { Logo } from '../../Logo';
import { Button } from '../../ui/Button';
import { LOGIN_URL, NAV_LINKS, REGISTER_URL, CTA } from '../../../data/navigation';

/** Flat nav — trimmed 2026-08-09 alongside the site's reduction from 15
 * pages to 4 (Home/Platform/Store Builder/Pricing). No mega menus: with
 * only three real destinations besides Home there's nothing left to
 * sub-navigate, so the old Platform/Çözümler/Entegrasyonlar dropdowns
 * (MegaMenu/*) were removed rather than kept pointing at deleted routes. */
export function Navigation() {
  const { url } = usePage();
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

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

  useEffect(() => {
    setMobileOpen(false);
  }, [url]);

  function isActive(href: string) {
    return href === '/' ? url === '/' : url.startsWith(href);
  }

  return (
    <header
      className={`fixed inset-x-0 top-0 z-50 transition-all duration-300 ${
        scrolled
          ? 'border-b border-dark/[0.07] bg-white/90 shadow-[0_1px_0_0_rgba(9,11,15,0.04)] backdrop-blur-xl'
          : 'border-b border-transparent bg-white/50 backdrop-blur-sm'
      }`}
    >
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
        <Link href="/" className="shrink-0">
          <Logo />
        </Link>

        <nav className="hidden items-center gap-1 md:flex" aria-label="Ana navigasyon">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="relative rounded-full px-4 py-2 text-sm font-medium text-dark/70 transition-colors hover:bg-dark/[0.04] hover:text-dark"
            >
              {link.label}
              {isActive(link.href) && (
                <span className="absolute inset-x-4 -bottom-[1px] h-[2px] rounded-full bg-primary" aria-hidden="true" />
              )}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-2 md:flex">
          <Button href={LOGIN_URL} variant="ghost" size="md">
            Giriş Yap
          </Button>
          <Button href={REGISTER_URL} variant="primary" size="md">
            {CTA.primary}
          </Button>
        </div>

        <button
          type="button"
          onClick={() => setMobileOpen((prev) => !prev)}
          className="inline-flex h-11 w-11 items-center justify-center rounded-control text-dark md:hidden"
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
            <div className="flex max-h-[70vh] flex-col gap-1 overflow-y-auto px-6 py-4">
              {NAV_LINKS.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className="min-h-11 rounded-control px-3 py-2.5 text-sm font-medium text-dark/70 hover:bg-surface-orange hover:text-dark"
                >
                  {link.label}
                </Link>
              ))}
              <div className="mt-3 flex flex-col gap-2">
                <Button href={LOGIN_URL} variant="secondary" fullWidthOnMobile>
                  Giriş Yap
                </Button>
                <Button href={REGISTER_URL} variant="primary" fullWidthOnMobile>
                  {CTA.primary}
                </Button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  );
}
