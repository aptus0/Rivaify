import type { ReactNode } from 'react';
import { ArrowRight } from 'lucide-react';
import { Reveal } from '../../effects/Reveal';
import { Spotlight } from '../../effects/Spotlight';
import { Button } from '../../ui/Button';
import { REGISTER_URL } from '../../../data/navigation';

interface PageHeroProps {
  eyebrow?: string;
  title: ReactNode;
  description?: string;
  onDark?: boolean;
  ctaLabel?: string;
  ctaHref?: string;
  spotlightColor?: string;
  children?: ReactNode;
}

/** Shared hero shell for the lighter-treatment marketing pages (Platform,
 * Payments, Shipping, Solutions, Security, Pricing, ...) — each still gets
 * its own heading/copy and usually a distinguishing visual passed as
 * `children`, just without Home's full bespoke composition. */
export function PageHero({
  eyebrow,
  title,
  description,
  onDark = false,
  ctaLabel = 'Mağazanı Oluştur',
  ctaHref = REGISTER_URL,
  spotlightColor,
  children,
}: PageHeroProps) {
  return (
    <section className={`relative overflow-hidden pt-36 pb-20 lg:pt-44 lg:pb-24 ${onDark ? 'bg-dark text-white' : ''}`}>
      <Spotlight className="inset-x-0 top-0 h-[480px]" color={spotlightColor} />

      <div className="relative mx-auto max-w-3xl px-6 text-center lg:px-8">
        {eyebrow && (
          <Reveal>
            <span
              className={`inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium ${
                onDark ? 'border-white/15 bg-white/[0.06] text-primary-soft' : 'border-primary/20 bg-surface-orange text-primary'
              }`}
            >
              {eyebrow}
            </span>
          </Reveal>
        )}

        <Reveal delay={0.08}>
          <h1
            className={`mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl ${
              onDark ? 'text-white' : 'text-dark'
            }`}
          >
            {title}
          </h1>
        </Reveal>

        {description && (
          <Reveal delay={0.16}>
            <p className={`mx-auto mt-6 max-w-2xl text-lg leading-relaxed ${onDark ? 'text-white/55' : 'text-dark/55'}`}>
              {description}
            </p>
          </Reveal>
        )}

        <Reveal delay={0.24}>
          <div className="mt-9 flex justify-center">
            <Button href={ctaHref} variant="primary" size="lg" icon={ArrowRight}>
              {ctaLabel}
            </Button>
          </div>
        </Reveal>
      </div>

      {children}
    </section>
  );
}
