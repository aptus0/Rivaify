import { useState } from 'react';
import { Package, ShoppingCart, TrendingUp, Users, Wallet } from 'lucide-react';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { MetricCard } from '../../../components/ui/MetricCard';
import { StatusBadge } from '../../../components/ui/Badge';
import type { StoreStatus } from '../../../types';

const SALES_PERIODS = ['Son 7 Gün', 'Son 30 Gün', 'Son 90 Gün'] as const;

// Steps 1-2 are true by construction — you can't reach the dashboard
// without an account and a store. Steps 3-6 need backend flags (has a
// product, has a theme, has a payment method, has a domain) that don't
// exist yet — Commerce/Payments/Domains are later Sprint 02+ work, so
// they're shown as static unchecked items rather than faked as tracked
// progress.
const CHECKLIST = [
  { label: 'Hesabını oluşturdun', done: true },
  { label: 'Mağazan oluşturuldu', done: true },
  { label: 'İlk ürününü ekle', done: false },
  { label: 'Tema seç', done: false },
  { label: 'Ödeme yöntemini bağla', done: false },
  { label: 'Domain bağla', done: false },
];

function greeting(): string {
  const hour = new Date().getHours();
  if (hour < 6) return 'İyi geceler';
  if (hour < 12) return 'Günaydın';
  if (hour < 18) return 'İyi günler';
  return 'İyi akşamlar';
}

function storeStatusBadge(status: StoreStatus) {
  if (status === 'active') return <StatusBadge tone="success" label="Yayında" />;
  if (status === 'pending_approval') return <StatusBadge tone="warning" label="İncelemede" />;
  return <StatusBadge tone="neutral" label="Taslak" />;
}

export function DashboardPage() {
  usePageTitle('Ana Sayfa');
  const { user, store } = useAuth();
  const [salesPeriod, setSalesPeriod] = useState<(typeof SALES_PERIODS)[number]>('Son 7 Gün');

  if (!user || !store) {
    return null;
  }

  const completedCount = CHECKLIST.filter((item) => item.done).length;

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div>
        <h2 className="text-xl font-semibold text-dark">
          {greeting()}, {user.name}
        </h2>
        <p className="text-sm text-muted">{store.name}</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <MetricCard label="Toplam Satış" value="₺0,00" icon={Wallet} />
        <MetricCard label="Siparişler" value="0" icon={ShoppingCart} />
        <MetricCard label="Müşteriler" value="0" icon={Users} />
        <MetricCard label="Dönüşüm" value="%0" icon={TrendingUp} />
      </div>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h3 className="font-medium text-dark">Rivaify&apos;a hoş geldin</h3>
            <p className="text-sm text-muted">Mağazanı satışa hazırlayalım.</p>
          </div>
          <span className="text-sm font-medium text-muted">
            {completedCount} / {CHECKLIST.length} tamamlandı
          </span>
        </div>

        <ul className="mb-4 space-y-2">
          {CHECKLIST.map((item) => (
            <li key={item.label} className="flex items-center gap-2 text-sm">
              <span
                className={`flex h-4 w-4 items-center justify-center rounded-full border text-[10px] ${
                  item.done ? 'border-primary bg-primary text-white' : 'border-border text-transparent'
                }`}
              >
                ✓
              </span>
              <span className={item.done ? 'text-dark' : 'text-muted'}>{item.label}</span>
            </li>
          ))}
        </ul>

        <Button fullWidth={false} disabled title="Ürün ekranları Sprint 02'de geliyor">
          Mağazanı Kurmaya Devam Et
        </Button>
      </Card>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-medium text-dark">Satışlar</h3>
            <div className="flex gap-1 rounded-md bg-app-bg p-1">
              {SALES_PERIODS.map((period) => (
                <button
                  key={period}
                  onClick={() => setSalesPeriod(period)}
                  className={`rounded px-2.5 py-1 text-xs font-medium transition ${
                    salesPeriod === period ? 'bg-card text-dark shadow-sm' : 'text-muted'
                  }`}
                >
                  {period}
                </button>
              ))}
            </div>
          </div>
          <EmptyState
            icon={TrendingUp}
            title="Henüz satış verisi yok"
            description="İlk siparişini aldığında satış grafiğin burada görünecek."
          />
        </Card>

        <Card>
          <h3 className="mb-2 font-medium text-dark">Online Mağaza</h3>
          <p className="text-sm font-medium text-dark">{store.name}</p>
          <p className="mb-3 text-sm text-muted">{store.slug}.rivaify.com</p>
          <div className="mb-4">{storeStatusBadge(store.status)}</div>
          <div className="flex gap-2">
            <Button fullWidth={false} variant="secondary" disabled title="Yakında">
              Mağazayı Gör
            </Button>
            <Button fullWidth={false} variant="secondary" disabled title="Yakında">
              Düzenle
            </Button>
          </div>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h3 className="mb-2 font-medium text-dark">Son Siparişler</h3>
          <EmptyState
            icon={ShoppingCart}
            title="Henüz sipariş yok"
            description="İlk siparişini aldığında burada göreceksin."
          />
        </Card>
        <Card>
          <h3 className="mb-2 font-medium text-dark">En Çok Satan Ürünler</h3>
          <EmptyState
            icon={Package}
            title="Henüz yeterli veri bulunmuyor"
            description="Ürünlerin satışa başladığında en çok satanları burada görürsün."
          />
        </Card>
      </div>
    </div>
  );
}
