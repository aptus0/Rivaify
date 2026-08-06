import { ShieldCheck } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { CheckoutPreview } from '../../components/marketing/CheckoutPreview/CheckoutPreview';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';

interface CheckoutProps {
  seo: { title: string; description: string };
}

export default function Checkout({ seo }: CheckoutProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Checkout"
        title={
          <>
            Satışın en kritik ekranı,
            <br />
            <span className="text-primary">markanın devamı olsun.</span>
          </>
        }
      />

      <CheckoutPreview />

      <section className="px-6 pb-24 lg:px-8">
        <Container size="narrow">
          <Reveal>
            <div className="flex items-start gap-4 rounded-card border border-dark/[0.07] bg-surface p-6">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-white text-primary shadow-floating">
                <ShieldCheck className="h-5 w-5" strokeWidth={2} />
              </span>
              <p className="text-sm leading-relaxed text-dark/60">
                Kritik ödeme işlevleri korunurken, görsel marka kimliği (renk, logo, yazı tipi) her
                zaman özelleştirilebilir kalır.
              </p>
            </div>
          </Reveal>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
