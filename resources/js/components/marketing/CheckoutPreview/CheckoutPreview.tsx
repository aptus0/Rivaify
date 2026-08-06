import { useState } from 'react';
import { Lock } from 'lucide-react';
import { RivaCard } from '../../effects/RivaCard';
import { Reveal } from '../../effects/Reveal';
import { Container } from '../../ui/Container';

const ACCENT_OPTIONS = ['#FF6B00', '#111827', '#7C5CFC', '#0EA5A5'];
const BUTTON_STYLES = ['Yuvarlak', 'Köşeli'] as const;

export function CheckoutPreview() {
  const [accent, setAccent] = useState(ACCENT_OPTIONS[0]);
  const [buttonStyle, setButtonStyle] = useState<(typeof BUTTON_STYLES)[number]>('Yuvarlak');

  return (
    <section id="checkout" className="px-6 py-24 lg:px-8 lg:py-32">
      <Container size="wide">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
          <RivaCard variant="spectrum" intensity="medium" className="rounded-2xl">
            <div className="grid grid-cols-1 gap-6 rounded-2xl border border-dark/[0.07] bg-white p-6 sm:p-8 md:grid-cols-2">
              <div>
                <p className="text-xs font-bold uppercase tracking-wider text-dark/35">Teslimat Bilgileri</p>
                <div className="mt-4 flex flex-col gap-3">
                  {['Ad Soyad', 'E-posta', 'Adres', 'Şehir'].map((field) => (
                    <div key={field}>
                      <label className="text-xs font-medium text-dark/50">{field}</label>
                      <div className="mt-1 h-10 rounded-lg border border-dark/10 bg-surface/60" />
                    </div>
                  ))}
                </div>
              </div>

              <div>
                <p className="text-xs font-bold uppercase tracking-wider text-dark/35">Sipariş Özeti</p>
                <div className="mt-4 flex items-center justify-between rounded-xl border border-dark/[0.06] bg-surface/60 p-3">
                  <div className="flex items-center gap-3">
                    <div className="h-12 w-12 rounded-lg bg-white" />
                    <div>
                      <p className="text-sm font-semibold text-dark">Nike Air Max</p>
                      <p className="text-xs text-dark/40">1 adet</p>
                    </div>
                  </div>
                  <p className="text-sm font-bold text-dark">₺4.499</p>
                </div>

                <div className="mt-4 flex flex-col gap-2 text-sm">
                  <div className="flex justify-between text-dark/60">
                    <span>Ara Toplam</span>
                    <span>₺4.499</span>
                  </div>
                  <div className="flex justify-between text-dark/60">
                    <span>Kargo</span>
                    <span className="font-medium text-primary">Ücretsiz</span>
                  </div>
                  <div className="mt-2 flex justify-between border-t border-dark/[0.06] pt-2 text-base font-bold text-dark">
                    <span>Toplam</span>
                    <span>₺4.499</span>
                  </div>
                </div>

                <button
                  type="button"
                  style={{ backgroundColor: accent, borderRadius: buttonStyle === 'Yuvarlak' ? 9999 : 10 }}
                  className="mt-5 inline-flex w-full items-center justify-center gap-2 py-3 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
                >
                  <Lock className="h-4 w-4" strokeWidth={2} />
                  Ödemeyi Tamamla
                </button>
                <p className="mt-2 text-center text-[11px] text-dark/30">
                  Bu bir mağaza önizlemesidir — gerçek ödeme bilgisi istenmez.
                </p>
              </div>
            </div>
          </RivaCard>

          <Reveal delay={0.15}>
            <div className="rounded-2xl border border-dark/[0.07] bg-white p-5">
              <p className="text-xs font-bold uppercase tracking-wider text-dark/35">Checkout Özelleştirme</p>

              <div className="mt-4">
                <p className="text-xs font-semibold text-dark/50">Ana Renk</p>
                <div className="mt-2 flex gap-2">
                  {ACCENT_OPTIONS.map((color) => (
                    <button
                      key={color}
                      type="button"
                      onClick={() => setAccent(color)}
                      aria-label={color}
                      aria-pressed={accent === color}
                      className={`h-8 w-8 rounded-full border-2 transition-transform hover:scale-105 ${
                        accent === color ? 'border-dark' : 'border-transparent'
                      }`}
                      style={{ backgroundColor: color }}
                    />
                  ))}
                </div>
              </div>

              <div className="mt-5">
                <p className="text-xs font-semibold text-dark/50">Buton Stili</p>
                <div className="mt-2 flex gap-2">
                  {BUTTON_STYLES.map((style) => (
                    <button
                      key={style}
                      type="button"
                      onClick={() => setButtonStyle(style)}
                      aria-pressed={buttonStyle === style}
                      className={`min-h-9 flex-1 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors ${
                        buttonStyle === style
                          ? 'border-primary/40 bg-surface-orange text-primary'
                          : 'border-dark/10 text-dark/50 hover:border-dark/20'
                      }`}
                    >
                      {style}
                    </button>
                  ))}
                </div>
              </div>

              <div className="mt-5 flex flex-col gap-2">
                {['Logo', 'Yazı Tipi', 'Yerleşim'].map((label) => (
                  <div
                    key={label}
                    className="flex items-center justify-between rounded-lg border border-dark/[0.06] bg-surface/60 px-3 py-2 text-xs text-dark/50"
                  >
                    {label}
                    <span className="text-dark/30">Varsayılan</span>
                  </div>
                ))}
              </div>
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
