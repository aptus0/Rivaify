import { Package, Plug, ShoppingCart, Sparkles, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { PlatformNetwork } from '../../components/marketing/sections/Platform/PlatformNetwork';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { TracingBeam } from '../../components/effects/TracingBeam';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

interface PlatformProps {
  seo: { title: string; description: string };
}

const STORY_POINTS: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: Package, title: 'Katalog', description: 'Ürün, varyant, kategori ve marka yönetimi.' },
  { icon: ShoppingCart, title: 'Siparişler', description: 'Sipariş akışını uçtan uca tek ekrandan yönet.' },
  { icon: Users, title: 'Müşteriler', description: 'Müşteri profilleri, segmentler ve geçmiş.' },
  { icon: Warehouse, title: 'Stok', description: 'Depo bazlı stok ve hareket takibi.' },
  { icon: Sparkles, title: 'Otomasyon', description: 'Tekrarlayan işlemleri Rivaify halletsin.' },
  { icon: Plug, title: 'Entegrasyonlar', description: 'Ödeme, kargo ve pazaryeri bağlantıları.' },
];

const LIFECYCLE = [
  'Ürün oluşturuldu',
  'Online yayına alındı',
  'Instagram kataloğu senkronize oldu',
  'Müşteri sipariş verdi',
  'Ödeme alındı',
  'Stok güncellendi',
  'Kargoya verildi',
  'Analitik panelinde göründü',
];

export default function Platform({ seo }: PlatformProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Platform"
        title={
          <>
            Ticaret operasyonunun tamamı.
            <br />
            <span className="text-primary">Tek işletim sistemi.</span>
          </>
        }
      >
        <div className="relative mx-auto mt-16 max-w-6xl px-6 lg:px-8">
          <PlatformNetwork />
        </div>
      </PageHero>

      <section className="bg-surface px-6 py-24 lg:px-8 lg:py-32">
        <Container>
          <SectionHeading title="Her modül, tek platformda birbirine bağlı." />
          <div className="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {STORY_POINTS.map((point, index) => (
              <Reveal key={point.title} delay={index * 0.05}>
                <div className="rounded-card border border-dark/[0.07] bg-white p-6">
                  <span className="flex h-10 w-10 items-center justify-center rounded-control bg-surface-orange text-primary">
                    <point.icon className="h-5 w-5" strokeWidth={2} />
                  </span>
                  <p className="mt-4 text-sm font-bold text-dark">{point.title}</p>
                  <p className="mt-1.5 text-xs leading-relaxed text-dark/45">{point.description}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <Container size="narrow">
          <SectionHeading
            title="Bir moda mağazasında tipik bir gün."
            description="Ürün oluşturmadan analitik panele düşene kadar tüm yaşam döngüsü."
          />

          <TracingBeam className="mx-auto mt-14 max-w-md">
            <div className="flex flex-col gap-8 pl-10">
              {LIFECYCLE.map((step, index) => (
                <Reveal key={step} delay={index * 0.05}>
                  <div className="flex items-center gap-3">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white">
                      {index + 1}
                    </span>
                    <p className="text-sm font-medium text-dark/70">{step}</p>
                  </div>
                </Reveal>
              ))}
            </div>
          </TracingBeam>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
