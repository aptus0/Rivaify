import { motion } from 'framer-motion';
import { Package, Rocket, Share2, TrendingUp, type LucideIcon } from 'lucide-react';

interface Step {
  icon: LucideIcon;
  title: string;
  description: string;
}

const STEPS: Step[] = [
  {
    icon: Rocket,
    title: 'Mağazanı kur',
    description: 'Birkaç adımda hesabını oluştur, mağaza bilgilerini gir ve Rivaify’ı satışa hazırla.',
  },
  {
    icon: Package,
    title: 'Ürünlerini ekle',
    description: 'Ürünlerini, varyantlarını ve stoklarını tek panelden yükle.',
  },
  {
    icon: Share2,
    title: 'Kanallarını bağla',
    description: 'Instagram, Facebook ve TikTok hesaplarını mağazana bağlayıp satışa aç.',
  },
  {
    icon: TrendingUp,
    title: 'Satışa başla',
    description: 'Siparişlerini tek ekrandan yönet, büyümeni gerçek zamanlı takip et.',
  },
];

export function HowItWorks() {
  return (
    <section id="nasil-calisir" className="px-6 py-24 lg:px-8 lg:py-32">
      <div className="mx-auto max-w-6xl">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          className="mx-auto max-w-2xl text-center"
        >
          <h2 className="text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
            Dört adımda <span className="text-primary">satışa hazırsın.</span>
          </h2>
        </motion.div>

        <div className="relative mt-16 grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
          <div
            className="pointer-events-none absolute top-6 right-0 left-0 hidden h-px bg-[linear-gradient(to_right,transparent,rgba(17,17,17,0.1)_10%,rgba(17,17,17,0.1)_90%,transparent)] lg:block"
            aria-hidden="true"
          />

          {STEPS.map((step, index) => (
            <motion.div
              key={step.title}
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
              className="relative flex flex-col items-start"
            >
              <span className="relative z-10 flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-dark/[0.08] bg-white text-primary shadow-[0_4px_16px_-4px_rgba(17,17,17,0.1)]">
                <step.icon className="h-5 w-5" strokeWidth={2} />
              </span>
              <span className="mt-4 text-xs font-bold uppercase tracking-wider text-primary/70">
                Adım {index + 1}
              </span>
              <h3 className="mt-1.5 text-base font-bold text-dark">{step.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-dark/50">{step.description}</p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
