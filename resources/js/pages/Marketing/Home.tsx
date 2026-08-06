import { MarketingLayout } from '../../layouts/MarketingLayout';
import { HomeHero } from '../../components/marketing/sections/Home/HomeHero';
import { HomeBrandEcosystem } from '../../components/marketing/sections/Home/HomeBrandEcosystem';
import { HomeBento } from '../../components/marketing/sections/Home/HomeBento';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

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
    </MarketingLayout>
  );
}
