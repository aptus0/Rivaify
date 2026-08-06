import { Globe, Search, ShoppingBag, Smartphone, type LucideIcon } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { MobileStore } from '../../components/marketing/MobileStore/MobileStore';
import { CustomDomain } from '../../components/marketing/CustomDomain/CustomDomain';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { FocusCorners } from '../../components/effects/FocusCorners';
import { RivaCard } from '../../components/effects/RivaCard';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

interface OnlineStoreProps {
  seo: { title: string; description: string };
}

const FEATURES: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: Globe, title: 'Özel Domain', description: 'Kendi alan adında yayında ol.' },
  { icon: Search, title: 'SEO', description: 'Arama motorları için optimize sayfa yapısı.' },
  { icon: Smartphone, title: 'Mobil Optimizasyon', description: 'Her cihazda akıcı alışveriş deneyimi.' },
  { icon: ShoppingBag, title: 'Sepet & Checkout', description: 'Dönüşüm odaklı satın alma akışı.' },
];

export default function OnlineStore({ seo }: OnlineStoreProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Online Mağaza"
        title={
          <>
            Markana ait mağaza.
            <br />
            <span className="text-primary">Rivaify altyapısıyla.</span>
          </>
        }
      >
        <div className="relative mx-auto mt-16 max-w-3xl px-6 lg:px-8">
          <FocusCorners>
            <RivaCard variant="spectrum" intensity="medium" radius="window" className="overflow-hidden p-6">
              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-3 h-32 rounded-card bg-surface-orange sm:h-44" />
                <div className="h-16 rounded-card bg-surface sm:h-24" />
                <div className="h-16 rounded-card bg-surface sm:h-24" />
                <div className="h-16 rounded-card bg-surface sm:h-24" />
              </div>
            </RivaCard>
          </FocusCorners>
        </div>
      </PageHero>

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container>
          <SectionHeading title="Mağazan için ihtiyacın olan her şey." />
          <div className="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {FEATURES.map((feature, index) => (
              <Reveal key={feature.title} delay={index * 0.06}>
                <div className="flex flex-col items-center text-center">
                  <span className="flex h-11 w-11 items-center justify-center rounded-control bg-surface-orange text-primary">
                    <feature.icon className="h-5 w-5" strokeWidth={2} />
                  </span>
                  <p className="mt-4 text-sm font-bold text-dark">{feature.title}</p>
                  <p className="mt-1.5 text-xs leading-relaxed text-dark/45">{feature.description}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <CustomDomain />
      <MobileStore />
      <FinalCTA />
    </MarketingLayout>
  );
}
