import { useState } from 'react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { Analytics as AnalyticsSection } from '../../components/marketing/Analytics/Analytics';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { Badge } from '../../components/ui/Badge';
import { Container } from '../../components/ui/Container';

interface AnalyticsProps {
  seo: { title: string; description: string };
}

const PERIODS = ['Bugün', '7 Gün', '30 Gün', '90 Gün'] as const;

export default function AnalyticsPage({ seo }: AnalyticsProps) {
  const [period, setPeriod] = useState<(typeof PERIODS)[number]>('30 Gün');

  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Analytics"
        onDark
        spotlightColor="rgba(121, 87, 255, 0.18)"
        title={
          <>
            Rakamları değil,
            <br />
            <span className="text-primary-soft">ticaretini anla.</span>
          </>
        }
      >
        <div className="relative z-10 mt-4 flex justify-center">
          <Badge variant="onDark">Demo Data</Badge>
        </div>
      </PageHero>

      <section className="px-6 pt-16 lg:px-8">
        <Container>
          <div className="flex justify-center gap-2">
            {PERIODS.map((p) => (
              <button
                key={p}
                type="button"
                onClick={() => setPeriod(p)}
                aria-pressed={period === p}
                className={`inline-flex min-h-9 items-center rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors ${
                  period === p ? 'border-primary bg-primary text-white' : 'border-dark/10 bg-white text-dark/60 hover:border-dark/20'
                }`}
              >
                {p}
              </button>
            ))}
          </div>
        </Container>
      </section>

      {/* Demo dataset doesn't vary by period — re-keying replays the reveal
          animation on switch so the period buttons still give feedback
          rather than doing visibly nothing. */}
      <AnalyticsSection key={period} />
      <FinalCTA />
    </MarketingLayout>
  );
}
