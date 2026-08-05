import { motion } from 'framer-motion';

interface StoreTheme {
  name: string;
  category: string;
  accent: string;
  bg: string;
}

const STORE_THEMES: StoreTheme[] = [
  { name: 'Aura', category: 'Fashion', accent: '#111111', bg: '#F4F1EC' },
  { name: 'Bloom', category: 'Beauty', accent: '#FF6B00', bg: '#FFF5EC' },
  { name: 'Form', category: 'Minimal Store', accent: '#3A3A3A', bg: '#F6F6F5' },
];

function StoreMock({ theme }: { theme: StoreTheme }) {
  return (
    <div className="h-40 w-full p-4" style={{ backgroundColor: theme.bg }}>
      <div className="flex items-center justify-between">
        <span className="h-2 w-14 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.8 }} />
        <div className="flex gap-1.5">
          <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.4 }} />
          <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.4 }} />
          <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: theme.accent, opacity: 0.4 }} />
        </div>
      </div>
      <div className="mt-4 grid grid-cols-3 gap-2">
        <div className="col-span-2 rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.12, height: 72 }} />
        <div className="flex flex-col gap-2">
          <div className="rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.18, height: 33 }} />
          <div className="rounded-lg" style={{ backgroundColor: theme.accent, opacity: 0.12, height: 33 }} />
        </div>
      </div>
    </div>
  );
}

export function Themes() {
  return (
    <section id="temalar" className="px-6 py-24 lg:px-8 lg:py-32">
      <div className="mx-auto max-w-6xl">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end"
        >
          <div className="max-w-xl">
            <h2 className="text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
              Mağazan, <span className="text-primary">markan gibi görünmeli.</span>
            </h2>
            <p className="mt-4 text-base leading-relaxed text-dark/50">
              Profesyonel ve dönüşüm odaklı Rivaify temalarıyla mağazanı dakikalar içinde yayına hazırla.
            </p>
          </div>
          <span className="inline-flex shrink-0 items-center rounded-full border border-primary/20 bg-surface-orange px-4 py-1.5 text-xs font-semibold text-primary">
            30+ Tema Planlanıyor
          </span>
        </motion.div>

        <div className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-3">
          {STORE_THEMES.map((theme, index) => (
            <motion.div
              key={theme.name}
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
              className="overflow-hidden rounded-2xl border border-dark/[0.07] bg-white transition-shadow hover:shadow-[0_16px_40px_-16px_rgba(17,17,17,0.15)]"
            >
              <StoreMock theme={theme} />
              <div className="flex items-center justify-between border-t border-dark/[0.06] px-4 py-3.5">
                <div>
                  <p className="text-sm font-bold text-dark">{theme.name}</p>
                  <p className="text-xs text-dark/40">{theme.category}</p>
                </div>
                <span
                  className="h-3 w-3 rounded-full"
                  style={{ backgroundColor: theme.accent }}
                  aria-hidden="true"
                />
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
