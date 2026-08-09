import { motion } from 'framer-motion';
import { ArrowRight, Compass } from 'lucide-react';
import { Spotlight } from '../../../effects/Spotlight';
import { Button } from '../../../ui/Button';
import { CTA, REGISTER_URL } from '../../../../data/navigation';
import { useReducedMotion } from '../../../../hooks/useReducedMotion';
import { CommerceWall } from './CommerceWall';
import { CommerceWallMobile } from './CommerceWallMobile';

const LINE_DELAYS = [0.18, 0.26, 0.34];

function HeroLine({ delay, children }: { delay: number; children: React.ReactNode }) {
  const reducedMotion = useReducedMotion();
  return (
    <motion.span
      initial={{ opacity: 0, y: reducedMotion ? 0 : 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: reducedMotion ? 0.2 : 0.55, delay: reducedMotion ? 0 : delay, ease: [0.22, 1, 0.36, 1] }}
      className="block"
    >
      {children}
    </motion.span>
  );
}

/** Two-column hero: a deliberately sparse left message beside the "Rivaify
 * Commerce Wall" — three tilted, staggered columns of real Rivaify surfaces
 * (storefront, product, order, payment, inventory, shipment, analytics)
 * bleeding past the right edge. The point of departure from a generic
 * dashboard screenshot or a Shopier-style people collage: every card is a
 * real product surface, and one visibly cycles through an order's
 * lifecycle. See CommerceWall.tsx / CommerceWallMobile.tsx. */
export function HomeHero() {
  const reducedMotion = useReducedMotion();

  return (
    <section className="relative overflow-hidden pt-32 pb-24 lg:pt-40 lg:pb-32">
      <Spotlight className="inset-x-0 top-0 h-[560px]" />
      <CommerceWall />

      <div className="relative mx-auto max-w-7xl px-6 lg:px-8">
        <div className="relative z-10 max-w-xl">
          <motion.span
            initial={{ opacity: 0, y: reducedMotion ? 0 : 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: reducedMotion ? 0.2 : 0.5, delay: reducedMotion ? 0 : 0.1, ease: [0.22, 1, 0.36, 1] }}
            className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-surface-orange px-4 py-1.5 text-sm font-medium text-primary"
          >
            Yeni nesil e-ticaret altyapısı
          </motion.span>

          <h1 className="mt-8 text-5xl font-extrabold leading-[1.08] tracking-tight text-dark text-balance sm:text-6xl lg:text-[68px]">
            <HeroLine delay={LINE_DELAYS[0]}>Mağazanı kur.</HeroLine>
            <HeroLine delay={LINE_DELAYS[1]}>Her yerde sat.</HeroLine>
            <HeroLine delay={LINE_DELAYS[2]}>
              <span className="text-primary">Tek yerden yönet.</span>
            </HeroLine>
          </h1>

          <motion.p
            initial={{ opacity: 0, y: reducedMotion ? 0 : 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: reducedMotion ? 0.2 : 0.5, delay: reducedMotion ? 0 : 0.44, ease: [0.22, 1, 0.36, 1] }}
            className="mt-6 max-w-md text-lg leading-relaxed text-dark/55"
          >
            Ürünlerini, siparişlerini, müşterilerini, ödemelerini, stoklarını ve satış kanallarını
            Rivaify ile tek yerden yönet.
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: reducedMotion ? 0 : 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: reducedMotion ? 0.2 : 0.5, delay: reducedMotion ? 0 : 0.54, ease: [0.22, 1, 0.36, 1] }}
            className="mt-10 flex flex-col items-start gap-4 sm:flex-row sm:items-center"
          >
            <Button href={REGISTER_URL} variant="primary" size="lg" icon={ArrowRight} fullWidthOnMobile>
              {CTA.primary}
            </Button>
            <Button href="/platform" variant="ghost" size="lg" icon={Compass} iconPosition="left" fullWidthOnMobile>
              {CTA.secondary}
            </Button>
          </motion.div>
        </div>

        <div className="mt-16 lg:hidden">
          <CommerceWallMobile />
        </div>
      </div>
    </section>
  );
}
