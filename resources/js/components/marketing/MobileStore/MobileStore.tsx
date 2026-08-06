import { Heart, Minus, Plus, Search, ShoppingBag } from 'lucide-react';
import { RivaCard } from '../../effects/RivaCard';
import { Reveal } from '../../effects/Reveal';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';

function PhoneFrame({ children, label }: { children: React.ReactNode; label: string }) {
  return (
    <div className="flex flex-col items-center">
      <RivaCard variant="spectrum" intensity="subtle" className="rounded-[2rem]">
        <div className="h-[420px] w-[210px] overflow-hidden rounded-[2rem] border-4 border-dark bg-white shadow-[0_30px_60px_-24px_rgba(13,17,23,0.35)]">
          <div className="mx-auto mt-2 h-1.5 w-16 rounded-full bg-dark/10" />
          <div className="h-full overflow-hidden px-3 pb-4 pt-2">{children}</div>
        </div>
      </RivaCard>
      <p className="mt-4 text-sm font-semibold text-white/60">{label}</p>
    </div>
  );
}

function HomeScreen() {
  return (
    <div className="flex h-full flex-col gap-2.5">
      <div className="flex items-center justify-between">
        <span className="text-sm font-extrabold text-dark">Yasemin</span>
        <Search className="h-4 w-4 text-dark/40" />
      </div>
      <div className="h-24 rounded-xl bg-surface-orange" />
      <div className="grid grid-cols-2 gap-2">
        <div className="h-20 rounded-lg bg-surface" />
        <div className="h-20 rounded-lg bg-surface" />
        <div className="h-20 rounded-lg bg-surface" />
        <div className="h-20 rounded-lg bg-surface" />
      </div>
    </div>
  );
}

function ProductScreen() {
  return (
    <div className="flex h-full flex-col gap-2.5">
      <div className="h-44 rounded-xl bg-surface-orange" />
      <div className="flex items-center justify-between">
        <p className="text-sm font-bold text-dark">Nike Air Max 90</p>
        <Heart className="h-4 w-4 text-dark/30" />
      </div>
      <p className="text-sm font-extrabold text-primary">₺4.499</p>
      <div className="flex gap-1.5">
        {['S', 'M', 'L', 'XL'].map((size) => (
          <span key={size} className="flex h-7 w-7 items-center justify-center rounded-md border border-dark/10 text-[11px] font-medium text-dark/60">
            {size}
          </span>
        ))}
      </div>
      <div className="mt-auto rounded-full bg-primary py-2.5 text-center text-xs font-semibold text-white">
        Sepete Ekle
      </div>
    </div>
  );
}

function CartScreen() {
  return (
    <div className="flex h-full flex-col gap-3">
      <p className="text-sm font-bold text-dark">Sepetim</p>
      {[1, 2].map((item) => (
        <div key={item} className="flex items-center gap-2 rounded-lg border border-dark/[0.06] p-2">
          <div className="h-10 w-10 rounded-md bg-surface-orange" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-xs font-semibold text-dark">Ürün {item}</p>
            <p className="text-[11px] text-dark/40">₺349</p>
          </div>
          <div className="flex items-center gap-1 rounded-full border border-dark/10 px-1.5 py-0.5">
            <Minus className="h-3 w-3 text-dark/40" />
            <span className="text-[10px] font-semibold">1</span>
            <Plus className="h-3 w-3 text-dark/40" />
          </div>
        </div>
      ))}
      <div className="mt-auto flex items-center justify-between rounded-full bg-dark px-4 py-2.5 text-xs font-semibold text-white">
        <span className="flex items-center gap-1.5">
          <ShoppingBag className="h-3.5 w-3.5" /> Devam Et
        </span>
        <span>₺698</span>
      </div>
    </div>
  );
}

export function MobileStore() {
  return (
    <section className="bg-soft-dark px-6 py-24 text-white lg:px-8 lg:py-32">
      <Container>
        <SectionHeading
          onDark
          title={
            <>
              Mobil için sonradan uyarlanan değil,
              <br />
              <span className="text-primary-soft">mobil düşünülerek tasarlanan mağazalar.</span>
            </>
          }
        />

        <div className="mt-16 flex flex-wrap items-start justify-center gap-8">
          <Reveal delay={0}>
            <PhoneFrame label="Ana Sayfa">
              <HomeScreen />
            </PhoneFrame>
          </Reveal>
          <Reveal delay={0.1}>
            <PhoneFrame label="Ürün Detayı">
              <ProductScreen />
            </PhoneFrame>
          </Reveal>
          <Reveal delay={0.2}>
            <PhoneFrame label="Sepet">
              <CartScreen />
            </PhoneFrame>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
