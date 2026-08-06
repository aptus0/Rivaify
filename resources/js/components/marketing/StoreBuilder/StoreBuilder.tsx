import { useState } from 'react';
import { Reorder } from 'framer-motion';
import { GripVertical, Laptop, Smartphone, Tablet, type LucideIcon } from 'lucide-react';
import { AuraCard } from '../../effects/AuraCard';
import { Container } from '../../ui/Container';
import { SectionHeading } from '../../ui/SectionHeading';
import { StorePreview } from './StorePreview';

interface BuilderSection {
  id: string;
  label: string;
}

const INITIAL_SECTIONS: BuilderSection[] = [
  { id: 'hero', label: 'Hero' },
  { id: 'bestsellers', label: 'Çok Satanlar' },
  { id: 'banner', label: 'Kampanya Banner' },
  { id: 'reviews', label: 'Yorumlar' },
  { id: 'newsletter', label: 'Bülten' },
];

const DEVICES: { id: 'desktop' | 'tablet' | 'mobile'; icon: LucideIcon; label: string }[] = [
  { id: 'desktop', icon: Laptop, label: 'Masaüstü' },
  { id: 'tablet', icon: Tablet, label: 'Tablet' },
  { id: 'mobile', icon: Smartphone, label: 'Mobil' },
];

const SETTINGS_GROUPS = [
  { title: 'Tipografi', options: ['Inter', 'Söhne', 'Fraunces'] },
  { title: 'Renk', options: ['#FF6B00', '#111827', '#0D1117'] },
  { title: 'Boşluk', options: ['Dar', 'Normal', 'Geniş'] },
];

/** The landing page's flagship interactivity demo: real drag-to-reorder via
 * Motion's <Reorder.Group> (already a dependency — no new drag library),
 * a device-width toggle, and a section picker that highlights the matching
 * block in the live preview. Nothing here persists; it's a marketing demo. */
export function StoreBuilder() {
  const [sections, setSections] = useState(INITIAL_SECTIONS);
  const [device, setDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [accent, setAccent] = useState('#FF6B00');

  return (
    <section id="magaza-olusturucu" className="px-6 py-24 lg:px-8 lg:py-32">
      <Container size="wide">
        <SectionHeading
          title={
            <>
              Kod yazmadan,
              <br />
              <span className="text-primary">markana ait bir mağaza oluştur.</span>
            </>
          }
        />

        <AuraCard intensity="medium" className="mt-12 rounded-2xl">
          <div className="grid grid-cols-1 gap-4 rounded-2xl border border-dark/[0.07] bg-white p-4 lg:grid-cols-[220px_1fr_220px] lg:p-6">
            <div>
              <p className="px-1 text-[11px] font-bold uppercase tracking-wider text-dark/35">Bölümler</p>
              <p className="mt-1 px-1 text-[11px] text-dark/35">Sürükleyerek sırala</p>
              <Reorder.Group axis="y" values={sections} onReorder={setSections} className="mt-3 flex flex-col gap-1.5">
                {sections.map((sectionItem) => (
                  <Reorder.Item
                    key={sectionItem.id}
                    value={sectionItem}
                    onClick={() => setSelectedId((current) => (current === sectionItem.id ? null : sectionItem.id))}
                    className={`flex cursor-grab items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors active:cursor-grabbing ${
                      selectedId === sectionItem.id
                        ? 'border-primary/40 bg-surface-orange text-primary'
                        : 'border-dark/[0.06] bg-surface/60 text-dark/70 hover:border-dark/15'
                    }`}
                  >
                    <GripVertical className="h-4 w-4 shrink-0 text-dark/25" />
                    {sectionItem.label}
                  </Reorder.Item>
                ))}
              </Reorder.Group>
            </div>

            <div className="flex flex-col items-center">
              <div className="mb-3 flex gap-1 rounded-full border border-dark/[0.07] bg-surface/60 p-1">
                {DEVICES.map((d) => (
                  <button
                    key={d.id}
                    type="button"
                    onClick={() => setDevice(d.id)}
                    aria-pressed={device === d.id}
                    aria-label={d.label}
                    className={`inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors ${
                      device === d.id ? 'bg-white text-primary shadow-sm' : 'text-dark/40 hover:text-dark'
                    }`}
                  >
                    <d.icon className="h-4 w-4" strokeWidth={2} />
                  </button>
                ))}
              </div>

              <StorePreview sections={sections} device={device} selectedId={selectedId} accent={accent} />
            </div>

            <div>
              <p className="px-1 text-[11px] font-bold uppercase tracking-wider text-dark/35">Ayarlar</p>
              <div className="mt-3 flex flex-col gap-4">
                {SETTINGS_GROUPS.map((group) => (
                  <div key={group.title}>
                    <p className="px-1 text-xs font-semibold text-dark/50">{group.title}</p>
                    {group.title === 'Renk' ? (
                      <div className="mt-2 flex gap-2 px-1">
                        {group.options.map((color) => (
                          <button
                            key={color}
                            type="button"
                            onClick={() => setAccent(color)}
                            aria-label={color}
                            aria-pressed={accent === color}
                            className={`h-7 w-7 rounded-full border-2 transition-transform hover:scale-105 ${
                              accent === color ? 'border-dark' : 'border-transparent'
                            }`}
                            style={{ backgroundColor: color }}
                          />
                        ))}
                      </div>
                    ) : (
                      <div className="mt-2 flex flex-col gap-1 px-1">
                        {group.options.map((option) => (
                          <span
                            key={option}
                            className="rounded-lg border border-dark/[0.06] bg-surface/60 px-3 py-1.5 text-xs text-dark/60"
                          >
                            {option}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </AuraCard>
      </Container>
    </section>
  );
}
