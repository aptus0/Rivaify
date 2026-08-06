import { BarChart3, Camera, CreditCard, Layers, Package, ShoppingCart, Store, Webhook } from 'lucide-react';
import { BentoCard } from '../../../bento/BentoCard';
import { BentoGrid } from '../../../bento/BentoGrid';
import { BentoVisual } from '../../../bento/BentoVisual';
import { BrandGrid } from '../../../brands/BrandGrid';
import { Reveal } from '../../../effects/Reveal';

function TileHeading({ icon: Icon, title }: { icon: typeof Store; title: string }) {
  return (
    <div className="mb-4 flex items-center gap-2.5">
      <span className="flex h-9 w-9 items-center justify-center rounded-control bg-primary/10 text-primary shadow-[0_0_10px_rgba(255,107,0,0.1)]">
        <Icon className="h-4 w-4" strokeWidth={2} />
      </span>
      <p className="text-sm font-bold text-white">{title}</p>
    </div>
  );
}

function OnlineStoreTile() {
  return (
    <>
      <TileHeading icon={Store} title="Online Mağaza" />
      <BentoVisual label="yasemingiyim.com">
        <div className="grid grid-cols-3 gap-1.5">
          <div className="col-span-3 h-14 rounded-control bg-primary/20" />
          <div className="h-10 rounded-control bg-white/[0.03]" />
          <div className="h-10 rounded-control bg-white/[0.03]" />
          <div className="h-10 rounded-control bg-white/[0.03]" />
        </div>
      </BentoVisual>
    </>
  );
}

function CheckoutTile() {
  return (
    <>
      <TileHeading icon={CreditCard} title="Checkout" />
      <BentoVisual>
        <div className="flex items-center justify-between text-[11px]">
          <span className="text-white/50">Toplam</span>
          <span className="font-bold text-white">₺4.499</span>
        </div>
        <div className="mt-2 h-7 rounded-full bg-primary drop-shadow-[0_0_8px_rgba(255,107,0,0.4)]" />
      </BentoVisual>
    </>
  );
}

function VisualBuilderTile() {
  return (
    <>
      <TileHeading icon={Layers} title="Visual Builder" />
      <BentoVisual>
        <div className="flex flex-col gap-1.5">
          <div className="h-4 rounded bg-primary/30" />
          <div className="h-4 rounded bg-white/[0.03]" />
          <div className="h-4 w-2/3 rounded bg-white/[0.03]" />
        </div>
      </BentoVisual>
    </>
  );
}

function OrdersTile() {
  return (
    <>
      <TileHeading icon={ShoppingCart} title="Siparişler" />
      <BentoVisual>
        <div className="flex flex-col gap-1.5">
          {['#1058', '#1057'].map((id) => (
            <div key={id} className="flex items-center justify-between text-[11px]">
              <span className="font-semibold text-white">{id}</span>
              <span className="text-primary">Ödendi</span>
            </div>
          ))}
        </div>
      </BentoVisual>
    </>
  );
}

function InventoryTile() {
  return (
    <>
      <TileHeading icon={Package} title="Stok" />
      <BentoVisual>
        <div className="flex items-center justify-between text-[11px]">
          <span className="text-white/50">248 ürün senkronize</span>
        </div>
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
          <div className="h-full w-[86%] rounded-full bg-primary shadow-[0_0_8px_rgba(255,107,0,0.6)]" />
        </div>
      </BentoVisual>
    </>
  );
}

function SocialCommerceTile() {
  return (
    <>
      <TileHeading icon={Camera} title="Sosyal Ticaret" />
      <BentoVisual>
        <div className="grid grid-cols-3 gap-1.5">
          {[0, 1, 2].map((i) => (
            <div key={i} className="aspect-square rounded-control bg-primary/20" />
          ))}
        </div>
      </BentoVisual>
    </>
  );
}

function AnalyticsTile() {
  const points = [30, 45, 38, 58, 50, 70, 62, 80];
  const max = Math.max(...points);
  const linePoints = points.map((point, index) => `${(index / (points.length - 1)) * 100},${40 - (point / max) * 40}`).join(' ');

  return (
    <>
      <TileHeading icon={BarChart3} title="Analitik" />
      <BentoVisual>
        <div className="flex items-center justify-between text-[11px]">
          <span className="text-white/50">Net satış</span>
          <span className="font-bold text-primary drop-shadow-[0_0_5px_rgba(255,107,0,0.3)]">+18,4%</span>
        </div>
        <svg viewBox="0 0 100 40" preserveAspectRatio="none" className="mt-2 h-10 w-full drop-shadow-[0_0_8px_rgba(255,107,0,0.4)]">
          <polyline points={linePoints} fill="none" stroke="#FF6B00" strokeWidth="2.5" vectorEffect="non-scaling-stroke" />
        </svg>
      </BentoVisual>
    </>
  );
}

export function HomeBento() {
  return (
    <Reveal>
      <BentoGrid>
        <BentoCard size="wide">
          <OnlineStoreTile />
        </BentoCard>
        <BentoCard size="sm">
          <OrdersTile />
        </BentoCard>

        <BentoCard size="sm">
          <VisualBuilderTile />
        </BentoCard>
        <BentoCard size="sm">
          <CheckoutTile />
        </BentoCard>
        <BentoCard size="sm">
          <InventoryTile />
        </BentoCard>

        <BentoCard size="sm">
          <SocialCommerceTile />
        </BentoCard>
        <BentoCard size="wide">
          <AnalyticsTile />
        </BentoCard>

        <BentoCard size="hero" variant="subtle">
          <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
            <div>
              <TileHeading icon={Webhook} title="Entegrasyonlar" />
              <p className="max-w-md text-sm text-white/50">
                Sosyal, ödeme ve kargo servislerini Rivaify ile tek merkezden bağla.
              </p>
            </div>
            <BrandGrid categories={['social', 'payment', 'shipping']} className="sm:min-w-[420px]" />
          </div>
        </BentoCard>
      </BentoGrid>
    </Reveal>
  );
}
