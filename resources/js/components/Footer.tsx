import { Logo } from './Logo';

const FOOTER_COLUMNS = [
  {
    title: 'Ürün',
    links: [
      { label: 'Nasıl Çalışır', href: '#nasil-calisir' },
      { label: 'Özellikler', href: '#ozellikler' },
      { label: 'Entegrasyonlar', href: '#entegrasyonlar' },
      { label: 'Temalar', href: '#temalar' },
    ],
  },
  {
    title: 'Şirket',
    links: [
      { label: 'Hakkımızda', href: '#hakkimizda' },
      { label: 'Erken Erişim', href: '#erken-erisim' },
    ],
  },
  {
    title: 'Yasal',
    links: [
      { label: 'Gizlilik Politikası', href: '#' },
      { label: 'Kullanım Koşulları', href: '#' },
    ],
  },
] as const;

export function Footer() {
  return (
    <footer className="border-t border-dark/[0.06] px-6 pt-16 pb-10 lg:px-8">
      <div className="mx-auto max-w-6xl">
        <div className="grid grid-cols-2 gap-10 sm:grid-cols-4">
          <div className="col-span-2 sm:col-span-1">
            <Logo />
            <p className="mt-3 max-w-[220px] text-sm leading-relaxed text-dark/40">
              Yeni nesil sosyal ticaret ve e-ticaret platformu.
            </p>
          </div>

          {FOOTER_COLUMNS.map((column) => (
            <div key={column.title}>
              <p className="text-xs font-semibold uppercase tracking-wide text-dark/35">{column.title}</p>
              <ul className="mt-4 flex flex-col gap-3">
                {column.links.map((link) => (
                  <li key={link.label}>
                    <a
                      href={link.href}
                      className="text-sm font-medium text-dark/60 transition-colors hover:text-dark"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-14 border-t border-dark/[0.06] pt-8 text-center sm:text-left">
          <p className="text-xs text-dark/30">© 2026 Rivaify. Tüm hakları saklıdır.</p>
        </div>
      </div>
    </footer>
  );
}
