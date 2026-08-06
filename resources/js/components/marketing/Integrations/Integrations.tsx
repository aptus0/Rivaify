import {
  BarChart3,
  Camera,
  Code2,
  CreditCard,
  ShoppingBag,
  ThumbsUp,
  Truck,
  Video,
  Webhook,
  type LucideIcon,
} from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Badge } from '../../ui/Badge';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';

interface IntegrationTile {
  icon: LucideIcon;
  name: string;
  status: 'planned' | 'infra';
}

interface IntegrationCategory {
  title: string;
  tiles: IntegrationTile[];
}

const CATEGORIES: IntegrationCategory[] = [
  {
    title: 'Sosyal Ticaret',
    tiles: [
      { icon: Camera, name: 'Instagram', status: 'planned' },
      { icon: ThumbsUp, name: 'Facebook', status: 'planned' },
      { icon: Video, name: 'TikTok', status: 'planned' },
    ],
  },
  {
    title: 'Ödeme',
    tiles: [
      { icon: CreditCard, name: 'Ödeme Sağlayıcıları', status: 'infra' },
    ],
  },
  {
    title: 'Kargo',
    tiles: [{ icon: Truck, name: 'Kargo Firmaları', status: 'planned' }],
  },
  {
    title: 'Pazaryeri',
    tiles: [{ icon: ShoppingBag, name: 'Pazaryeri Bağlantıları', status: 'planned' }],
  },
  {
    title: 'Analitik',
    tiles: [{ icon: BarChart3, name: 'Veri & Raporlama', status: 'infra' }],
  },
  {
    title: 'Geliştirici',
    tiles: [{ icon: Code2, name: 'API', status: 'infra' }, { icon: Webhook, name: 'Webhooks', status: 'infra' }],
  },
];

const STATUS_LABEL: Record<IntegrationTile['status'], string> = {
  planned: 'Planlanıyor',
  infra: 'Entegrasyon altyapısı',
};

export function Integrations() {
  return (
    <section id="entegrasyonlar" className="bg-surface px-6 py-24 lg:px-8 lg:py-32">
      <Container>
        <SectionHeading
          title={
            <>
              Büyüyen bir <span className="text-primary">entegrasyon ekosistemi.</span>
            </>
          }
          description="Rivaify, mağazanı ödeme, kargo ve pazaryeri altyapılarıyla genişletmek için tasarlandı."
        />

        <div className="mt-14 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {CATEGORIES.map((category) => (
            <AuraCard key={category.title} intensity="subtle" className="rounded-2xl">
              <div className="rounded-2xl border border-dark/[0.07] bg-white p-5">
                <p className="text-[11px] font-bold uppercase tracking-wider text-dark/35">{category.title}</p>
                <div className="mt-3 flex flex-col gap-2.5">
                  {category.tiles.map((tile) => (
                    <div key={tile.name} className="flex items-center justify-between gap-2">
                      <span className="flex items-center gap-2.5 text-sm font-medium text-dark/70">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-surface-orange text-primary">
                          <tile.icon className="h-4 w-4" strokeWidth={2} />
                        </span>
                        {tile.name}
                      </span>
                      <Badge variant="soon" className="shrink-0">
                        {STATUS_LABEL[tile.status]}
                      </Badge>
                    </div>
                  ))}
                </div>
              </div>
            </AuraCard>
          ))}
        </div>
      </Container>
    </section>
  );
}
