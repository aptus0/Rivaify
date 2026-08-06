import { Globe } from 'lucide-react';
import { Logo } from '../../Logo';
import { Container } from '../../ui/Container';

const LEGAL_LINKS = ['Gizlilik Politikası', 'Kullanım Koşulları', 'KVKK'];

export function Footer() {
  return (
    <footer className="border-t border-white/[0.05] bg-transparent px-6 py-10 lg:px-8 mt-24">
      <Container>
        <div className="flex flex-col items-center justify-between gap-6 sm:flex-row">
          <div className="flex items-center gap-3">
            <Logo variant="icon" />
            <p className="text-xs text-white/40">© 2026 Rivaify. Tüm hakları saklıdır.</p>
          </div>

          <div className="flex flex-wrap justify-center items-center gap-6">
            {LEGAL_LINKS.map((label) => (
              <span key={label} className="cursor-pointer inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/80 transition-colors">
                {label}
              </span>
            ))}
            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
              <Globe className="h-3.5 w-3.5" strokeWidth={2} />
              Türkçe
            </span>
          </div>
        </div>
      </Container>
    </footer>
  );
}
