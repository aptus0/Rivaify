import { ArrowRight } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Reveal } from '../../effects/Reveal';
import { Button } from '../../ui/Button';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';
import { REGISTER_URL } from '../../../constants/site';

interface StoreTheme {
  name: string;
  category: string;
  accent: string;
  bg: string;
}

const STORE_THEMES: StoreTheme[] = [
  { name: 'Nova', category: 'Fashion', accent: '#111111', bg: '#F4F1EC' },
  { name: 'Studio', category: 'Minimal', accent: '#3A3A3A', bg: '#F6F6F5' },
  { name: 'Aura', category: 'Beauty', accent: '#FF6B00', bg: '#FFF3E8' },
  { name: 'Mono', category: 'Electronics', accent: '#0D1117', bg: '#EFEFEF' },
  { name: 'Pulse', category: 'Market', accent: '#7C5CFC', bg: '#F3F0FF' },
  { name: 'Market', category: 'Market', accent: '#2DD4BF', bg: '#EAFBF8' },
];

function ThemeCard({ theme }: { theme: StoreTheme }) {
  return (
    <AuraCard intensity="medium" className="rounded-2xl">
      <div className="group overflow-hidden rounded-2xl border border-dark/[0.07] bg-white transition-transform duration-300 hover:-translate-y-1 hover:scale-[1.01]">
        <div className="flex h-40 items-end gap-3 p-4" style={{ backgroundColor: theme.bg }}>
          <div className="flex-1">
            <div className="flex items-center justify-between">
              <span className="h-2 w-14 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.8 }} />
              <div className="flex gap-1.5">
                <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.4 }} />
                <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.4 }} />
              </div>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2">
              <div className="col-span-2 rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.12, height: 60 }} />
              <div className="flex flex-col gap-2">
                <div className="rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.18, height: 27 }} />
                <div className="rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.12, height: 27 }} />
              </div>
            </div>
          </div>
          <div
            className="hidden h-32 w-14 shrink-0 rounded-xl border-2 border-white/60 shadow-sm sm:block"
            style={{ backgroundColor: theme.bg, filter: 'brightness(0.97)' }}
          />
        </div>
        <div className="flex items-center justify-between border-t border-dark/[0.06] px-4 py-3.5">
          <div>
            <p className="text-sm font-bold text-dark">{theme.name}</p>
            <p className="text-xs text-dark/40">{theme.category}</p>
          </div>
          <span className="h-3 w-3 rounded-full" style={{ backgroundColor: theme.accent }} aria-hidden="true" />
        </div>
      </div>
    </AuraCard>
  );
}

export function ThemeShowcase() {
  return (
    <section id="temalar" className="bg-surface px-6 py-24 lg:px-8 lg:py-32">
      <Container>
        <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <SectionHeading
            align="left"
            title={
              <>
                Her marka farklı.
                <br />
                <span className="text-primary">Mağazası da öyle olmalı.</span>
              </>
            }
            description="Profesyonel ve dönüşüm odaklı Rivaify temalarıyla mağazanı dakikalar içinde yayına hazırla."
          />
        </div>

        <div className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {STORE_THEMES.map((theme) => (
            <ThemeCard key={theme.name} theme={theme} />
          ))}
        </div>

        <Reveal delay={0.2} className="mt-10 flex justify-center">
          <Button href={REGISTER_URL} variant="secondary" icon={ArrowRight}>
            Temaları Keşfet
          </Button>
        </Reveal>
      </Container>
    </section>
  );
}
