import { useState } from 'react';
import { BarChart3, Package, ShoppingCart, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { ScanHighlight } from '../../effects/ScanHighlight';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';
import { ControlPanel, type ControlTab } from './panels';

const TABS: { id: ControlTab; label: string; icon: LucideIcon }[] = [
  { id: 'orders', label: 'Siparişler', icon: ShoppingCart },
  { id: 'products', label: 'Ürünler', icon: Package },
  { id: 'customers', label: 'Müşteriler', icon: Users },
  { id: 'inventory', label: 'Stok', icon: Warehouse },
  { id: 'analytics', label: 'Analitik', icon: BarChart3 },
];

export function ControlCenter() {
  const [activeTab, setActiveTab] = useState<ControlTab>('orders');

  return (
    <section id="kontrol-merkezi" className="bg-surface px-6 py-24 lg:px-8 lg:py-32">
      <Container>
        <SectionHeading title="İşletmen için tek kontrol merkezi." align="left" />

        <div className="mt-10 flex flex-wrap gap-2">
          {TABS.map((tab) => {
            const isActive = tab.id === activeTab;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTab(tab.id)}
                aria-pressed={isActive}
                className={`inline-flex min-h-11 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors ${
                  isActive
                    ? 'border-primary bg-primary text-white'
                    : 'border-dark/10 bg-white text-dark/60 hover:border-dark/20 hover:text-dark'
                }`}
              >
                <tab.icon className="h-4 w-4" strokeWidth={2} />
                {tab.label}
              </button>
            );
          })}
        </div>

        <AuraCard intensity="subtle" className="mt-6 rounded-2xl">
          <ScanHighlight className="rounded-2xl border border-dark/[0.07] bg-white p-6 sm:p-8">
            <ControlPanel tab={activeTab} />
          </ScanHighlight>
        </AuraCard>
      </Container>
    </section>
  );
}
