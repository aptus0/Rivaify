import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { PageHero } from '../../components/marketing/sections/PageHero';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { RivaCard } from '../../components/effects/RivaCard';
import { BrandLogo } from '../../components/brands/BrandLogo';
import { Badge } from '../../components/ui/Badge';
import { Container } from '../../components/ui/Container';
import { CATEGORY_LABEL, INTEGRATIONS, STATUS_LABEL, type IntegrationCategory, type IntegrationStatus } from '../../data/integrations';

interface IntegrationsProps {
  seo: { title: string; description: string };
}

type Filter = 'all' | IntegrationStatus | IntegrationCategory;

const FILTERS: { key: Filter; label: string }[] = [
  { key: 'all', label: 'Tümü' },
  { key: 'planned', label: 'Planlanıyor' },
  { key: 'coming-soon', label: 'Yakında' },
  { key: 'social', label: 'Sosyal' },
  { key: 'payment', label: 'Ödeme' },
  { key: 'shipping', label: 'Kargo' },
];

export default function Integrations({ seo }: IntegrationsProps) {
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<Filter>('all');

  const results = useMemo(() => {
    return INTEGRATIONS.filter((integration) => {
      const matchesQuery = integration.name.toLowerCase().includes(query.toLowerCase());
      const matchesFilter = filter === 'all' || integration.category === filter || integration.status === filter;
      return matchesQuery && matchesFilter;
    });
  }, [query, filter]);

  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <PageHero
        eyebrow="Integrations"
        title={
          <>
            Rivaify'ı işletmenle
            <br />
            <span className="text-primary">birlikte büyüt.</span>
          </>
        }
      >
        <div className="relative mx-auto mt-10 max-w-lg px-6 lg:px-8">
          <div className="relative">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-dark/30" />
            <input
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Entegrasyon ara..."
              className="w-full rounded-full border border-dark/10 bg-white py-3 pl-11 pr-4 text-sm text-dark shadow-floating outline-none ring-primary/30 focus:ring-2"
            />
          </div>
        </div>
      </PageHero>

      <section className="px-6 pb-24 lg:px-8">
        <Container size="wide">
          <div className="flex flex-wrap justify-center gap-2">
            {FILTERS.map((f) => (
              <button
                key={f.key}
                type="button"
                onClick={() => setFilter(f.key)}
                aria-pressed={filter === f.key}
                className={`inline-flex min-h-9 items-center rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors ${
                  filter === f.key
                    ? 'border-primary bg-primary text-white'
                    : 'border-dark/10 bg-white text-dark/60 hover:border-dark/20'
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>

          <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {results.map((integration) => (
              <RivaCard key={integration.key} variant="spectrum" intensity="subtle" className="flex flex-col p-5">
                <div className="flex items-start justify-between gap-3">
                  <BrandLogo brand={integration.key} size="sm" showName={false} />
                  <Badge variant="soon">{STATUS_LABEL[integration.status]}</Badge>
                </div>
                <p className="mt-3 text-sm font-bold text-dark">{integration.name}</p>
                <p className="mt-1 text-xs text-dark/45">{integration.description}</p>
                <p className="mt-3 text-[11px] font-medium uppercase tracking-wide text-dark/30">
                  {CATEGORY_LABEL[integration.category]}
                </p>
              </RivaCard>
            ))}
            {results.length === 0 && (
              <p className="col-span-full py-12 text-center text-sm text-dark/40">Eşleşen entegrasyon bulunamadı.</p>
            )}
          </div>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
