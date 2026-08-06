import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { SocialCommerce as SocialCommerceSection } from '../../components/marketing/SocialCommerce/SocialCommerce';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';

interface SocialCommercePageProps {
  seo: { title: string; description: string };
}

export default function SocialCommercePage({ seo }: SocialCommercePageProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Social Commerce"
        title={
          <>
            Takipçileri
            <br />
            <span className="text-primary">müşteriye dönüştür.</span>
          </>
        }
      />
      <SocialCommerceSection />
      <FinalCTA />
    </MarketingLayout>
  );
}
