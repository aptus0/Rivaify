import { Cpu, Gauge, ImageDown, Smartphone, type LucideIcon } from 'lucide-react';
import { Reveal } from '../../effects/Reveal';
import { Container } from '../../ui/Container';

const POINTS: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: Gauge, title: 'Hızlı storefront', description: 'Optimize edilmiş sayfa yükleme deneyimi.' },
  { icon: Cpu, title: 'Edge delivery', description: 'İçerik, müşterine en yakın noktadan sunulur.' },
  { icon: Smartphone, title: 'Duyarlı deneyim', description: 'Her cihazda tutarlı ve akıcı arayüz.' },
  { icon: ImageDown, title: 'Optimize varlıklar', description: 'Görseller otomatik olarak sıkıştırılır.' },
];

export function Performance() {
  return (
    <section className="px-6 py-20 lg:px-8 lg:py-24">
      <Container>
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {POINTS.map((point, index) => (
            <Reveal key={point.title} delay={index * 0.06}>
              <div className="flex flex-col items-center text-center sm:items-start sm:text-left">
                <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-surface-orange text-primary">
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
  );
}
