import { Bell, CheckCircle2, PackageCheck, Truck } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { RivaCard } from '../../components/effects/RivaCard';
import { TracingBeam } from '../../components/effects/TracingBeam';
import { Reveal } from '../../components/effects/Reveal';
import { BrandGrid } from '../../components/brands/BrandGrid';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

interface ShippingProps {
  seo: { title: string; description: string };
}

const AUTOMATION_STEPS = [
  { icon: CheckCircle2, label: 'Sipariş ödendi' },
  { icon: Truck, label: 'Kargo firması seçildi' },
  { icon: PackageCheck, label: 'Gönderi oluşturuldu' },
  { icon: Bell, label: 'Müşteri bilgilendirildi' },
];

export default function Shipping({ seo }: ShippingProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Shipping"
        title={
          <>
            Siparişten kapıya
            <br />
            <span className="text-primary">tek akış.</span>
          </>
        }
      />

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container size="narrow">
          <SectionHeading title="Kargo takibi tek ekranda." align="left" />

          <Reveal delay={0.1} className="mt-10">
            <RivaCard variant="spectrum" intensity="medium" className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs font-medium text-dark/40">Gönderi</p>
                  <p className="text-lg font-bold text-dark">#RV-10254</p>
                </div>
                <span className="rounded-full border border-primary/20 bg-surface-orange px-3 py-1 text-xs font-semibold text-primary">
                  Dağıtımda
                </span>
              </div>
              <div className="mt-4 flex items-center justify-between text-xs text-dark/50">
                <span>Aras Kargo</span>
                <span className="font-mono">TRX492013875</span>
              </div>
              <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-dark/[0.06]">
                <div className="h-full w-[70%] rounded-full bg-primary" />
              </div>
            </RivaCard>
          </Reveal>
        </Container>
      </section>

      <section className="bg-surface px-6 py-24 lg:px-8 lg:py-32">
        <Container size="narrow">
          <SectionHeading title="Sipariş ödendiğinde, gerisini Rivaify halleder." align="left" />
          <TracingBeam className="mt-12 max-w-sm">
            <div className="flex flex-col gap-8 pl-10">
              {AUTOMATION_STEPS.map((step, index) => (
                <Reveal key={step.label} delay={index * 0.08}>
                  <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white text-primary shadow-floating">
                      <step.icon className="h-4 w-4" strokeWidth={2} />
                    </span>
                    <p className="text-sm font-medium text-dark/70">{step.label}</p>
                  </div>
                </Reveal>
              ))}
            </div>
          </TracingBeam>
        </Container>
      </section>

      <section className="px-6 py-16 lg:px-8">
        <Container>
          <SectionHeading title="Kargo firmaları" />
          <div className="mt-10">
            <BrandGrid categories={['shipping']} />
          </div>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
