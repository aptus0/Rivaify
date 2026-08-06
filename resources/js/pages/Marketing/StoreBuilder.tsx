import { LayoutTemplate, MousePointerClick, Smartphone, type LucideIcon } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { StoreBuilder as StoreBuilderDemo } from '../../components/marketing/StoreBuilder/StoreBuilder';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';

interface StoreBuilderPageProps {
  seo: { title: string; description: string };
}

const LIBRARY: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: LayoutTemplate, title: 'Bölüm Kütüphanesi', description: 'Hero, ürün, koleksiyon, banner ve daha fazlası.' },
  { icon: MousePointerClick, title: 'Sürükle-Bırak', description: 'Bölümleri yeniden sırala, önizle, yayınla.' },
  { icon: Smartphone, title: 'Cihaz Modları', description: 'Masaüstü, tablet ve mobilde anlık önizleme.' },
];

export default function StoreBuilderPage({ seo }: StoreBuilderPageProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Store Builder"
        title={
          <>
            Kod yok. Sınır yok.
            <br />
            <span className="text-primary">Mağazanı görerek oluştur.</span>
          </>
        }
      />

      <section className="bg-surface px-6 py-16 lg:px-8">
        <Container>
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
            {LIBRARY.map((item, index) => (
              <Reveal key={item.title} delay={index * 0.06}>
                <div className="rounded-card border border-dark/[0.07] bg-white p-6 text-center">
                  <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-control bg-surface-orange text-primary">
                    <item.icon className="h-5 w-5" strokeWidth={2} />
                  </span>
                  <p className="mt-4 text-sm font-bold text-dark">{item.title}</p>
                  <p className="mt-1.5 text-xs leading-relaxed text-dark/45">{item.description}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <StoreBuilderDemo />
      <FinalCTA />
    </MarketingLayout>
  );
}
