import { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Menu, X } from 'lucide-react';
import { Logo } from '../../Logo';
import { Button } from '../../ui/Button';
import { LOGIN_URL, NAV_LINKS, REGISTER_URL, CTA } from '../../../data/navigation';
import { PlatformMenu } from '../MegaMenu/PlatformMenu';
import { SolutionsMenu } from '../MegaMenu/SolutionsMenu';
import { IntegrationsMenu } from '../MegaMenu/IntegrationsMenu';

type MenuKey = 'platform' | 'solutions' | 'integrations' | null;

const MEGA_MENUS: { key: Exclude<MenuKey, null>; label: string; width: string }[] = [
  { key: 'platform', label: 'Platform', width: 'w-[720px]' },
  { key: 'solutions', label: 'Çözümler', width: 'w-[560px]' },
  { key: 'integrations', label: 'Entegrasyonlar', width: 'w-[640px]' },
];

export function Navigation() {
  const { url } = usePage();
  const [scrolled, setScrolled] = useState(false);
  const [openMenu, setOpenMenu] = useState<MenuKey>(null);
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

  useEffect(() => {
    setMobileOpen(false);
    setOpenMenu(null);
  }, [url]);

  function openMenuNow(key: Exclude<MenuKey, null>) {
    if (closeTimer.current) window.clearTimeout(closeTimer.current);
    setOpenMenu(key);
  }

  function scheduleClose() {
    closeTimer.current = window.setTimeout(() => setOpenMenu(null), 120);
  }

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
          {MEGA_MENUS.map((menu) => (
            <div key={menu.key} className="relative" onMouseEnter={() => openMenuNow(menu.key)} onMouseLeave={scheduleClose}>
              <button
                type="button"
                aria-expanded={openMenu === menu.key}
                onClick={() => setOpenMenu((current) => (current === menu.key ? null : menu.key))}
                className="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium text-dark/70 transition-colors hover:bg-dark/[0.04] hover:text-dark"
              >
                {menu.label}
                <ChevronDown className={`h-3.5 w-3.5 transition-transform ${openMenu === menu.key ? 'rotate-180' : ''}`} />
              </button>

              <AnimatePresence>
                {openMenu === menu.key && (
                  <motion.div
                    initial={{ opacity: 0, scale: 0.98, y: 8 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.98, y: 8 }}
                    transition={{ duration: 0.18, ease: [0.22, 1, 0.36, 1] }}
                    role="menu"
                    className={`absolute left-1/2 top-full mt-3 -translate-x-1/2 rounded-showcase border border-dark/[0.08] bg-white p-6 shadow-spectrum ${menu.width}`}
                  >
                    {menu.key === 'platform' && <PlatformMenu onNavigate={() => setOpenMenu(null)} />}
                    {menu.key === 'solutions' && <SolutionsMenu onNavigate={() => setOpenMenu(null)} />}
                    {menu.key === 'integrations' && <IntegrationsMenu onNavigate={() => setOpenMenu(null)} />}
                  </motion.div>
                )}
              </AnimatePresence>
            </div>
          ))}

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
              <p className="px-3 pt-2 text-[11px] font-bold uppercase tracking-wider text-dark/35">Platform</p>
              <Link href="/platform" className="min-h-11 rounded-control px-3 py-2.5 text-sm font-medium text-dark/70 hover:bg-surface-orange hover:text-dark">
                Genel Bakış
              </Link>
              {[
                { href: '/themes', label: 'Temalar' },
                { href: '/store-builder', label: 'Store Builder' },
                { href: '/checkout', label: 'Checkout' },
                { href: '/analytics', label: 'Analitik' },
                { href: '/social-commerce', label: 'Sosyal Ticaret' },
                { href: '/integrations', label: 'Entegrasyonlar' },
              ].map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="min-h-11 rounded-control px-3 py-2.5 text-sm font-medium text-dark/70 hover:bg-surface-orange hover:text-dark"
                >
                  {item.label}
                </Link>
              ))}
              <div className="my-2 border-t border-dark/[0.06]" />
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
