import { ArrowRight, Globe, ShieldCheck, Zap } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Badge } from '../../ui/Badge';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';

const STATUS_CHIPS = [
  { icon: ShieldCheck, label: 'SSL' },
  { icon: Globe, label: 'Domain Bağlantısı' },
  { icon: Zap, label: 'CDN' },
];

export function CustomDomain() {
  return (
    <section id="domain" className="px-6 py-24 lg:px-8 lg:py-32">
      <Container size="narrow">
        <SectionHeading
          title={
            <>
              Markan senin.
              <br />
              <span className="text-primary">Alan adın da senin olsun.</span>
            </>
          }
        />

        <AuraCard intensity="medium" className="mt-12 rounded-2xl">
          <div className="rounded-2xl border border-dark/[0.07] bg-white p-8 text-center">
            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
              <span className="rounded-lg bg-surface px-4 py-2 font-mono text-sm text-dark/50">
                yasemingiyim.rivaify.com
              </span>
              <ArrowRight className="h-4 w-4 shrink-0 rotate-90 text-dark/30 sm:rotate-0" />
              <span className="rounded-lg bg-surface-orange px-4 py-2 font-mono text-sm font-semibold text-primary">
                yasemingiyim.com
              </span>
            </div>

            <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
              {STATUS_CHIPS.map((chip) => (
                <span
                  key={chip.label}
                  className="inline-flex items-center gap-2 rounded-full border border-dark/10 bg-surface/60 px-4 py-2 text-xs font-medium text-dark/60"
                >
                  <chip.icon className="h-3.5 w-3.5 text-dark/40" strokeWidth={2} />
                  {chip.label}
                  <Badge variant="soon" className="ml-1">Yakında</Badge>
                </span>
              ))}
            </div>
          </div>
        </AuraCard>
      </Container>
    </section>
  );
}
