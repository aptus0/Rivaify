import { motion } from 'framer-motion';
import { LayoutGrid, TrendingUp, Zap, type LucideIcon } from 'lucide-react';

interface Pillar {
  icon: LucideIcon;
  title: string;
  description: string;
}

const PILLARS: Pillar[] = [
  {
    icon: Zap,
    title: 'Hızlı Kurulum',
    description: 'Dakikalar içinde mağazanı oluştur, ürünlerini ekle ve satışa hazır hale getir.',
  },
  {
    icon: LayoutGrid,
    title: 'Tek Panel',
    description: 'Ürünler, siparişler ve sosyal satış kanalların tek bir modern arayüzde birleşir.',
  },
  {
    icon: TrendingUp,
    title: 'Büyümeye Hazır',
    description: 'İşin büyüdükçe seninle birlikte ölçeklenen sağlam ve modern bir altyapı.',
  },
];

export function WhyRivaify() {
  return (
    <section id="hakkimizda" className="px-6 py-24 lg:px-8 lg:py-32">
      <div className="mx-auto max-w-6xl">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          className="mx-auto max-w-2xl text-center"
        >
          <h2 className="text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
            E-ticaret <span className="text-primary">daha kolay olabilir.</span>
          </h2>
        </motion.div>

        <div className="mt-16 grid grid-cols-1 gap-10 sm:grid-cols-3">
          {PILLARS.map((pillar, index) => (
            <motion.div
              key={pillar.title}
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
              className="flex flex-col items-center text-center sm:items-start sm:text-left"
            >
              <span className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-dark text-primary-soft">
                <pillar.icon className="h-5 w-5" strokeWidth={2} />
              </span>
              <h3 className="mt-5 text-lg font-bold text-dark">{pillar.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-dark/50">{pillar.description}</p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
