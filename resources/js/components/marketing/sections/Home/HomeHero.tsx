import { ArrowRight, Compass } from 'lucide-react';
import { FocusCorners } from '../../../effects/FocusCorners';
import { PointerGlow } from '../../../effects/PointerGlow';
import { RivaCard } from '../../../effects/RivaCard';
import { ScanReveal } from '../../../effects/ScanReveal';
import { Spotlight } from '../../../effects/Spotlight';
import { Reveal } from '../../../effects/Reveal';
import { Button } from '../../../ui/Button';
import { CTA, REGISTER_URL } from '../../../../data/navigation';
import { HomeDashboardPreview } from './HomeDashboardPreview';
import { HomeFloatingChips } from './HomeFloatingChips';

export function HomeHero() {
  return (
    <PointerGlow className="overflow-hidden pt-36 pb-20 lg:pt-44 lg:pb-28">
      <Spotlight className="inset-x-0 top-0 h-[560px]" />

      <div className="relative mx-auto max-w-4xl px-6 text-center lg:px-8">
        <Reveal>
          <span className="inline-flex items-center gap-2 rounded-full border border-primary/40 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary shadow-[0_0_15px_rgba(255,107,0,0.15)]">
            Yeni nesil ticaret altyapısı
          </span>
        </Reveal>

        <Reveal delay={0.08}>
          <h1 className="mt-8 text-5xl font-extrabold leading-[1.05] tracking-tight text-white sm:text-6xl lg:text-[76px]">
            Mağazanı kur.
            <br />
            Her yerde sat.
            <br />
            <span className="text-primary drop-shadow-[0_0_20px_rgba(255,107,0,0.3)]">Tek yerden yönet.</span>
          </h1>
        </Reveal>

        <Reveal delay={0.16}>
          <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/60">
            Rivaify, ürünlerinden siparişlerine ve sosyal satış kanallarından mağaza tasarımına kadar
            e-ticaret operasyonunu tek platformda birleştirir.
          </p>
        </Reveal>

        <Reveal delay={0.24}>
          <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
            <Button href={REGISTER_URL} variant="primary" size="lg" icon={ArrowRight} fullWidthOnMobile>
              {CTA.primary}
            </Button>
            <Button href="/platform" variant="secondary" size="lg" icon={Compass} iconPosition="left" fullWidthOnMobile>
              {CTA.secondary}
            </Button>
          </div>
        </Reveal>
      </div>

      <Reveal delay={0.3} className="relative mx-auto mt-20 max-w-6xl px-6 lg:px-8">
        <FocusCorners>
          <RivaCard variant="spectrum" intensity="strong" ambient radius="window">
            <HomeFloatingChips />
            <ScanReveal>
              <HomeDashboardPreview />
            </ScanReveal>
          </RivaCard>
        </FocusCorners>
      </Reveal>
    </PointerGlow>
  );
}
