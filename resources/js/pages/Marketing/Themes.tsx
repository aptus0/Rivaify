import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { ThemeShowcase } from '../../components/marketing/ThemeShowcase/ThemeShowcase';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';

interface ThemesProps {
  seo: { title: string; description: string };
}

export default function Themes({ seo }: ThemesProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Themes"
        title={
          <>
            Her marka farklı.
            <br />
            <span className="text-primary">Teması da öyle.</span>
          </>
        }
        description="8 profesyonel tema ile başlıyoruz — koleksiyon zamanla büyüyecek."
      />
      <ThemeShowcase />
      <FinalCTA />
    </MarketingLayout>
  );
}
