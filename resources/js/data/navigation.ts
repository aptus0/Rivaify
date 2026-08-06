export const BRAND = {
  name: 'Rivaify',
  tagline: 'Yeni Nesil E-Ticaret Platformu',
} as const;

/** The merchant dashboard SPA lives on its own subdomain (routes/web.php,
 * Route::domain('app.rivaify.com')) — auth CTAs cross to it by absolute
 * URL, not a client-side/Inertia route. */
export const APP_URL = 'https://app.rivaify.com';
export const LOGIN_URL = `${APP_URL}/login`;
export const REGISTER_URL = `${APP_URL}/register`;

export const CTA = {
  primary: 'Mağazanı Oluştur',
  secondary: 'Platformu Keşfet',
  developer: 'Dokümantasyonu Gör',
  integrations: 'Entegrasyonları Keşfet',
} as const;

export interface MegaMenuLinkItem {
  label: string;
  description: string;
  href: string;
}

export interface MegaMenuColumn {
  title: string;
  items: MegaMenuLinkItem[];
}

export const PLATFORM_MENU: MegaMenuColumn[] = [
  {
    title: 'Commerce Yönetimi',
    items: [
      { label: 'Ürünler', description: 'Ürün, varyant ve kategori yönetimi', href: '/platform' },
      { label: 'Siparişler', description: 'Siparişleri tek ekrandan takip et', href: '/platform' },
      { label: 'Stok', description: 'Depo bazlı stok ve hareket takibi', href: '/platform' },
      { label: 'Müşteriler', description: 'Müşteri profilleri ve segmentler', href: '/platform' },
    ],
  },
  {
    title: 'Storefront',
    items: [
      { label: 'Tema Editörü', description: 'Profesyonel temalarla kişiselleştir', href: '/themes' },
      { label: 'Sayfa Oluşturucu', description: 'Sürükle-bırak sayfa tasarımı', href: '/store-builder' },
      { label: 'Checkout', description: 'Markana özel ödeme deneyimi', href: '/checkout' },
      { label: 'Domain', description: 'Kendi alan adını mağazana bağla', href: '/online-store' },
    ],
  },
  {
    title: 'Growth',
    items: [
      { label: 'Analitik', description: 'Satış ve müşteri verilerini analiz et', href: '/analytics' },
      { label: 'Sosyal Ticaret', description: 'Instagram, Facebook ve TikTok', href: '/social-commerce' },
      { label: 'Entegrasyonlar', description: 'Ödeme, kargo ve pazaryeri', href: '/integrations' },
    ],
  },
  {
    title: 'Developer',
    items: [
      { label: 'API', description: 'REST API üzerinden entegre ol', href: '/developers' },
      { label: 'Webhooks', description: 'Olay tabanlı bildirimler', href: '/developers' },
      { label: 'Apps', description: 'Uygulama ekosistemi', href: '/developers' },
    ],
  },
];

export interface SolutionItem {
  label: string;
  description: string;
  href: string;
}

export const SOLUTIONS_MENU: SolutionItem[] = [
  { label: 'Moda', description: 'Varyant, koleksiyon ve lookbook odaklı satış', href: '/solutions' },
  { label: 'Perakende', description: 'Çok kanallı stok ve mağaza yönetimi', href: '/solutions' },
  { label: 'Kozmetik', description: 'Set ve varyant yönetimiyle güzellik satışı', href: '/solutions' },
  { label: 'Elektronik', description: 'Teknik özellik ve garanti takibi', href: '/solutions' },
  { label: 'Dijital Ürün', description: 'Kargosuz, anlık teslim satış akışı', href: '/solutions' },
  { label: 'Sosyal Satış', description: 'Sosyal kanallardan doğrudan satış', href: '/social-commerce' },
];

export const NAV_LINKS = [
  { label: 'Online Mağaza', href: '/online-store' },
  { label: 'Kaynaklar', href: '/developers' },
  { label: 'Fiyatlandırma', href: '/pricing' },
] as const;

export const FOOTER_COLUMNS = [
  {
    title: 'Platform',
    links: [
      { label: 'Online Mağaza', href: '/online-store' },
      { label: 'Store Builder', href: '/store-builder' },
      { label: 'Checkout', href: '/checkout' },
      { label: 'Analitik', href: '/analytics' },
      { label: 'Sosyal Ticaret', href: '/social-commerce' },
      { label: 'Entegrasyonlar', href: '/integrations' },
    ],
  },
  {
    title: 'Çözümler',
    links: [
      { label: 'Perakende', href: '/solutions' },
      { label: 'Moda', href: '/solutions' },
      { label: 'Dijital Ürün', href: '/solutions' },
    ],
  },
  {
    title: 'Kaynaklar',
    links: [
      { label: 'Developers', href: '/developers' },
      { label: 'Dokümantasyon', href: '/developers' },
    ],
  },
] as const;
