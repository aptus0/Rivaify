import { motion } from 'framer-motion';
import { BarChart3, PackageSearch, Share2, ShoppingCart, type LucideIcon } from 'lucide-react';

interface Feature {
  icon: LucideIcon;
  title: string;
  description: string;
  span: 'wide' | 'narrow';
}

const FEATURES: Feature[] = [
  {
    icon: PackageSearch,
    title: 'Mağaza Yönetimi',
    description:
      'Ürünlerini, varyantlarını, stoklarını ve tüm mağaza operasyonunu tek panelden yönet — ayrı sistemler arasında geçiş yapmana gerek kalmaz.',
    span: 'wide',
  },
  {
    icon: Share2,
    title: 'Sosyal Ticaret',
    description: 'Instagram, Facebook ve TikTok satış kanallarını mağazana bağla.',
    span: 'narrow',
  },
  {
    icon: ShoppingCart,
    title: 'Siparişler',
    description: 'Tüm siparişlerini tek ekrandan takip et ve yönet.',
    span: 'narrow',
  },
  {
    icon: BarChart3,
    title: 'Analitik',
    description:
      'Satışlarını, müşterilerini ve büyümeni gerçek zamanlı analiz et; hangi kanalın işe yaradığını net olarak gör.',
    span: 'wide',
  },
];

export function Features() {
  return (
    <section id="ozellikler" className="px-6 py-24 lg:px-8 lg:py-32">
      <div className="mx-auto max-w-6xl">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          className="mx-auto max-w-2xl text-center"
        >
          <h2 className="text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
            Satış yapmak için ihtiyacın olan <span className="text-primary">her şey.</span>
          </h2>
        </motion.div>

        <div className="mt-16 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((feature, index) => (
            <motion.div
              key={feature.title}
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
              className={`group relative overflow-hidden rounded-2xl border border-dark/[0.07] bg-white p-7 transition-shadow hover:shadow-[0_16px_40px_-16px_rgba(17,17,17,0.15)] ${
                feature.span === 'wide' ? 'lg:col-span-2' : 'lg:col-span-1'
              }`}
            >
              <feature.icon
                className="pointer-events-none absolute -right-4 -top-4 h-28 w-28 text-primary/[0.04] transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6"
                strokeWidth={1}
                aria-hidden="true"
              />
              <span className="relative inline-flex h-11 w-11 items-center justify-center rounded-xl bg-surface-orange text-primary transition-transform group-hover:-translate-y-0.5">
                <feature.icon className="h-5 w-5" strokeWidth={2} />
              </span>
              <h3 className="relative mt-5 text-base font-bold text-dark">{feature.title}</h3>
              <p className="relative mt-2 max-w-md text-sm leading-relaxed text-dark/50">
                {feature.description}
              </p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
