import { Link } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { Logo } from '../../Logo';
import { Badge } from '../../ui/Badge';
import { Container } from '../../ui/Container';
import { FOOTER_COLUMNS } from '../../../data/navigation';

interface FooterLink {
  label: string;
  href?: string;
}

const EXTRA_COLUMNS: { title: string; links: FooterLink[] }[] = [
  { title: 'Şirket', links: [{ label: 'Hakkımızda' }, { label: 'İletişim' }] },
];

const LEGAL_LINKS = ['Gizlilik Politikası', 'Kullanım Koşulları', 'KVKK'];

function FooterLinkItem({ link }: { link: FooterLink }) {
  if (link.href) {
    return (
      <Link href={link.href} className="text-sm font-medium text-dark/60 transition-colors hover:text-dark">
        {link.label}
      </Link>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 text-sm font-medium text-dark/35">
      {link.label}
      <Badge variant="soon" className="px-2 py-0.5 text-[10px]">Yakında</Badge>
    </span>
  );
}

export function Footer() {
  const columns = [...FOOTER_COLUMNS, ...EXTRA_COLUMNS];

  return (
    <footer className="border-t border-dark/[0.06] bg-white px-6 pt-16 pb-10 lg:px-8">
      <Container>
        <div className="grid grid-cols-2 gap-10 sm:grid-cols-4 lg:grid-cols-5">
          <div className="col-span-2 sm:col-span-4 lg:col-span-1">
            <Logo />
            <p className="mt-3 max-w-[220px] text-sm leading-relaxed text-dark/40">
              Yeni nesil e-ticaret platformu.
            </p>
          </div>

          {columns.map((column) => (
            <div key={column.title}>
              <p className="text-xs font-semibold uppercase tracking-wide text-dark/35">{column.title}</p>
              <ul className="mt-4 flex flex-col gap-3">
                {column.links.map((link) => (
                  <li key={link.label}>
                    <FooterLinkItem link={link} />
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-14 flex flex-col items-center justify-between gap-4 border-t border-dark/[0.06] pt-8 sm:flex-row">
          <div className="flex items-center gap-2">
            <Logo variant="icon" />
            <p className="text-xs text-dark/30">© 2026 Rivaify. Tüm hakları saklıdır.</p>
          </div>

          <div className="flex items-center gap-5">
            {LEGAL_LINKS.map((label) => (
              <span key={label} className="inline-flex items-center gap-1.5 text-xs text-dark/35">
                {label}
                <Badge variant="soon" className="px-2 py-0.5 text-[10px]">Yakında</Badge>
              </span>
            ))}
            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-dark/40">
              <Globe className="h-3.5 w-3.5" strokeWidth={2} />
              Türkçe
            </span>
          </div>
        </div>
      </Container>
    </footer>
  );
}
