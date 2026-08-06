import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Navigation } from '../components/marketing/Navigation/Navigation';
import { Footer } from '../components/marketing/Footer/Footer';
import { useReducedMotion } from '../hooks/useReducedMotion';

interface MarketingLayoutProps {
  children: ReactNode;
  /** Client-side navigations (Inertia) don't re-run the Blade template, so
   * <Head> here is what keeps the tab title/description correct after the
   * first load — resources/views/app.blade.php handles the *first* load
   * (and therefore crawlers/OG scrapers) from the same `seo` route prop. */
  title?: string;
  description?: string;
}

/** Shared shell for all 15 marketing pages. The fade/rise on `main` replays
 * on every Inertia navigation because swapping to a different page
 * component unmounts/remounts this layout along with it — no manual
 * AnimatePresence wiring needed for that. */
export function MarketingLayout({ children, title, description }: MarketingLayoutProps) {
  const reducedMotion = useReducedMotion();

  return (
    <div className="min-h-screen bg-[#050505] font-sans text-white/90 selection:bg-primary/30 selection:text-white">
      {(title || description) && (
        <Head>
          {title && <title>{title}</title>}
          {description && <meta name="description" content={description} />}
        </Head>
      )}
      <Navigation />
      <motion.main
        initial={{ opacity: 0, y: reducedMotion ? 0 : 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: reducedMotion ? 0.15 : 0.35, ease: [0.22, 1, 0.36, 1] }}
      >
        {children}
      </motion.main>
      <Footer />
    </div>
  );
}
