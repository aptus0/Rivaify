import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { RivaCard } from '../../components/effects/RivaCard';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';
import { SOLUTIONS_MENU } from '../../data/navigation';

interface SolutionsProps {
  seo: { title: string; description: string };
}

const FEATURE_TAGS: Record<string, string[]> = {
  Moda: ['Varyant', 'Koleksiyon', 'Lookbook'],
  Perakende: ['Barkod', 'Stok', 'Hızlı Sepet'],
  Elektronik: ['Teknik Özellik', 'Varyant', 'Garanti'],
  'Dijital Ürün': ['Kargosuz', 'Anlık Teslim'],
};

export default function Solutions({ seo }: SolutionsProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Çözümler"
        title={
          <>
            Her işletme aynı değil.
            <br />
            <span className="text-primary">Rivaify olmak zorunda da değil.</span>
          </>
        }
      />

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container>
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {SOLUTIONS_MENU.map((solution, index) => (
              <Reveal key={solution.label} delay={index * 0.06}>
                <RivaCard variant="spectrum" intensity="subtle" className="flex h-full flex-col p-6">
                  <p className="text-lg font-bold text-dark">{solution.label}</p>
                  <p className="mt-2 text-sm leading-relaxed text-dark/50">{solution.description}</p>
                  {FEATURE_TAGS[solution.label] && (
                    <div className="mt-4 flex flex-wrap gap-1.5">
                      {FEATURE_TAGS[solution.label].map((tag) => (
                        <span key={tag} className="rounded-full border border-dark/10 bg-surface px-2.5 py-1 text-[11px] font-medium text-dark/50">
                          {tag}
                        </span>
                      ))}
                    </div>
                  )}
                </RivaCard>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
