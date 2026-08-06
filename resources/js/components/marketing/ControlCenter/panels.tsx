import { AnimatePresence, motion } from 'framer-motion';
import type { ReactNode } from 'react';
import { Package, TrendingUp, Users } from 'lucide-react';

export type ControlTab = 'orders' | 'products' | 'customers' | 'inventory' | 'analytics';

const ORDERS = [
  { id: '#1058', customer: 'Ahmet Yılmaz', amount: '₺2.845', status: 'Ödendi' },
  { id: '#1057', customer: 'Zeynep Arslan', amount: '₺960', status: 'Hazırlanıyor' },
  { id: '#1056', customer: 'Burak Şahin', amount: '₺1.420', status: 'Kargoda' },
  { id: '#1055', customer: 'Deniz Koç', amount: '₺3.210', status: 'Ödendi' },
];

const PRODUCTS = [
  { name: 'Nike Air Max 90', variants: '6 varyant', stock: 128, price: '₺4.499' },
  { name: 'Essential Tişört', variants: '4 varyant', stock: 342, price: '₺349' },
  { name: 'Deri Sırt Çantası', variants: '2 varyant', stock: 24, price: '₺1.299' },
];

const CUSTOMERS = [
  { name: 'Elif Kaya', segment: 'Sadık Müşteri', orders: 12, spent: '₺8.240' },
  { name: 'Mert Yıldız', segment: 'Yeni Müşteri', orders: 1, spent: '₺860' },
  { name: 'Aylin Demir', segment: 'VIP', orders: 27, spent: '₺21.480' },
];

const INVENTORY = [
  { location: 'Merkez Depo · İstanbul', skus: 1240, low: 8 },
  { location: 'Karacabey Şube', skus: 384, low: 2 },
];

function Row({ children }: { children: ReactNode }) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-dark/[0.06] bg-white px-4 py-3.5">
      {children}
    </div>
  );
}

function OrdersPanel() {
  return (
    <div className="flex flex-col gap-2.5">
      {ORDERS.map((order) => (
        <Row key={order.id}>
          <div>
            <p className="text-sm font-bold text-dark">{order.id}</p>
            <p className="text-xs text-dark/40">{order.customer}</p>
          </div>
          <div className="text-right">
            <p className="text-sm font-bold text-dark">{order.amount}</p>
            <p className="text-xs text-primary">{order.status}</p>
          </div>
        </Row>
      ))}
    </div>
  );
}

function ProductsPanel() {
  return (
    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
      {PRODUCTS.map((product) => (
        <div key={product.name} className="rounded-xl border border-dark/[0.06] bg-white p-4">
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-orange text-primary">
            <Package className="h-4 w-4" strokeWidth={2} />
          </span>
          <p className="mt-3 text-sm font-bold text-dark">{product.name}</p>
          <p className="text-xs text-dark/40">{product.variants} · {product.stock} adet stok</p>
          <p className="mt-2 text-sm font-semibold text-primary">{product.price}</p>
        </div>
      ))}
    </div>
  );
}

function CustomersPanel() {
  return (
    <div className="flex flex-col gap-2.5">
      {CUSTOMERS.map((customer) => (
        <Row key={customer.name}>
          <div className="flex items-center gap-3">
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-surface-orange text-sm font-bold text-primary">
              {customer.name.charAt(0)}
            </span>
            <div>
              <p className="text-sm font-bold text-dark">{customer.name}</p>
              <p className="text-xs text-dark/40">{customer.segment}</p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-sm font-bold text-dark">{customer.spent}</p>
            <p className="text-xs text-dark/40">{customer.orders} sipariş</p>
          </div>
        </Row>
      ))}
    </div>
  );
}

function InventoryPanel() {
  return (
    <div className="flex flex-col gap-2.5">
      {INVENTORY.map((location) => (
        <Row key={location.location}>
          <div>
            <p className="text-sm font-bold text-dark">{location.location}</p>
            <p className="text-xs text-dark/40">{location.skus} SKU</p>
          </div>
          <div className="text-right">
            <p className="text-xs font-semibold text-primary">{location.low} üründe düşük stok</p>
          </div>
        </Row>
      ))}
    </div>
  );
}

function AnalyticsPanel() {
  return (
    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
      {[
        { label: 'Net Satış', value: '₺184.920', icon: TrendingUp },
        { label: 'Sipariş', value: '1.248', icon: Package },
        { label: 'Dönüşüm', value: '%4,82', icon: TrendingUp },
        { label: 'Müşteri', value: '4.892', icon: Users },
      ].map((stat) => (
        <div key={stat.label} className="rounded-xl border border-dark/[0.06] bg-white p-4">
          <stat.icon className="h-4 w-4 text-primary" strokeWidth={2} />
          <p className="mt-2 text-lg font-bold text-dark">{stat.value}</p>
          <p className="text-xs text-dark/40">{stat.label}</p>
        </div>
      ))}
    </div>
  );
}

const PANELS: Record<ControlTab, () => ReactNode> = {
  orders: OrdersPanel,
  products: ProductsPanel,
  customers: CustomersPanel,
  inventory: InventoryPanel,
  analytics: AnalyticsPanel,
};

export function ControlPanel({ tab }: { tab: ControlTab }) {
  const Panel = PANELS[tab];
  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={tab}
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        exit={{ opacity: 0, y: -8 }}
        transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
      >
        <Panel />
      </motion.div>
    </AnimatePresence>
  );
}
