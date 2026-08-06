import { Layers, Sparkles } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Reveal } from '../../effects/Reveal';
import { Badge } from '../../ui/Badge';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';

const GENERATED_BLOCKS = ['Hero', 'Koleksiyon Grid', 'Çok Satanlar', 'Yorumlar'];

export function AIAssistant() {
  return (
    <section className="px-6 py-24 lg:px-8 lg:py-32">
      <Container size="narrow">
        <SectionHeading eyebrow="Yakında" title="Daha akıllı commerce için tasarlandı." />

        <AuraCard intensity="medium" ambient className="mt-12 rounded-2xl">
          <div className="rounded-2xl border border-dark/[0.07] bg-white p-6 sm:p-8">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-dark text-primary-soft">
                  <Sparkles className="h-4 w-4" strokeWidth={2.5} />
                </span>
                <p className="text-sm font-bold text-dark">Rivaify Asistan</p>
              </div>
              <Badge variant="soon">Yakında</Badge>
            </div>

            <div className="mt-6 flex flex-col gap-4">
              <div className="flex justify-end">
                <div className="max-w-xs rounded-2xl rounded-tr-sm bg-dark px-4 py-2.5 text-sm text-white">
                  Yaz koleksiyonum için ana sayfa oluştur.
                </div>
              </div>

              <Reveal>
                <div className="max-w-sm rounded-2xl rounded-tl-sm border border-dark/[0.07] bg-surface/60 px-4 py-3 text-sm text-dark/70">
                  <p>Yaz koleksiyonun için 4 bölümlük bir ana sayfa taslağı hazırladım:</p>
                  <div className="mt-3 flex flex-col gap-1.5">
                    {GENERATED_BLOCKS.map((block) => (
                      <div key={block} className="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-dark">
                        <Layers className="h-3.5 w-3.5 text-primary" strokeWidth={2} />
                        {block}
                      </div>
                    ))}
                  </div>
                </div>
              </Reveal>
            </div>
          </div>
        </AuraCard>
      </Container>
    </section>
  );
}
