import { motion } from 'framer-motion';

interface BuilderSection {
  id: string;
  label: string;
}

interface StorePreviewProps {
  sections: BuilderSection[];
  device: 'desktop' | 'tablet' | 'mobile';
  selectedId: string | null;
  accent: string;
}

const DEVICE_WIDTH: Record<StorePreviewProps['device'], string> = {
  desktop: 'w-full max-w-[420px]',
  tablet: 'w-full max-w-[320px]',
  mobile: 'w-full max-w-[220px]',
};

const BLOCK_HEIGHT: Record<string, string> = {
  hero: 'h-16',
  bestsellers: 'h-12',
  banner: 'h-10',
  reviews: 'h-10',
  newsletter: 'h-9',
};

function Block({ section, accent, selected }: { section: BuilderSection; accent: string; selected: boolean }) {
  return (
    <motion.div
      layout
      transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
      className={`rounded-lg border px-3 py-2 transition-colors ${
        selected ? 'border-primary/50 bg-surface-orange' : 'border-dark/[0.06] bg-white'
      }`}
    >
      <p className="text-[10px] font-semibold text-dark/50">{section.label}</p>
      <div
        className={`mt-1.5 rounded-md ${BLOCK_HEIGHT[section.id] ?? 'h-10'}`}
        style={{ backgroundColor: `color-mix(in srgb, ${accent} ${selected ? 22 : 10}%, white)` }}
      />
    </motion.div>
  );
}

export function StorePreview({ sections, device, selectedId, accent }: StorePreviewProps) {
  return (
    <div className={`overflow-hidden rounded-2xl border border-dark/[0.08] bg-surface/60 p-3 ${DEVICE_WIDTH[device]}`}>
      <div className="mb-2 flex items-center justify-between rounded-md bg-white px-2.5 py-1.5">
        <span className="text-[10px] font-bold text-dark">yasemingiyim.com</span>
        <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: accent }} />
      </div>
      <div className="flex flex-col gap-1.5">
        {sections.map((section) => (
          <Block key={section.id} section={section} accent={accent} selected={section.id === selectedId} />
        ))}
      </div>
    </div>
  );
}
