import { Camera, Store, ThumbsUp, Video, type LucideIcon } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Reveal } from '../../effects/Reveal';
import { Badge } from '../../ui/Badge';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';
import { Logo } from '../../Logo';

interface ChannelNode {
  icon: LucideIcon;
  name: string;
  status: 'live' | 'soon';
  detail: string;
}

const TOP_CHANNELS: ChannelNode[] = [
  { icon: Camera, name: 'Instagram', status: 'soon', detail: 'Katalog senkronizasyonu' },
  { icon: ThumbsUp, name: 'Facebook', status: 'soon', detail: 'Mağaza entegrasyonu' },
  { icon: Video, name: 'TikTok', status: 'soon', detail: 'Ürün keşfi' },
];

function ChannelCard({ channel }: { channel: ChannelNode }) {
  return (
    <AuraCard intensity="subtle" className="rounded-2xl">
      <div className="flex flex-col items-center gap-2 rounded-2xl border border-dark/[0.07] bg-white px-5 py-4 text-center">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-orange text-primary">
          <channel.icon className="h-5 w-5" strokeWidth={2} />
        </span>
        <p className="text-sm font-bold text-dark">{channel.name}</p>
        <p className="text-xs text-dark/40">{channel.detail}</p>
        <Badge variant={channel.status === 'live' ? 'default' : 'soon'}>
          {channel.status === 'live' ? 'Aktif' : 'Planlanıyor'}
        </Badge>
      </div>
    </AuraCard>
  );
}

/** Static, sparse connector lines rather than a full graph-layout library —
 * three lines converge from the channel row into the Rivaify node, one
 * continues down to the storefront. Kept subtle per the brief ("animated but
 * subtle connector paths"): a slow opacity pulse, no moving particles. */
function Connectors() {
  return (
    <svg
      viewBox="0 0 600 160"
      preserveAspectRatio="none"
      className="pointer-events-none absolute inset-0 -z-10 h-full w-full motion-reduce:[&_path]:animate-none"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id="connector-gradient" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#FF6B00" stopOpacity="0.35" />
          <stop offset="100%" stopColor="#7C5CFC" stopOpacity="0.15" />
        </linearGradient>
      </defs>
      {[80, 300, 520].map((x, index) => (
        <path
          key={x}
          d={`M ${x} 20 C ${x} 70, 300 60, 300 100`}
          fill="none"
          stroke="url(#connector-gradient)"
          strokeWidth="1.5"
          className="animate-pulse"
          style={{ animationDuration: '3.5s', animationDelay: `${index * 0.3}s` }}
        />
      ))}
      <path d="M 300 100 L 300 150" fill="none" stroke="url(#connector-gradient)" strokeWidth="1.5" />
    </svg>
  );
}

export function SocialCommerce() {
  return (
    <section id="sosyal-ticaret" className="px-6 py-24 lg:px-8 lg:py-32">
      <Container>
        <SectionHeading
          title={
            <>
              Müşterilerin neredeyse,
              <br />
              <span className="text-primary">mağazan da orada.</span>
            </>
          }
          description="Rivaify satış kanallarını tek bir merkezde birleştirir."
        />

        <Reveal delay={0.15} className="relative mx-auto mt-16 max-w-3xl">
          <Connectors />

          <div className="relative grid grid-cols-3 gap-4">
            {TOP_CHANNELS.map((channel) => (
              <ChannelCard key={channel.name} channel={channel} />
            ))}
          </div>

          <div className="relative z-10 mx-auto my-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-dark/[0.08] bg-dark shadow-[0_16px_32px_-12px_rgba(13,17,23,0.4)]">
            <Logo variant="icon" />
          </div>

          <div className="relative mx-auto max-w-xs">
            <AuraCard intensity="medium" className="rounded-2xl">
              <div className="flex flex-col items-center gap-2 rounded-2xl border border-dark/[0.07] bg-white px-5 py-4 text-center">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-orange text-primary">
                  <Store className="h-5 w-5" strokeWidth={2} />
                </span>
                <p className="text-sm font-bold text-dark">Online Mağaza</p>
                <p className="text-xs text-dark/40">Stok · Sipariş · Katalog</p>
                <Badge variant="default">Aktif</Badge>
              </div>
            </AuraCard>
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
