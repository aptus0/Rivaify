import { Sparkles } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { RivaCard } from '../../components/effects/RivaCard';
import { Reveal } from '../../components/effects/Reveal';
import { Spotlight } from '../../components/effects/Spotlight';
import { Button } from '../../components/ui/Button';
import { Container } from '../../components/ui/Container';
import { REGISTER_URL } from '../../data/navigation';

interface PricingProps {
  seo: { title: string; description: string };
}

export default function Pricing({ seo }: PricingProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <section className="relative overflow-hidden px-6 pt-36 pb-24 lg:px-8 lg:pt-44 lg:pb-32">
        <Spotlight className="inset-x-0 top-0 h-[480px]" />

        <Container size="narrow" className="text-center">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-surface-orange px-4 py-1.5 text-sm font-medium text-primary">
              Fiyatlandırma
            </span>
          </Reveal>

          <Reveal delay={0.08}>
            <h1 className="mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight text-dark sm:text-5xl lg:text-6xl">
              Satış yaptıkça
              <br />
              <span className="text-primary">büyüyen model.</span>
            </h1>
          </Reveal>

          <Reveal delay={0.16}>
            <RivaCard variant="spectrum" intensity="medium" ambient className="mx-auto mt-12 max-w-lg p-8">
              <Sparkles className="mx-auto h-8 w-8 text-primary" strokeWidth={2} />
              <p className="mt-4 text-lg font-bold text-dark">Fiyatlandırma yakında açıklanacak.</p>
              <p className="mt-2 text-sm leading-relaxed text-dark/50">
                Rivaify ücret modeli henüz kesinleşmedi. Erken erişime katılan mağazalar, model
                açıklandığında ilk bilgilendirilenler arasında olacak.
              </p>
              <div className="mt-6 flex justify-center">
                <Button href={REGISTER_URL} variant="primary" size="lg">
                  Erken Erişim
                </Button>
              </div>
            </RivaCard>
          </Reveal>
        </Container>
      </section>
    </MarketingLayout>
  );
}
