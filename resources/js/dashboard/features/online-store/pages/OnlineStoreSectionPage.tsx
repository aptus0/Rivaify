import { Link, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  BadgeCheck,
  CreditCard,
  FileText,
  GalleryHorizontal,
  Globe2,
  Image,
  Navigation,
  Palette,
  Plus,
  SearchCheck,
  Settings2,
  ShieldCheck,
} from 'lucide-react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';

const pages = {
  pages: {
    title: 'Sayfalar',
    description: 'Ana sayfa dışındaki içerik sayfaları, ürün ve koleksiyon şablonları buradan yönetilir.',
    icon: FileText,
    actions: ['Yeni sayfa', 'Şablonları düzenle', 'SEO kontrolü'],
    rows: ['Ana sayfa', 'Ürün şablonu', 'Koleksiyon şablonu', 'Hakkımızda', 'İletişim'],
  },
  navigation: {
    title: 'Navigasyon',
    description: 'Header, footer ve kampanya menülerini mağaza temasından bağımsız düzenleyin.',
    icon: Navigation,
    actions: ['Menü ekle', 'Header menüsü', 'Footer menüsü'],
    rows: ['Ana menü', 'Footer politikaları', 'Koleksiyon bağlantıları'],
  },
  domains: {
    title: 'Domainler',
    description: 'Mağaza alan adları, doğrulama durumu ve birincil domain seçimi.',
    icon: Globe2,
    actions: ['Domain bağla', 'DNS kontrolü', 'Birincil seç'],
    rows: ['app.rivaify.com', 'Mağaza alt domaini', 'SSL durumu'],
  },
  preferences: {
    title: 'Tercihler',
    description: 'Global marka ayarları, renkler, tipografi, SEO ve sosyal paylaşım görünümü.',
    icon: Settings2,
    actions: ['Marka ayarları', 'SEO', 'Sosyal görsel'],
    rows: ['Renk paleti', 'Tipografi', 'Buton ve kart radius', 'Meta açıklaması'],
  },
  media: {
    title: 'Medya',
    description: 'Tema görselleri, banner medyaları, odak noktaları ve mobil görsel varyantları.',
    icon: Image,
    actions: ['Medya yükle', 'Odak noktası', 'Mobil varyant'],
    rows: ['Hero görseli', 'Koleksiyon bannerı', 'Ürün lifestyle görselleri'],
  },
  checkout: {
    title: 'Checkout',
    description: 'Ödeme sonrası deneyim, güven rozetleri ve tema ile uyumlu checkout alanları.',
    icon: CreditCard,
    actions: ['Önizle', 'Güven alanları', 'Teşekkür sayfası'],
    rows: ['Checkout header', 'Sipariş özeti', 'Teşekkür sayfası', 'Politika bağlantıları'],
  },
} as const;

type SectionKey = keyof typeof pages;

export function OnlineStoreSectionPage() {
  const { section = 'pages' } = useParams<{ section: SectionKey }>();
  const current = pages[(section as SectionKey) in pages ? section as SectionKey : 'pages'];
  const Icon = current.icon;

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <Link to="/online-store" className="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-dark">
            <ArrowLeft className="h-4 w-4" />Online mağaza
          </Link>
          <div className="mt-4 flex items-center gap-3">
            <span className="inline-flex h-11 w-11 items-center justify-center rounded-md bg-primary text-white">
              <Icon className="h-5 w-5" />
            </span>
            <div>
              <h1 className="text-2xl font-semibold text-dark">{current.title}</h1>
              <p className="mt-1 max-w-2xl text-sm text-muted">{current.description}</p>
            </div>
          </div>
        </div>
        <Button type="button" fullWidth={false}>
          <Plus className="h-4 w-4" />Yeni işlem
        </Button>
      </div>

      <section className="grid gap-4 lg:grid-cols-3">
        {current.actions.map((action, index) => {
          const ActionIcon = [Palette, SearchCheck, ShieldCheck][index] ?? BadgeCheck;
          return (
            <Card className="p-5" key={action}>
              <ActionIcon className="h-5 w-5 text-primary" />
              <h2 className="mt-4 text-base font-semibold text-dark">{action}</h2>
              <p className="mt-2 text-sm text-muted">Taslak değişiklikler güvenli yayın akışına alınır.</p>
            </Card>
          );
        })}
      </section>

      <Card className="p-0">
        <div className="border-b border-border px-5 py-4">
          <h2 className="text-base font-semibold text-dark">Yönetilebilir alanlar</h2>
        </div>
        <div className="divide-y divide-border">
          {current.rows.map((row) => (
            <div className="flex items-center justify-between gap-4 px-5 py-4" key={row}>
              <div className="flex min-w-0 items-center gap-3">
                <GalleryHorizontal className="h-4 w-4 shrink-0 text-primary" />
                <span className="truncate text-sm font-medium text-dark">{row}</span>
              </div>
              <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Hazır</span>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
