import { ArrowRight } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { HomeHero } from '../../components/marketing/sections/Home/HomeHero';
import { HomeBrandEcosystem } from '../../components/marketing/sections/Home/HomeBrandEcosystem';
import { HomeBento } from '../../components/marketing/sections/Home/HomeBento';
import { StoreBuilder } from '../../components/marketing/StoreBuilder/StoreBuilder';
import { CheckoutPreview } from '../../components/marketing/CheckoutPreview/CheckoutPreview';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';
import { Button } from '../../components/ui/Button';

interface HomeProps {
  seo: { title: string; description: string };
}

export default function Home({ seo }: HomeProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <HomeHero />
      <HomeBrandEcosystem />
      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container size="wide">
          <SectionHeading
            align="left"
            title="Ticaretinin her parçası, tek platformda."
            description="Mağazandan siparişine, checkout'undan analitiğine — her modül gerçek ürün arayüzüyle."
          />
          <div className="mt-12">
            <HomeBento />
          </div>
        </Container>
      </section>

      <section className="px-6 pt-24 lg:px-8 lg:pt-32">
        <Container size="wide">
          <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
            <SectionHeading
              align="left"
              eyebrow="Mağaza Tasarımcısı"
              title="Mağazanızı kod yazmadan tasarlayın."
              description="Bölümleri sürükleyerek sırala, cihaz modlarında anında önizle — bu gerçek Rivaify Store Builder deneyimi."
            />
            <Button href="/store-builder" variant="ghost" size="md" icon={ArrowRight}>
              Store Builder'ı Keşfet
            </Button>
          </div>
        </Container>
      </section>
      <StoreBuilder />

      <section className="px-6 pt-8 lg:px-8 lg:pt-8">
        <Container size="wide">
          <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
            <SectionHeading
              align="left"
              eyebrow="Checkout"
              title="Tek checkout. Markanıza özel deneyim."
              description="Ödeme motoru Rivaify'da kalır; logo, renk ve yazı tipi markanıza göre şekillenir."
            />
            <Button href="/platform" variant="ghost" size="md" icon={ArrowRight}>
              Platformu Keşfet
            </Button>
          </div>
        </Container>
      </section>
      <CheckoutPreview />
    </MarketingLayout>
  );
}
