import { useEffect, useRef, useState } from 'react';

/**
 * Thin IntersectionObserver wrapper for one-off "has this scrolled into view
 * yet" needs (e.g. starting the StoreBuilder demo loop only once visible).
 * Section reveal animations use Motion's own `whileInView` instead — this is
 * for plain DOM/state-driven effects that don't already go through Motion.
 */
export function useIntersection<T extends HTMLElement>(options?: IntersectionObserverInit) {
  const ref = useRef<T>(null);
  const [isIntersecting, setIsIntersecting] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        setIsIntersecting(true);
        observer.disconnect();
      }
    }, options ?? { threshold: 0.3 });

    observer.observe(el);
    return () => observer.disconnect();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return { ref, isIntersecting };
}
