import { motion } from 'framer-motion';
import { ArrowRight, Sparkles } from 'lucide-react';

const fadeUp = {
  hidden: { opacity: 0, y: 24 },
  visible: (i: number = 0) => ({
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, delay: i * 0.1, ease: [0.22, 1, 0.36, 1] as const },
  }),
};

export function Hero() {
  return (
    <section id="top" className="relative overflow-hidden pt-40 pb-24 lg:pt-48 lg:pb-32">
      <div
        className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[560px] bg-[radial-gradient(60%_50%_at_50%_0%,rgba(255,107,0,0.08),rgba(255,107,0,0)_70%)]"
        aria-hidden="true"
      />

      <div className="mx-auto max-w-4xl px-6 text-center lg:px-8">
        <motion.div
          initial="hidden"
          animate="visible"
          custom={0}
          variants={fadeUp}
          className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-surface-orange px-4 py-1.5 text-sm font-medium text-primary"
        >
          <Sparkles className="h-3.5 w-3.5" strokeWidth={2.5} />
          Rivaify çok yakında
        </motion.div>

        <motion.h1
          initial="hidden"
          animate="visible"
          custom={1}
          variants={fadeUp}
          className="mt-8 text-4xl font-extrabold leading-[1.1] tracking-tight text-dark sm:text-5xl lg:text-6xl"
        >
          E-ticaretin <span className="text-primary">yeni nesli</span> yakında burada.
        </motion.h1>

        <motion.p
          initial="hidden"
          animate="visible"
          custom={2}
          variants={fadeUp}
          className="mt-5 text-lg font-medium text-dark/60 sm:text-xl"
        >
          Mağazanı kur. Sosyal kanallarını bağla. <span className="text-dark">Satışını tek yerden yönet.</span>
        </motion.p>

        <motion.p
          initial="hidden"
          animate="visible"
          custom={3}
          variants={fadeUp}
          className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-dark/50"
        >
          Rivaify; ürünlerini, siparişlerini, müşterilerini ve sosyal satış kanallarını tek bir modern
          platform üzerinden yönetmeni sağlayan yeni nesil e-ticaret altyapısı.
        </motion.p>

        <motion.div
          initial="hidden"
          animate="visible"
          custom={4}
          variants={fadeUp}
          className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
        >
          <a
            href="#erken-erisim"
            className="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary px-7 py-3.5 text-sm font-semibold text-white shadow-[0_8px_24px_-8px_rgba(255,107,0,0.6)] transition-transform hover:-translate-y-0.5 sm:w-auto"
          >
            Erken Erişime Katıl
            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
          </a>
          <a
            href="#platform"
            className="inline-flex w-full items-center justify-center rounded-full border border-dark/10 bg-white px-7 py-3.5 text-sm font-semibold text-dark transition-colors hover:border-dark/20 hover:bg-surface sm:w-auto"
          >
            Rivaify&apos;ı Keşfet
          </a>
        </motion.div>

        <motion.p
          initial="hidden"
          animate="visible"
          custom={5}
          variants={fadeUp}
          className="mt-5 text-xs font-medium uppercase tracking-wide text-dark/35"
        >
          Lansmana özel erken erişim avantajları.
        </motion.p>
      </div>
    </section>
  );
}
