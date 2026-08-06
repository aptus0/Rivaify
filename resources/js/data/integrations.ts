import {
  BarChart3,
  Calculator,
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

export type IntegrationStatus = 'available' | 'beta' | 'coming-soon' | 'planned' | 'connected';

export type IntegrationCategory =
  | 'social'
  | 'payment'
  | 'shipping'
  | 'marketplace'
  | 'marketing'
  | 'analytics'
  | 'developer'
  | 'accounting';

export interface Integration {
  key: string;
  name: string;
  category: IntegrationCategory;
  status: IntegrationStatus;
  description: string;
  /** public/brands/{key}.svg — undefined until a licensed asset is supplied.
   * BrandLogo falls back to `icon` until then; swapping in a real logo
   * later needs no component changes, only this field. */
  logoPath?: string;
  icon: LucideIcon;
}

// Status is never hardcoded into a visual component — it's read from here,
// which in turn should eventually be backed by real project config. None of
// these integrations are actually built yet (Sprint 2 status: Catalog/
// Inventory only) — do not upgrade a status without confirming the backend
// integration actually shipped.
export const INTEGRATIONS: Integration[] = [
  { key: 'instagram', name: 'Instagram', category: 'social', status: 'planned', description: 'Katalog senkronizasyonu', icon: Camera },
  { key: 'facebook', name: 'Facebook', category: 'social', status: 'planned', description: 'Mağaza entegrasyonu', icon: ThumbsUp },
  { key: 'tiktok', name: 'TikTok', category: 'social', status: 'planned', description: 'Ürün keşfi', icon: Video },
  { key: 'paytr', name: 'PayTR', category: 'payment', status: 'planned', description: 'Ödeme altyapısı', icon: CreditCard },
  { key: 'iyzico', name: 'iyzico', category: 'payment', status: 'planned', description: 'Ödeme altyapısı', icon: CreditCard },
  { key: 'stripe', name: 'Stripe', category: 'payment', status: 'planned', description: 'Uluslararası ödeme altyapısı', icon: CreditCard },
  { key: 'aras-kargo', name: 'Aras Kargo', category: 'shipping', status: 'planned', description: 'Kargo entegrasyonu', icon: Truck },
  { key: 'ptt-kargo', name: 'PTT Kargo', category: 'shipping', status: 'planned', description: 'Kargo entegrasyonu', icon: Truck },
  { key: 'yurtici-kargo', name: 'Yurtiçi Kargo', category: 'shipping', status: 'planned', description: 'Kargo entegrasyonu', icon: Truck },
  { key: 'marketplace', name: 'Pazaryeri Bağlantıları', category: 'marketplace', status: 'planned', description: 'Trendyol, Hepsiburada ve benzeri', icon: ShoppingBag },
  { key: 'analytics-data', name: 'Veri & Raporlama', category: 'analytics', status: 'coming-soon', description: 'Gelişmiş analitik altyapısı', icon: BarChart3 },
  { key: 'accounting', name: 'Muhasebe Entegrasyonu', category: 'accounting', status: 'planned', description: 'Fatura ve muhasebe senkronizasyonu', icon: Calculator },
  { key: 'api', name: 'API', category: 'developer', status: 'coming-soon', description: 'REST API', icon: Code2 },
  { key: 'webhooks', name: 'Webhooks', category: 'developer', status: 'coming-soon', description: 'Olay tabanlı bildirimler', icon: Webhook },
];

export const STATUS_LABEL: Record<IntegrationStatus, string> = {
  available: 'Aktif',
  beta: 'Beta',
  'coming-soon': 'Yakında',
  planned: 'Planlanıyor',
  connected: 'Bağlı',
};

export const CATEGORY_LABEL: Record<IntegrationCategory, string> = {
  social: 'Sosyal Ticaret',
  payment: 'Ödeme',
  shipping: 'Kargo',
  marketplace: 'Pazaryeri',
  marketing: 'Pazarlama',
  analytics: 'Analitik',
  developer: 'Geliştirici',
  accounting: 'Muhasebe',
};

export function getIntegration(key: string): Integration | undefined {
  return INTEGRATIONS.find((integration) => integration.key === key);
}

export function integrationsByCategory(category: IntegrationCategory): Integration[] {
  return INTEGRATIONS.filter((integration) => integration.category === category);
}
