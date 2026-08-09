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
} as const;

/** Trimmed 2026-08-09 to the 4 pages the site actually has (Home is
 * implicit via the logo). No more mega menus / SOLUTIONS_MENU /
 * PLATFORM_MENU — there's nothing left to sub-navigate to. */
export const NAV_LINKS = [
  { label: 'Platform', href: '/platform' },
  { label: 'Store Builder', href: '/store-builder' },
  { label: 'Fiyatlandırma', href: '/pricing' },
] as const;

export const FOOTER_COLUMNS = [
  {
    title: 'Platform',
    links: [
      { label: 'Platform', href: '/platform' },
      { label: 'Store Builder', href: '/store-builder' },
      { label: 'Fiyatlandırma', href: '/pricing' },
    ],
  },
] as const;
