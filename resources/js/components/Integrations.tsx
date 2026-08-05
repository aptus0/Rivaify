import { motion } from 'framer-motion';
import { Camera, ThumbsUp, Video, type LucideIcon } from 'lucide-react';

interface Channel {
  icon: LucideIcon;
  name: string;
  description: string;
}

const CHANNELS: Channel[] = [
  { icon: Camera, name: 'Instagram', description: 'Gönderi ve hikayelerden doğrudan satış' },
  { icon: ThumbsUp, name: 'Facebook', description: 'Mağaza ve sayfa entegrasyonu' },
  { icon: Video, name: 'TikTok', description: 'Kısa video üzerinden ürün keşfi' },
];

export function Integrations() {
  return (
    <section id="entegrasyonlar" className="bg-soft-dark px-6 py-24 text-white lg:px-8 lg:py-32">
      <div className="mx-auto max-w-6xl">
        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:items-center">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          >
            <h2 className="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
              Sosyal medya artık sadece <span className="text-primary-soft">pazarlama kanalı değil.</span>
            </h2>
            <p className="mt-5 max-w-md text-base leading-relaxed text-white/50">
              Rivaify ile sosyal kanallarını doğrudan satış kanalına dönüştür.
            </p>
          </motion.div>

          <div className="flex flex-col gap-3">
            {CHANNELS.map((channel, index) => (
              <motion.div
                key={channel.name}
                initial={{ opacity: 0, x: 24 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
                className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5 transition-colors hover:border-primary/30"
              >
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/[0.06] text-primary-soft">
                  <channel.icon className="h-5 w-5" strokeWidth={2} />
                </span>
                <div>
                  <p className="text-sm font-bold text-white">{channel.name}</p>
                  <p className="text-xs text-white/45">{channel.description}</p>
                </div>
              </motion.div>
            ))}

            <p className="mt-2 text-xs font-medium uppercase tracking-wide text-white/30">
              Daha fazla entegrasyon yakında.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
