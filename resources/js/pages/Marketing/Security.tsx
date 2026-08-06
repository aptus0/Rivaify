import { Cloud, Database, Lock, Server, ShieldCheck, User, type LucideIcon } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { Trust } from '../../components/marketing/Trust/Trust';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { TracingBeam } from '../../components/effects/TracingBeam';
import { Reveal } from '../../components/effects/Reveal';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

interface SecurityProps {
  seo: { title: string; description: string };
}

const FLOW: { icon: LucideIcon; label: string }[] = [
  { icon: User, label: 'Müşteri' },
  { icon: Cloud, label: 'Cloudflare' },
  { icon: Server, label: 'Rivaify Application' },
  { icon: ShieldCheck, label: 'Tenant Isolation' },
  { icon: Database, label: 'Database' },
  { icon: Lock, label: 'Encrypted Storage' },
];

export default function Security({ seo }: SecurityProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Security"
        onDark
        spotlightColor="rgba(32, 199, 199, 0.14)"
        title={
          <>
            Ticaret altyapısında
            <br />
            <span className="text-primary-soft">güven tesadüf değildir.</span>
          </>
        }
      />

      <section className="bg-soft-dark px-6 py-24 text-white lg:px-8 lg:py-32">
        <Container size="narrow">
          <SectionHeading onDark title="İstek nasıl işlenir." />
          <TracingBeam className="mx-auto mt-14 max-w-xs">
            <div className="flex flex-col gap-8 pl-10">
              {FLOW.map((step, index) => (
                <Reveal key={step.label} delay={index * 0.06}>
                  <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white/[0.06] text-primary-soft">
                      <step.icon className="h-4 w-4" strokeWidth={2} />
                    </span>
                    <p className="text-sm font-medium text-white/70">{step.label}</p>
                  </div>
                </Reveal>
              ))}
            </div>
          </TracingBeam>
        </Container>
      </section>

      <Trust />
      <FinalCTA />
    </MarketingLayout>
  );
}
