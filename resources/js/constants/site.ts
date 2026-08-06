export const BRAND = {
  name: 'Rivaify',
  tagline: 'Yeni Nesil E-Ticaret Platformu',
} as const;

/** The merchant dashboard SPA lives on its own subdomain (see routes/web.php,
 * Route::domain('app.rivaify.com')) — auth CTAs on the marketing site must
 * cross to it by absolute URL, not a client-side route. */
export const APP_URL = 'https://app.rivaify.com';
export const LOGIN_URL = `${APP_URL}/login`;
export const REGISTER_URL = `${APP_URL}/register`;

export interface MegaMenuItem {
  label: string;
  description: string;
  href: string;
  available: boolean;
}

export interface MegaMenuColumn {
  title: string;
  items: MegaMenuItem[];
}

export const PLATFORM_MENU: MegaMenuColumn[] = [
  {
    title: 'Commerce',
    items: [
      { label: 'Ürün Yönetimi', description: 'Ürün, varyant ve kategori yönetimi', href: '#kontrol-merkezi', available: true },
      { label: 'Sipariş Yönetimi', description: 'Siparişleri tek ekrandan takip et', href: '#kontrol-merkezi', available: true },
      { label: 'Müşteri Yönetimi', description: 'Müşteri profilleri ve segmentler', href: '#kontrol-merkezi', available: true },
      { label: 'Stok Yönetimi', description: 'Depo bazlı stok ve hareket takibi', href: '#kontrol-merkezi', available: true },
    ],
  },
  {
    title: 'Storefront',
    items: [
      { label: 'Tema Editörü', description: 'Profesyonel temalarla mağazanı kişiselleştir', href: '#temalar', available: true },
      { label: 'Sayfa Oluşturucu', description: 'Kod yazmadan sürükle-bırak sayfa tasarımı', href: '#magaza-olusturucu', available: true },
      { label: 'Checkout', description: 'Markana özel ödeme deneyimi', href: '#checkout', available: true },
      { label: 'Domain', description: 'Kendi alan adını mağazana bağla', href: '#domain', available: true },
    ],
  },
  {
    title: 'Growth',
    items: [
      { label: 'Analitik', description: 'Satış ve müşteri verilerini analiz et', href: '#analitik', available: true },
      { label: 'Sosyal Ticaret', description: 'Instagram, Facebook ve TikTok kanalları', href: '#sosyal-ticaret', available: true },
      { label: 'Entegrasyonlar', description: 'Ödeme, kargo ve pazaryeri bağlantıları', href: '#entegrasyonlar', available: true },
    ],
  },
];

export const NAV_LINKS = [
  { label: 'Çözümler', href: '#kontrol-merkezi' },
  { label: 'Online Mağaza', href: '#magaza-olusturucu' },
  { label: 'Entegrasyonlar', href: '#entegrasyonlar' },
  { label: 'Temalar', href: '#temalar' },
] as const;
