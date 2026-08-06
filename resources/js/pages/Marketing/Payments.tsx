import { useState } from 'react';
import { Lock } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { RivaCard } from '../../components/effects/RivaCard';
import { BrandLogo } from '../../components/brands/BrandLogo';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';
import { integrationsByCategory } from '../../data/integrations';

interface PaymentsProps {
  seo: { title: string; description: string };
}

const PROVIDERS = integrationsByCategory('payment');

export default function Payments({ seo }: PaymentsProps) {
  const [activeProvider, setActiveProvider] = useState(PROVIDERS[0]?.key);

  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Payments"
        title={
          <>
            Ödeme deneyimini
            <br />
            <span className="text-primary">ticaretin önünden kaldır.</span>
          </>
        }
      />

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container size="wide">
          <SectionHeading title="Ödeme sağlayıcıları" align="left" />

          <div className="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              {PROVIDERS.map((provider) => (
                <button key={provider.key} type="button" onClick={() => setActiveProvider(provider.key)} className="text-left">
                  <RivaCard
                    variant={activeProvider === provider.key ? 'spectrum' : 'default'}
                    intensity="subtle"
                    interactive={activeProvider === provider.key}
                    ambient={activeProvider === provider.key}
                    className="flex flex-col items-center gap-2 p-6 text-center"
                  >
                    <BrandLogo brand={provider.key} showName={false} />
                    <p className="text-sm font-bold text-dark">{provider.name}</p>
                    <p className="text-xs text-dark/40">{provider.description}</p>
                  </RivaCard>
                </button>
              ))}
            </div>

            <RivaCard variant="dark" radius="showcase" className="p-6">
              <p className="text-xs font-bold uppercase tracking-wider text-white/40">Checkout Önizleme</p>
              <div className="mt-4 rounded-card bg-white/[0.06] p-4">
                <div className="flex items-center justify-between text-sm text-white/70">
                  <span>Toplam</span>
                  <span className="font-bold text-white">₺4.499</span>
                </div>
                <div className="mt-4 flex items-center justify-center gap-2 rounded-full bg-primary py-2.5 text-sm font-semibold text-white">
                  <Lock className="h-4 w-4" strokeWidth={2} />
                  {PROVIDERS.find((p) => p.key === activeProvider)?.name} ile öde
                </div>
              </div>
              <p className="mt-3 text-[11px] text-white/30">Bu bir önizlemedir — gerçek ödeme bilgisi istenmez.</p>
            </RivaCard>
          </div>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
