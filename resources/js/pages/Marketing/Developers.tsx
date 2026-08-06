import type { ReactNode } from 'react';
import { Code2, KeyRound, ShoppingBag, Store, Webhook, type LucideIcon } from 'lucide-react';
import { MarketingLayout } from '../../layouts/MarketingLayout';
import { FinalCTA } from '../../components/marketing/FinalCTA/FinalCTA';
import { RivaCard } from '../../components/effects/RivaCard';
import { Reveal } from '../../components/effects/Reveal';
import { Spotlight } from '../../components/effects/Spotlight';
import { Badge } from '../../components/ui/Badge';
import { Container } from '../../components/ui/Container';
import { SectionHeading } from '../../components/ui/SectionHeading';

interface DevelopersProps {
  seo: { title: string; description: string };
}

const CAPABILITIES: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: Code2, title: 'REST API', description: 'Ürün, sipariş ve müşteri verilerine programatik erişim.' },
  { icon: Webhook, title: 'Webhooks', description: 'Olay tabanlı bildirimlerle sistemini senkron tut.' },
  { icon: KeyRound, title: 'OAuth Apps', description: 'Güvenli yetkilendirme akışıyla uygulama bağla.' },
  { icon: Store, title: 'Storefront API', description: 'Mağaza deneyimini kendi arayüzünle sun.' },
  { icon: ShoppingBag, title: 'Admin API', description: 'Yönetim işlemlerini kendi araçlarından yürüt.' },
];

function CodeLine({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <p className={`whitespace-pre text-[13px] leading-relaxed ${className}`}>{children}</p>;
}

export default function Developers({ seo }: DevelopersProps) {
  return (
    <MarketingLayout title={seo.title} description={seo.description}>
      <section className="relative overflow-hidden bg-dark px-6 pt-36 pb-24 text-white lg:px-8 lg:pt-44 lg:pb-32">
        <Spotlight className="inset-x-0 top-0 h-[520px]" color="rgba(32, 199, 199, 0.14)" />

        <div className="relative mx-auto max-w-3xl px-0 text-center">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.06] px-4 py-1.5 text-sm font-medium text-primary-soft">
              Developers
            </span>
          </Reveal>
          <Reveal delay={0.08}>
            <h1 className="mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
              Rivaify'ın üzerine
              <br />
              <span className="text-primary-soft">kendi commerce deneyimini kur.</span>
            </h1>
          </Reveal>
        </div>

        <Reveal delay={0.2} className="relative mx-auto mt-14 max-w-2xl">
          <RivaCard variant="glass" radius="showcase" className="overflow-hidden bg-[#0d0f14]! p-0 font-mono">
            <div className="flex items-center gap-1.5 border-b border-white/10 px-4 py-3">
              <span className="h-2.5 w-2.5 rounded-full bg-white/15" />
              <span className="h-2.5 w-2.5 rounded-full bg-white/15" />
              <span className="h-2.5 w-2.5 rounded-full bg-white/15" />
              <span className="ml-3 text-[11px] text-white/30">terminal</span>
            </div>
            <div className="flex flex-col gap-3 p-5 text-white/70">
              <CodeLine>
                <span className="text-primary-soft">GET</span> /api/v1/products
              </CodeLine>
              <CodeLine className="text-white/40">→ 200 OK — ürün listesi</CodeLine>
              <CodeLine>
                <span className="text-[#20C7C7]">POST</span> /api/v1/orders
              </CodeLine>
              <CodeLine className="text-white/40">→ 201 Created — yeni sipariş</CodeLine>
            </div>
          </RivaCard>
        </Reveal>
      </section>

      <section className="bg-soft-dark px-6 py-24 text-white lg:px-8 lg:py-32">
        <Container>
          <SectionHeading onDark title="Geliştirici araçları" />
          <div className="mt-14 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {CAPABILITIES.map((item, index) => (
              <Reveal key={item.title} delay={index * 0.06}>
                <div className="rounded-card border border-white/10 bg-white/[0.03] p-6">
                  <span className="flex h-10 w-10 items-center justify-center rounded-control bg-white/[0.06] text-primary-soft">
                    <item.icon className="h-5 w-5" strokeWidth={2} />
                  </span>
                  <p className="mt-4 text-sm font-bold text-white">{item.title}</p>
                  <p className="mt-1.5 text-xs leading-relaxed text-white/45">{item.description}</p>
                </div>
              </Reveal>
            ))}
          </div>

          <div className="mt-12 flex justify-center">
            <Badge variant="onDark">Dokümantasyon — Yakında</Badge>
          </div>
        </Container>
      </section>

      <FinalCTA />
    </MarketingLayout>
  );
}
