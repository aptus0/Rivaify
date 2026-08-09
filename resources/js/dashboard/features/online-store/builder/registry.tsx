import {
  BadgeCheck,
  Bell,
  Blocks,
  Clock3,
  Columns3,
  GalleryHorizontal,
  Grid2X2,
  Heading1,
  Image,
  ImagePlus,
  Camera,
  LayoutTemplate,
  ListChecks,
  Mail,
  MessageSquareQuote,
  Minus,
  PanelTop,
  PlaySquare,
  Rows3,
  ShoppingBag,
  Text,
  Tags,
} from 'lucide-react';
import type { ComponentType } from 'react';
import type { BuilderSection, SectionType } from '../types';

type SectionPreviewProps = {
  section: BuilderSection;
};

export type SectionDefinition = {
  type: SectionType;
  label: string;
  category: 'Temel' | 'Ürünler' | 'Koleksiyonlar' | 'İçerik' | 'Pazarlama' | 'Sosyal';
  icon: ComponentType<{ className?: string }>;
  defaults: BuilderSection;
  allowedBlocks?: string[];
  preview: ComponentType<SectionPreviewProps>;
};

function text(value: unknown, fallback: string): string {
  return typeof value === 'string' && value.trim() ? value : fallback;
}

function numberValue(value: unknown, fallback: number): number {
  return typeof value === 'number' ? value : fallback;
}

function HeaderPreview({ section }: SectionPreviewProps) {
  const logoBlock = section.blocks?.find((block) => block.type === 'logo');
  return (
    <header className="flex items-center justify-between border-b border-zinc-200 bg-white px-8 py-5">
      <div className="text-lg font-semibold text-zinc-950">{text(logoBlock?.settings.text, 'Yasemin Giyim')}</div>
      <nav className="hidden items-center gap-6 text-sm text-zinc-600 md:flex"><span>Yeni Gelenler</span><span>Koleksiyonlar</span><span>İletişim</span></nav>
      <div className="flex items-center gap-3 text-sm text-zinc-500"><span>Ara</span><ShoppingBag className="h-4 w-4" /></div>
    </header>
  );
}

function AnnouncementPreview({ section }: SectionPreviewProps) {
  return <div className="bg-teal-700 px-5 py-2 text-center text-sm font-medium text-white">{text(section.settings.text, 'Ücretsiz kargo')}</div>;
}

function HeroPreview({ section }: SectionPreviewProps) {
  const alignment = text(section.settings.alignment, 'center');
  const alignClass = alignment === 'left' ? 'items-start text-left' : alignment === 'right' ? 'items-end text-right' : 'items-center text-center';
  const overlay = numberValue(section.settings.overlay, 8);
  return (
    <section className={`relative flex min-h-[430px] flex-col justify-center overflow-hidden bg-zinc-950 px-8 py-16 text-white ${alignClass}`}>
      <div className="absolute inset-0 bg-[linear-gradient(135deg,#0f766e,#111827_48%,#eab308)]" />
      <div className="absolute inset-0 bg-black" style={{ opacity: overlay / 100 }} />
      <div className="relative max-w-2xl">
        <p className="mb-3 text-xs font-semibold uppercase tracking-[.2em] text-teal-100">Nova</p>
        <h1 className="text-4xl font-semibold leading-tight md:text-6xl">{text(section.settings.title, 'Yaz Koleksiyonu')}</h1>
        <p className="mt-4 text-base text-zinc-100 md:text-lg">{text(section.settings.subtitle, 'Yeni sezonu keşfet')}</p>
        <button className="mt-7 rounded-md bg-white px-5 py-3 text-sm font-semibold text-zinc-950">{text(section.settings.button_label, 'Şimdi Keşfet')}</button>
      </div>
    </section>
  );
}

function ProductGridPreview({ section }: SectionPreviewProps) {
  const columns = section.settings.columns as { desktop?: number } | undefined;
  const count = Math.max(2, columns?.desktop ?? 4);
  return (
    <section className="bg-white px-8 py-12">
      <div className="mb-6 flex items-end justify-between">
        <h2 className="text-2xl font-semibold text-zinc-950">{text(section.settings.title, 'Çok Satanlar')}</h2>
        <span className="text-sm text-zinc-500">Storefront Catalog</span>
      </div>
      <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${Math.min(count, 4)}, minmax(0, 1fr))` }}>
        {Array.from({ length: Math.min(count, 4) }).map((_, index) => (
          <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white" key={index}>
            <div className="aspect-[4/5] bg-zinc-100" />
            <div className="p-4">
              <div className="h-4 w-3/4 rounded bg-zinc-200" />
              <div className="mt-2 h-3 w-1/3 rounded bg-zinc-100" />
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

function TextMediaPreview({ section }: SectionPreviewProps) {
  return (
    <section className="grid gap-8 bg-zinc-50 px-8 py-12 md:grid-cols-2">
      <div className="aspect-[4/3] rounded-lg bg-[linear-gradient(135deg,#f8fafc,#0f766e)]" />
      <div className="flex flex-col justify-center">
        <h2 className="text-3xl font-semibold text-zinc-950">{text(section.settings.title, 'Markanı anlat')}</h2>
        <p className="mt-4 text-zinc-600">{text(section.settings.body, 'Zengin içerik alanı, güvenli builder schema ile render edilir.')}</p>
      </div>
    </section>
  );
}

function SimplePreview({ section }: SectionPreviewProps) {
  return <section className="border-y border-zinc-200 bg-white px-8 py-12"><h2 className="text-2xl font-semibold text-zinc-950">{text(section.settings.title, section.type)}</h2><p className="mt-2 text-sm text-zinc-500">Bu bölüm registry üzerinden güvenli şekilde render ediliyor.</p></section>;
}

function SpacerPreview() {
  return <div className="h-16 bg-zinc-50" />;
}

function FooterPreview() {
  return <footer className="bg-zinc-950 px-8 py-10 text-white"><div className="text-lg font-semibold">Rivaify Storefront</div><div className="mt-5 grid gap-4 text-sm text-zinc-300 md:grid-cols-3"><span>KVKK</span><span>İade Politikası</span><span>Güvenli Ödeme</span></div></footer>;
}

function section(type: SectionType, label: string, category: SectionDefinition['category'], icon: SectionDefinition['icon'], preview: SectionDefinition['preview'], settings: Record<string, unknown> = {}, allowedBlocks?: string[]): SectionDefinition {
  return {
    type,
    label,
    category,
    icon,
    allowedBlocks,
    preview,
    defaults: { id: `sec_${type}_${Date.now()}`, type, enabled: true, settings },
  };
}

export const sectionRegistry: Record<SectionType, SectionDefinition> = {
  header: section('header', 'Header', 'Temel', PanelTop, HeaderPreview, { preset: 'logo-left', sticky: true }, ['logo', 'navigation', 'search', 'account', 'cart', 'language']),
  announcement: section('announcement', 'Announcement Bar', 'Pazarlama', Bell, AnnouncementPreview, { text: '750 TL üzeri ücretsiz kargo' }),
  hero: section('hero', 'Hero', 'Temel', Heading1, HeroPreview, { title: 'Yeni sezon', subtitle: '2026 koleksiyonunu keşfet', alignment: 'center', height: 'large', overlay: 12 }),
  'product-grid': section('product-grid', 'Product Grid', 'Ürünler', Grid2X2, ProductGridPreview, { title: 'Çok Satanlar', columns: { desktop: 4, tablet: 2, mobile: 1 } }),
  'product-carousel': section('product-carousel', 'Product Carousel', 'Ürünler', GalleryHorizontal, ProductGridPreview, { title: 'Yeni Gelenler', columns: { desktop: 4, tablet: 2, mobile: 1 } }),
  'featured-product': section('featured-product', 'Featured Product', 'Ürünler', ShoppingBag, SimplePreview, { title: 'Öne Çıkan Ürün' }),
  'category-showcase': section('category-showcase', 'Category Showcase', 'Koleksiyonlar', Tags, SimplePreview, { title: 'Kategorileri keşfet', layout: 'icon-grid' }),
  'collection-grid': section('collection-grid', 'Collection Grid', 'Koleksiyonlar', Columns3, SimplePreview, { title: 'Koleksiyonlar' }),
  'collection-carousel': section('collection-carousel', 'Collection Carousel', 'Koleksiyonlar', Rows3, SimplePreview, { title: 'Sezon Seçkileri' }),
  'image-banner': section('image-banner', 'Image Banner', 'İçerik', Image, TextMediaPreview, { title: 'Kampanya Banner' }),
  'image-with-text': section('image-with-text', 'Image + Text', 'İçerik', ImagePlus, TextMediaPreview, { title: 'Görsel + Metin', body: 'Kısa ve güçlü marka mesajı.' }),
  video: section('video', 'Video', 'İçerik', PlaySquare, SimplePreview, { title: 'Video' }),
  'rich-text': section('rich-text', 'Rich Text', 'İçerik', Text, SimplePreview, { title: 'Zengin Metin' }),
  reviews: section('reviews', 'Reviews', 'Sosyal', MessageSquareQuote, SimplePreview, { title: 'Müşteri Yorumları' }),
  faq: section('faq', 'FAQ', 'İçerik', ListChecks, SimplePreview, { title: 'Sık Sorulan Sorular' }),
  countdown: section('countdown', 'Countdown', 'Pazarlama', Clock3, SimplePreview, { title: 'Kampanya Sayacı' }),
  'promotion-tiles': section('promotion-tiles', 'Promotion Tiles', 'Pazarlama', Image, TextMediaPreview, { title: 'Sezon kampanyası', body: 'Öne çıkan kampanya alanı.' }),
  newsletter: section('newsletter', 'Newsletter', 'Pazarlama', Mail, SimplePreview, { title: 'Yeni koleksiyonları kaçırma' }),
  'instagram-feed': section('instagram-feed', 'Instagram Feed', 'Sosyal', Camera, SimplePreview, { title: 'Instagram' }),
  'logo-list': section('logo-list', 'Logo List', 'Sosyal', Blocks, SimplePreview, { title: 'Markalar' }),
  'trust-badges': section('trust-badges', 'Trust Badges', 'Pazarlama', BadgeCheck, SimplePreview, { title: 'Güven Rozetleri' }),
  spacer: section('spacer', 'Spacer', 'Temel', Minus, SpacerPreview, {}),
  footer: section('footer', 'Footer', 'Temel', LayoutTemplate, FooterPreview, { show_newsletter: true }, ['logo', 'menu', 'newsletter', 'social', 'payment-icons']),
};

export const sectionLibrary = Object.values(sectionRegistry);
