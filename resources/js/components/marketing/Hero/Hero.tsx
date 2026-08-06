import { ArrowRight, Compass } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { CursorAura } from '../../effects/CursorAura';
import { ScanHighlight } from '../../effects/ScanHighlight';
import { Reveal } from '../../effects/Reveal';
import { Button } from '../../ui/Button';
import { REGISTER_URL } from '../../../constants/site';
import { RivaifyDashboardPreview } from './RivaifyDashboardPreview';
import { FloatingChips } from './FloatingChips';

export function Hero() {
  return (
    <CursorAura className="relative overflow-hidden pt-36 pb-20 lg:pt-44 lg:pb-28">
      <section id="top">
        <div className="relative mx-auto max-w-4xl px-6 text-center lg:px-8">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-surface-orange px-4 py-1.5 text-sm font-medium text-primary">
              Yeni nesil e-ticaret platformu
            </span>
          </Reveal>

          <Reveal delay={0.08}>
            <h1 className="mt-8 text-5xl font-extrabold leading-[1.05] tracking-tight text-dark sm:text-6xl lg:text-[76px]">
              Satışın geleceğini
              <br />
              <span className="text-primary">tek platformda</span> yönet.
            </h1>
          </Reveal>

          <Reveal delay={0.16}>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-dark/55">
              Rivaify ile mağazanı oluştur, ürünlerini yönet, satış kanallarını bağla ve e-ticaret
              operasyonunu tek bir modern platformdan yönet.
            </p>
          </Reveal>

          <Reveal delay={0.24}>
            <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
              <Button href={REGISTER_URL} variant="primary" size="lg" icon={ArrowRight} fullWidthOnMobile>
                Mağazanı Oluştur
              </Button>
              <Button href="#kontrol-merkezi" variant="secondary" size="lg" icon={Compass} iconPosition="left" fullWidthOnMobile>
                Platformu Keşfet
              </Button>
            </div>
          </Reveal>
        </div>

        <Reveal delay={0.3} className="relative mx-auto mt-20 max-w-6xl px-6 lg:px-8">
          <AuraCard intensity="strong" ambient className="rounded-2xl">
            <FloatingChips />
            <ScanHighlight>
              <RivaifyDashboardPreview />
            </ScanHighlight>
          </AuraCard>
        </Reveal>
      </section>
    </CursorAura>
  );
}
