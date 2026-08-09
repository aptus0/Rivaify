import {
  ArrowRight,
  ArrowUpRight,
  CheckCircle2,
  Heart,
  Menu,
  Package,
  Search,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  Truck,
  UserRound,
  X,
} from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { StorefrontProduct, StorefrontStore } from '../types';
import { formatMoney } from '../utils';

export type StorefrontRuntimeMode = 'published' | 'preview';

export type RuntimeSection = {
  id: string;
  type: string;
  enabled: boolean;
  locked?: boolean;
  settings: Record<string, unknown>;
  blocks?: Array<{ id: string; type: string; settings: Record<string, unknown> }>;
};

export type RuntimeDocument = {
  version: 1;
  template: string;
  sections: RuntimeSection[];
};

type RendererProps = {
  store: StorefrontStore;
  document: RuntimeDocument;
  products: StorefrontProduct[];
  mode?: StorefrontRuntimeMode;
  compact?: boolean;
  selectedSectionId?: string | null;
  onSelectSection?: (sectionId: string) => void;
  onTextChange?: (sectionId: string, field: string, value: string) => void;
  renderSectionOverlay?: (section: RuntimeSection) => ReactNode;
};

type WishlistControls = {
  ids: string[];
  count: number;
  isSaved: (productId: string) => boolean;
  toggle: (productId: string) => void;
};

const WISHLIST_KEY = 'rivaify:wishlist:v1';

const NAV_ITEMS = [
  { label: 'Kategoriler', href: '/products' },
  { label: 'Yeni Gelenler', href: '/products?sort=new' },
  { label: 'Çok Satanlar', href: '/products?sort=popular' },
  { label: 'Kampanyalar', href: '/products?tag=sale' },
];

function stringSetting(section: RuntimeSection, key: string, fallback = ''): string {
  const value = section.settings[key];
  return typeof value === 'string' && value.trim() ? value : fallback;
}

function booleanSetting(section: RuntimeSection, key: string, fallback = false): boolean {
  const value = section.settings[key];
  return typeof value === 'boolean' ? value : fallback;
}

function numberSetting(section: RuntimeSection, key: string, fallback = 0): number {
  const value = section.settings[key];
  return typeof value === 'number' ? value : fallback;
}

function objectSetting<T extends Record<string, unknown>>(section: RuntimeSection, key: string): T | undefined {
  const value = section.settings[key];
  return value && typeof value === 'object' && !Array.isArray(value) ? value as T : undefined;
}

function useWishlist(): WishlistControls {
  const [ids, setIds] = useState<string[]>([]);

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(WISHLIST_KEY);
      if (raw) setIds(JSON.parse(raw));
    } catch {
      setIds([]);
    }
  }, []);

  useEffect(() => {
    try {
      window.localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
    } catch {
      // Wishlist remains non-blocking for storefront rendering.
    }
  }, [ids]);

  return {
    ids,
    count: ids.length,
    isSaved: (productId) => ids.includes(productId),
    toggle: (productId) => setIds((current) => current.includes(productId) ? current.filter((id) => id !== productId) : [...current, productId]),
  };
}

function EditableText({
  section,
  field,
  fallback,
  className = '',
  as = 'div',
  mode,
  onTextChange,
}: {
  section: RuntimeSection;
  field: string;
  fallback: string;
  className?: string;
  as?: 'div' | 'h1' | 'h2' | 'p' | 'button' | 'span';
  mode: StorefrontRuntimeMode;
  onTextChange?: (sectionId: string, field: string, value: string) => void;
}) {
  const Element = as;
  const editable = mode === 'preview' && onTextChange;

  return (
    <Element
      data-rivaify-node-id={mode === 'preview' ? `${section.id}:${field}` : undefined}
      data-rivaify-node-type={mode === 'preview' ? 'block' : undefined}
      data-rivaify-section-id={mode === 'preview' ? section.id : undefined}
      contentEditable={Boolean(editable)}
      suppressContentEditableWarning
      className={`${className} ${editable ? 'cursor-text outline-none focus:rounded-sm focus:outline focus:outline-2 focus:outline-[#FF6B00]' : ''}`}
      onInput={(event) => editable && onTextChange?.(section.id, field, event.currentTarget.textContent ?? '')}
      onBlur={(event) => editable && onTextChange?.(section.id, field, event.currentTarget.textContent?.trim() || fallback)}
      onKeyDown={(event) => {
        if (!editable) return;
        if (event.key === 'Escape') (event.currentTarget as HTMLElement).blur();
        if (event.key === 'Enter' && as !== 'p' && as !== 'div') {
          event.preventDefault();
          (event.currentTarget as HTMLElement).blur();
        }
      }}
    >
      {stringSetting(section, field, fallback)}
    </Element>
  );
}

function ActionButton({ label, children, count, onClick, to }: { label: string; children: ReactNode; count?: number; onClick?: () => void; to?: string }) {
  const className = 'relative inline-flex h-10 w-10 items-center justify-center rounded-md text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950';
  const content = (
    <>
      {children}
      {typeof count === 'number' && count > 0 ? <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-zinc-950 px-1.5 py-0.5 text-center text-[10px] font-semibold leading-none text-white">{count}</span> : null}
    </>
  );

  return to ? <Link to={to} aria-label={label} className={className}>{content}</Link> : <button type="button" aria-label={label} onClick={onClick} className={className}>{content}</button>;
}

function SearchPanel({ open, onClose, products, currency }: { open: boolean; onClose: () => void; products: StorefrontProduct[]; currency: string }) {
  const [query, setQuery] = useState('');
  const results = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return products.slice(0, 4);

    return products.filter((product) => `${product.title} ${product.description ?? ''}`.toLowerCase().includes(normalized)).slice(0, 6);
  }, [products, query]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 bg-zinc-950/35 p-3 sm:p-6" role="dialog" aria-modal="true">
      <div className="mx-auto max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl">
        <div className="flex items-center gap-3 border-b border-zinc-200 px-4 py-3">
          <Search className="h-5 w-5 text-zinc-500" />
          <input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Ürün, kategori veya marka ara..." className="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none" />
          <button type="button" className="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-950" onClick={onClose} aria-label="Aramayı kapat"><X className="h-4 w-4" /></button>
        </div>
        <div className="max-h-[70vh] overflow-y-auto p-3">
          <p className="px-2 py-2 text-xs font-semibold uppercase tracking-[.16em] text-zinc-500">Ürünler</p>
          {results.length === 0 ? <div className="rounded-md bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">Sonuç bulunamadı.</div> : null}
          {results.map((product) => {
            const media = product.media.find((item) => item.is_featured) ?? product.media[0];
            const price = product.variants[0]?.price;
            return (
              <Link key={product.id} to={`/products/${product.slug}`} onClick={onClose} className="flex items-center gap-3 rounded-md p-2 hover:bg-zinc-50">
                <div className="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-md bg-zinc-100 text-zinc-400">
                  {media ? <img src={media.url} alt={media.alt_text ?? product.title} className="h-full w-full object-cover" /> : <Package className="h-5 w-5" />}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-zinc-950">{product.title}</p>
                  <p className="mt-1 text-xs text-zinc-500">{price ? formatMoney(price, currency) : 'Fiyat yakında'}</p>
                </div>
                <ArrowUpRight className="h-4 w-4 text-zinc-400" />
              </Link>
            );
          })}
          <Link to="/products" onClick={onClose} className="mt-3 flex items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 py-3 text-sm font-semibold text-white">Tüm sonuçları gör <ArrowRight className="h-4 w-4" /></Link>
        </div>
      </div>
    </div>
  );
}

function MobileMenu({ open, onClose, store, wishlistCount }: { open: boolean; onClose: () => void; store: StorefrontStore; wishlistCount: number }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 bg-zinc-950/35 md:hidden" role="dialog" aria-modal="true">
      <aside className="flex h-full w-[88vw] max-w-sm flex-col bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
          <Link to="/" onClick={onClose} className="font-semibold text-zinc-950">{store.name}</Link>
          <button type="button" className="rounded-md p-2 text-zinc-500 hover:bg-zinc-100" onClick={onClose} aria-label="Menüyü kapat"><X className="h-4 w-4" /></button>
        </div>
        <div className="p-4">
          <div className="flex items-center gap-2 rounded-md bg-zinc-100 px-3 py-2 text-sm text-zinc-500"><Search className="h-4 w-4" /> Ürün veya kategori ara</div>
        </div>
        <nav className="grid gap-1 px-3" aria-label="Mobil navigasyon">
          {NAV_ITEMS.map((item) => <Link key={item.label} to={item.href} onClick={onClose} className="flex items-center justify-between rounded-md px-3 py-3 text-sm font-medium text-zinc-900 hover:bg-zinc-50">{item.label}<ArrowRight className="h-4 w-4 text-zinc-400" /></Link>)}
        </nav>
        <div className="mt-auto border-t border-zinc-200 p-3">
          <Link to="/wishlist" onClick={onClose} className="flex items-center justify-between rounded-md px-3 py-3 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Favoriler <span className="text-xs text-zinc-500">{wishlistCount}</span></Link>
          <Link to="/cart" onClick={onClose} className="flex items-center justify-between rounded-md px-3 py-3 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Sepet <ShoppingBag className="h-4 w-4 text-zinc-500" /></Link>
        </div>
      </aside>
    </div>
  );
}

function HeaderSection({ section, store, products, wishlist, mode }: { section: RuntimeSection; store: StorefrontStore; products: StorefrontProduct[]; wishlist: WishlistControls; mode: StorefrontRuntimeMode }) {
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const preset = stringSetting(section, 'preset', 'classic');
  const sticky = booleanSetting(section, 'sticky', true);
  const showSearch = booleanSetting(section, 'show_search', true);
  const showWishlist = booleanSetting(section, 'show_wishlist', true);
  const showAccount = booleanSetting(section, 'show_account', true);
  const showCart = booleanSetting(section, 'show_cart', true);
  const logoBlock = section.blocks?.find((block) => block.type === 'logo');
  const logoText = typeof logoBlock?.settings.text === 'string' ? logoBlock.settings.text : store.name;
  const centered = preset === 'centered' || preset === 'luxury';
  const searchFocused = preset === 'search-focused' || preset === 'retail';
  const minimal = preset === 'minimal';

  return (
    <>
      <header data-rivaify-node-type={mode === 'preview' ? 'global' : undefined} className={`${sticky ? 'sticky top-0' : ''} z-40 border-b border-zinc-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85`}>
        <div className={`mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 ${centered ? 'justify-between md:grid md:grid-cols-3' : 'justify-between'}`}>
          <div className="flex items-center gap-2">
            <button type="button" className="rounded-md p-2 text-zinc-700 hover:bg-zinc-100 md:hidden" onClick={() => setMobileOpen(true)} aria-label="Menüyü aç"><Menu className="h-5 w-5" /></button>
            {!centered || minimal ? (
              <Link to="/" className="flex min-w-0 items-center gap-3">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-zinc-950 text-sm font-bold text-white">{logoText.slice(0, 1).toUpperCase()}</span>
                <span className="truncate text-base font-semibold tracking-normal text-zinc-950">{logoText}</span>
              </Link>
            ) : <div className="hidden md:block" />}
          </div>

          {centered ? (
            <Link to="/" className="hidden justify-self-center text-center text-lg font-semibold text-zinc-950 md:block">{logoText}</Link>
          ) : null}

          {!minimal && !searchFocused ? (
            <nav className="hidden items-center gap-5 text-sm font-medium text-zinc-700 md:flex" aria-label="Ana navigasyon">
              {NAV_ITEMS.map((item) => <Link key={item.label} to={item.href} className="hover:text-zinc-950">{item.label}</Link>)}
            </nav>
          ) : null}

          {searchFocused ? (
            <button type="button" onClick={() => setSearchOpen(true)} className="hidden min-w-0 flex-1 items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-left text-sm text-zinc-500 transition hover:border-zinc-300 md:flex">
              <Search className="h-4 w-4" /> Ürün, kategori veya marka ara...
            </button>
          ) : null}

          <div className="flex items-center justify-end gap-1">
            {showSearch ? <ActionButton label="Ara" onClick={() => setSearchOpen(true)}><Search className="h-5 w-5" /></ActionButton> : null}
            {showWishlist ? <ActionButton label="Favoriler" count={wishlist.count} to="/wishlist"><Heart className="h-5 w-5" /></ActionButton> : null}
            {showAccount ? <ActionButton label="Hesap" to="/login"><UserRound className="h-5 w-5" /></ActionButton> : null}
            {showCart ? <ActionButton label="Sepet" to="/cart"><ShoppingBag className="h-5 w-5" /></ActionButton> : null}
          </div>
        </div>
        {searchFocused || preset === 'mega-commerce' ? (
          <nav className="hidden border-t border-zinc-100 px-6 py-2 text-sm font-medium text-zinc-700 md:block" aria-label="Kategori navigasyonu">
            <div className="mx-auto flex max-w-7xl gap-6">
              {NAV_ITEMS.map((item) => <Link key={item.label} to={item.href} className="hover:text-zinc-950">{item.label}</Link>)}
            </div>
          </nav>
        ) : null}
      </header>
      <SearchPanel open={searchOpen} onClose={() => setSearchOpen(false)} products={products} currency={store.currency} />
      <MobileMenu open={mobileOpen} onClose={() => setMobileOpen(false)} store={store} wishlistCount={wishlist.count} />
    </>
  );
}

function AnnouncementSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  return (
    <div className="bg-zinc-950 px-4 py-2.5 text-center text-sm font-medium text-white">
      <EditableText section={section} field="text" fallback="1.500 TL üzeri ücretsiz kargo" mode={mode} onTextChange={onTextChange} as="span" />
    </div>
  );
}

function HeroSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  const alignment = stringSetting(section, 'alignment', 'center');
  const height = stringSetting(section, 'height', 'large');
  const alignClass = alignment === 'left' ? 'items-start text-left' : alignment === 'right' ? 'items-end text-right' : 'items-center text-center';
  const overlay = numberSetting(section, 'overlay', 10);
  const minHeight = height === 'small' ? 'min-h-[360px]' : height === 'medium' ? 'min-h-[480px]' : 'min-h-[620px]';

  return (
    <section className={`relative flex ${minHeight} flex-col justify-center overflow-hidden bg-zinc-950 px-5 py-16 text-white sm:px-7 ${alignClass}`}>
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,.18),transparent_28%),linear-gradient(135deg,#0f172a,#0f766e_48%,#d97706)]" />
      <div className="absolute inset-0 bg-black" style={{ opacity: overlay / 100 }} />
      <div className="relative mx-auto w-full max-w-7xl">
        <div className="max-w-2xl">
          <EditableText section={section} field="eyebrow" fallback="Rivaify Nova" as="p" mode={mode} onTextChange={onTextChange} className="mb-4 text-xs font-semibold uppercase tracking-[.22em] text-white/75" />
          <EditableText section={section} field="title" fallback="Mağazanız bugün premium görünsün" as="h1" mode={mode} onTextChange={onTextChange} className="text-4xl font-semibold leading-tight md:text-6xl" />
          <EditableText section={section} field="subtitle" fallback="Profesyonel vitrin, hızlı alışveriş deneyimi ve aynı builder/public renderer." as="p" mode={mode} onTextChange={onTextChange} className="mt-5 max-w-xl text-base leading-7 text-white/82 md:text-lg" />
          <div className={`mt-8 flex flex-wrap gap-3 ${alignment === 'center' ? 'justify-center' : alignment === 'right' ? 'justify-end' : ''}`}>
            <Link to="/products" className="inline-flex items-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-zinc-100">
              <EditableText section={section} field="button_label" fallback="Ürünleri Keşfet" as="span" mode={mode} onTextChange={onTextChange} />
              <ArrowRight className="h-4 w-4" />
            </Link>
            <Link to="/products?sort=new" className="inline-flex items-center gap-2 rounded-md border border-white/30 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Yeni gelenler</Link>
          </div>
        </div>
      </div>
    </section>
  );
}

export function ProductCard({ product, currency, wishlist }: { product: StorefrontProduct; currency: string; wishlist: WishlistControls }) {
  const startingVariant = product.variants[0];
  const featuredMedia = product.media.find((item) => item.is_featured) ?? product.media[0];
  const secondMedia = product.media.find((item) => item.id !== featuredMedia?.id);
  const compare = startingVariant?.compare_at_price;
  const price = startingVariant?.price;
  const onSale = Boolean(compare && price && Number(compare) > Number(price));
  const saved = wishlist.isSaved(product.id);

  return (
    <article className="group min-w-0">
      <div className="relative overflow-hidden rounded-md bg-zinc-100">
        <Link to={`/products/${product.slug}`} className="block aspect-[4/5]">
          {featuredMedia ? <img src={featuredMedia.url} alt={featuredMedia.alt_text ?? product.title} loading="lazy" className={`h-full w-full object-cover transition duration-500 ${secondMedia ? 'group-hover:opacity-0' : 'group-hover:scale-[1.035]'}`} /> : <div className="grid h-full w-full place-items-center text-zinc-400"><Package size={42} strokeWidth={1.2} /></div>}
          {secondMedia ? <img src={secondMedia.url} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover opacity-0 transition duration-500 group-hover:scale-[1.035] group-hover:opacity-100" /> : null}
        </Link>
        <button
          type="button"
          aria-label={saved ? 'Favorilerden çıkar' : 'Favorilere ekle'}
          className={`absolute right-3 top-3 grid h-10 w-10 place-items-center rounded-full shadow-sm transition ${saved ? 'bg-zinc-950 text-white' : 'bg-white/92 text-zinc-700 hover:bg-white hover:text-zinc-950'}`}
          onClick={(event) => {
            event.preventDefault();
            wishlist.toggle(product.id);
          }}
        >
          <Heart className={`h-5 w-5 ${saved ? 'fill-current' : ''}`} />
        </button>
        {onSale ? <span className="absolute left-3 top-3 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-zinc-950 shadow-sm">İndirim</span> : null}
        <div className="absolute inset-x-3 bottom-3 translate-y-2 opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 max-md:translate-y-0 max-md:opacity-100">
          <Link to={`/products/${product.slug}`} className="flex min-h-11 items-center justify-center rounded-md bg-zinc-950 px-4 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Hızlı İncele</Link>
        </div>
      </div>
      <div className="mt-4">
        <Link to={`/products/${product.slug}`} className="block">
          <p className="text-xs font-semibold uppercase tracking-[.14em] text-zinc-500">Nova Selection</p>
          <h2 className="mt-1 line-clamp-2 font-medium text-zinc-950">{product.title}</h2>
        </Link>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <p className="text-sm font-semibold text-zinc-950">{price ? formatMoney(price, currency) : 'Fiyat yakında'}</p>
          {onSale && compare ? <p className="text-sm text-zinc-400 line-through">{formatMoney(compare, currency)}</p> : null}
        </div>
      </div>
    </article>
  );
}

function ProductGridSection({ section, store, products, mode, onTextChange, wishlist }: { section: RuntimeSection; store: StorefrontStore; products: StorefrontProduct[]; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange']; wishlist: WishlistControls }) {
  const columns = objectSetting<{ desktop?: number; tablet?: number; mobile?: number }>(section, 'columns');
  const desktop = Math.min(Math.max(2, columns?.desktop ?? 4), 5);
  const desktopClass = desktop === 2 ? 'lg:grid-cols-2' : desktop === 3 ? 'lg:grid-cols-3' : desktop === 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4';
  const visibleProducts = products.slice(0, Number(section.settings.limit ?? 8));
  const source = objectSetting<{ type?: string; label?: string }>(section, 'source');

  return (
    <section className="bg-white px-5 py-14 sm:px-7">
      <div className="mx-auto max-w-7xl">
        <div className="mb-7 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <EditableText section={section} field="title" fallback="Yeni Gelenler" as="h2" mode={mode} onTextChange={onTextChange} className="text-2xl font-semibold text-zinc-950 md:text-3xl" />
            <EditableText section={section} field="description" fallback="Mağazanızdaki aktif ürünler otomatik olarak burada görünür." as="p" mode={mode} onTextChange={onTextChange} className="mt-2 max-w-xl text-sm leading-6 text-zinc-500" />
          </div>
          <Link to="/products" className="inline-flex items-center gap-2 text-sm font-semibold text-zinc-950 hover:text-zinc-600">{source?.type === 'collection' ? source.label ?? 'Koleksiyon' : 'Tümünü gör'} <ArrowRight className="h-4 w-4" /></Link>
        </div>
        {visibleProducts.length === 0 ? (
          <div className="grid min-h-60 place-items-center rounded-md border border-dashed border-zinc-300 bg-zinc-50 text-center">
            <div><Package size={28} className="mx-auto text-zinc-400" /><p className="mt-3 text-sm font-medium text-zinc-700">Henüz satışta ürün yok.</p><p className="mt-1 text-xs text-zinc-500">Aktif ürün ve aktif variant eklenince burada görünür.</p></div>
          </div>
        ) : (
          <div className={`grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:gap-x-6 ${desktopClass}`}>
            {visibleProducts.map((product) => <ProductCard key={product.id} product={product} currency={store.currency} wishlist={wishlist} />)}
          </div>
        )}
      </div>
    </section>
  );
}

function CategoryShowcaseSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  const labels = ['Kadın', 'Erkek', 'Ayakkabı', 'Aksesuar', 'Kozmetik', 'Kampanyalar'];

  return (
    <section className="bg-zinc-50 px-5 py-12 sm:px-7">
      <div className="mx-auto max-w-7xl">
        <div className="mb-6 flex items-end justify-between gap-3">
          <EditableText section={section} field="title" fallback="Kategorileri keşfet" as="h2" mode={mode} onTextChange={onTextChange} className="text-2xl font-semibold text-zinc-950" />
          <Link to="/products" className="text-sm font-semibold text-zinc-700">Tüm kategoriler</Link>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {labels.map((label) => (
            <Link key={label} to={`/products?category=${encodeURIComponent(label.toLowerCase())}`} className="group rounded-md border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-sm">
              <div className="grid aspect-square place-items-center rounded-md bg-zinc-100 text-zinc-500 transition group-hover:bg-zinc-950 group-hover:text-white"><Sparkles className="h-6 w-6" /></div>
              <p className="mt-3 text-sm font-semibold text-zinc-950">{label}</p>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}

function ImageTextSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  const imagePosition = stringSetting(section, 'image_position', 'left');
  return (
    <section className="bg-white px-5 py-16 sm:px-7">
      <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-2">
        <div className={`min-h-[360px] rounded-md bg-[linear-gradient(135deg,#f8fafc,#0f766e)] ${imagePosition === 'right' ? 'md:order-2' : ''}`} />
        <div className="flex flex-col justify-center">
          <EditableText section={section} field="title" fallback="Günlük stile premium dokunuş" as="h2" mode={mode} onTextChange={onTextChange} className="text-3xl font-semibold leading-tight text-zinc-950 md:text-4xl" />
          <EditableText section={section} field="body" fallback="Markanızın hikayesini sade, güçlü ve mobil uyumlu bir anlatımla gösterin." as="p" mode={mode} onTextChange={onTextChange} className="mt-4 max-w-xl text-base leading-7 text-zinc-600" />
          <Link to="/products" className="mt-7 inline-flex w-fit items-center gap-2 rounded-md bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Alışverişe başla <ArrowRight className="h-4 w-4" /></Link>
        </div>
      </div>
    </section>
  );
}

function TrustBenefitsSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  const items = [
    { Icon: Truck, title: 'Ücretsiz Kargo', body: 'Belirlenen sepet tutarında hızlı teslimat.' },
    { Icon: CheckCircle2, title: 'Kolay İade', body: 'Sipariş sonrası net ve sade iade deneyimi.' },
    { Icon: ShieldCheck, title: 'Güvenli Ödeme', body: 'Ödeme akışı Rivaify güvencesiyle korunur.' },
  ];

  return (
    <section className="bg-zinc-950 px-5 py-12 text-white sm:px-7">
      <div className="mx-auto max-w-7xl">
        <EditableText section={section} field="title" fallback="Güven veren alışveriş" as="h2" mode={mode} onTextChange={onTextChange} className="text-2xl font-semibold" />
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {items.map(({ Icon, title, body }) => (
            <div key={title} className="rounded-md border border-white/10 bg-white/[.04] p-5">
              <Icon className="h-6 w-6 text-white" />
              <p className="mt-4 font-semibold">{title}</p>
              <p className="mt-2 text-sm leading-6 text-white/65">{body}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function NewsletterSection({ section, mode, onTextChange }: { section: RuntimeSection; mode: StorefrontRuntimeMode; onTextChange?: RendererProps['onTextChange'] }) {
  return (
    <section className="bg-zinc-100 px-5 py-14 sm:px-7">
      <div className="mx-auto flex max-w-4xl flex-col items-center text-center">
        <EditableText section={section} field="title" fallback="Yeni koleksiyonları kaçırma" as="h2" mode={mode} onTextChange={onTextChange} className="text-2xl font-semibold text-zinc-950 md:text-3xl" />
        <EditableText section={section} field="description" fallback="Kampanya ve yeni ürün haberlerini ilk öğrenenlerden olun." as="p" mode={mode} onTextChange={onTextChange} className="mt-3 max-w-xl text-sm leading-6 text-zinc-600" />
        <form className="mt-6 flex w-full max-w-md gap-2" onSubmit={(event) => event.preventDefault()}>
          <input type="email" placeholder="E-posta adresiniz" className="min-w-0 flex-1 rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm outline-none focus:border-zinc-950" />
          <button className="rounded-md bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Kaydol</button>
        </form>
      </div>
    </section>
  );
}

function FooterSection({ section, store }: { section: RuntimeSection; store: StorefrontStore }) {
  const preset = stringSetting(section, 'preset', 'large-commerce');

  return (
    <footer className="border-t border-zinc-200 bg-white">
      <div className={`mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-7 ${preset === 'minimal' ? 'md:grid-cols-1' : 'md:grid-cols-[1.4fr_1fr_1fr_1.2fr]'}`}>
        <div>
          <Link to="/" className="inline-flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-md bg-zinc-950 text-sm font-bold text-white">{store.name.slice(0, 1).toUpperCase()}</span>
            <span className="text-lg font-semibold text-zinc-950">{store.name}</span>
          </Link>
          <p className="mt-4 max-w-sm text-sm leading-6 text-zinc-600">Rivaify Nova ile hızlı, güvenli ve premium alışveriş deneyimi.</p>
        </div>
        {['Alışveriş', 'Yardım'].map((title) => (
          <nav key={title} className="grid content-start gap-3 text-sm" aria-label={title}>
            <p className="font-semibold text-zinc-950">{title}</p>
            {NAV_ITEMS.slice(0, 4).map((item) => <Link key={item.label} to={item.href} className="text-zinc-600 hover:text-zinc-950">{item.label}</Link>)}
          </nav>
        ))}
        <div>
          <p className="font-semibold text-zinc-950">Bülten</p>
          <p className="mt-3 text-sm leading-6 text-zinc-600">Yeni ürünleri ve kampanyaları kaçırmayın.</p>
          <form className="mt-4 flex gap-2" onSubmit={(event) => event.preventDefault()}>
            <input type="email" placeholder="E-posta" className="min-w-0 flex-1 rounded-md border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-950" />
            <button className="rounded-md bg-zinc-950 px-3 py-2 text-sm font-semibold text-white">OK</button>
          </form>
        </div>
      </div>
      <div className="border-t border-zinc-100 px-5 py-4 text-xs text-zinc-500 sm:px-7">
        <div className="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <span>© {new Date().getFullYear()} {store.name}</span>
          <span>Güvenli ödeme · Kolay iade · Rivaify Commerce</span>
        </div>
      </div>
    </footer>
  );
}

function SectionRenderer(props: RendererProps & { section: RuntimeSection; wishlist: WishlistControls }) {
  const { section, store, products, mode = 'published', onTextChange, wishlist } = props;

  if (section.type === 'header') return <HeaderSection section={section} store={store} products={products} wishlist={wishlist} mode={mode} />;
  if (section.type === 'announcement') return <AnnouncementSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'hero') return <HeroSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'category-showcase' || section.type === 'collection-grid' || section.type === 'collection-carousel') return <CategoryShowcaseSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'product-grid' || section.type === 'product-carousel') return <ProductGridSection section={section} store={store} products={products} mode={mode} onTextChange={onTextChange} wishlist={wishlist} />;
  if (section.type === 'image-with-text' || section.type === 'image-banner' || section.type === 'promotion-tiles') return <ImageTextSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'trust-badges') return <TrustBenefitsSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'newsletter') return <NewsletterSection section={section} mode={mode} onTextChange={onTextChange} />;
  if (section.type === 'spacer') return <div className="h-16 bg-zinc-50" />;
  if (section.type === 'footer') return <FooterSection section={section} store={store} />;

  return (
    <section className="border-y border-zinc-200 bg-white px-5 py-12 sm:px-7">
      <div className="mx-auto max-w-7xl">
        <EditableText section={section} field="title" fallback={section.type} as="h2" mode={mode} onTextChange={onTextChange} className="text-2xl font-semibold text-zinc-950" />
      </div>
    </section>
  );
}

export function StorefrontRenderer(props: RendererProps) {
  const { document, mode = 'published', compact = false, selectedSectionId, onSelectSection, renderSectionOverlay } = props;
  const wishlist = useWishlist();

  return (
    <div className={`${compact ? '' : 'min-h-screen'} bg-white text-zinc-950 antialiased`}>
      {document.sections.filter((section) => section.enabled).map((section) => (
        <div
          key={section.id}
          data-rivaify-node-id={mode === 'preview' ? section.id : undefined}
          data-rivaify-section-id={mode === 'preview' ? section.id : undefined}
          data-rivaify-node-type={mode === 'preview' ? 'section' : undefined}
          className={`relative ${mode === 'preview' ? 'group' : ''}`}
          onClick={(event) => {
            if (mode !== 'preview') return;
            event.stopPropagation();
            onSelectSection?.(section.id);
          }}
        >
          {mode === 'preview' && selectedSectionId === section.id ? <div className="pointer-events-none absolute inset-0 z-20 outline outline-2 outline-[#FF6B00]" /> : null}
          {mode === 'preview' && selectedSectionId !== section.id ? <div className="pointer-events-none absolute inset-0 z-10 opacity-0 outline outline-1 outline-[#FF6B00]/60 group-hover:opacity-100" /> : null}
          <SectionRenderer {...props} section={section} wishlist={wishlist} />
          {mode === 'preview' ? renderSectionOverlay?.(section) : null}
        </div>
      ))}
    </div>
  );
}
