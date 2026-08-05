import { Logo } from './Logo';

const FOOTER_LINKS = [
  { label: 'Platform', href: '#platform' },
  { label: 'Gizlilik', href: '#' },
  { label: 'Kullanım Koşulları', href: '#' },
  { label: 'İletişim', href: '#' },
];

export function Footer() {
  return (
    <footer className="border-t border-dark/[0.06] px-6 py-12 lg:px-8">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-6 text-center sm:flex-row sm:items-start sm:justify-between sm:text-left">
        <div>
          <Logo />
          <p className="mt-2 text-sm text-dark/40">Yeni nesil e-ticaret deneyimi.</p>
        </div>

        <nav className="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 sm:justify-end">
          {FOOTER_LINKS.map((link) => (
            <a
              key={link.label}
              href={link.href}
              className="text-sm font-medium text-dark/50 transition-colors hover:text-dark"
            >
              {link.label}
            </a>
          ))}
        </nav>
      </div>

      <p className="mx-auto mt-10 max-w-6xl text-center text-xs text-dark/30 sm:text-left">
        © 2026 Rivaify. Tüm hakları saklıdır.
      </p>
    </footer>
  );
}
