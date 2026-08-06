import { motion } from 'framer-motion';
import type { ReactNode } from 'react';
import { useReducedMotion } from '../../hooks/useReducedMotion';

interface RevealProps {
  children: ReactNode;
  delay?: number;
  y?: number;
  className?: string;
  as?: 'div' | 'span';
}

/** Standard scroll-triggered fade/rise used across every marketing section —
 * animates once per element, and collapses to a plain fade for
 * prefers-reduced-motion instead of skipping the transition class entirely
 * (an instant appear still reads as a jump cut without it). */
export function Reveal({ children, delay = 0, y = 24, className, as = 'div' }: RevealProps) {
  const reducedMotion = useReducedMotion();
  const Component = as === 'span' ? motion.span : motion.div;

  return (
    <Component
      initial={{ opacity: 0, y: reducedMotion ? 0 : y }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.2 }}
      transition={{ duration: reducedMotion ? 0.2 : 0.6, delay: reducedMotion ? 0 : delay, ease: [0.22, 1, 0.36, 1] }}
      className={className}
    >
      {children}
    </Component>
  );
}
