import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useIntersection } from '../../hooks/useIntersection';

interface TracingBeamProps {
  children: ReactNode;
  className?: string;
}

/**
 * A subtle vertical connector line that draws itself in once scrolled into
 * view (via stroke-dashoffset, not a live scroll-scrub — simpler and jank-
 * free, and the brief only asks for "very subtle"). Consumers lay out their
 * own module rows as children with left padding to clear the line; this
 * just measures their height and draws the beam behind them.
 */
export function TracingBeam({ children, className = '' }: TracingBeamProps) {
  const contentRef = useRef<HTMLDivElement>(null);
  const { ref, isIntersecting } = useIntersection<HTMLDivElement>({ threshold: 0.15 });
  const [height, setHeight] = useState(0);

  useEffect(() => {
    const el = contentRef.current;
    if (!el) return;
    const observer = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (entry) setHeight(entry.contentRect.height);
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div ref={ref} className={`relative ${className}`}>
      <svg className="pointer-events-none absolute left-0 top-0 -z-10 h-full w-6 motion-reduce:hidden" aria-hidden="true">
        <defs>
          <linearGradient id="tracing-beam-gradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#FF6B00" />
            <stop offset="50%" stopColor="#7957FF" />
            <stop offset="100%" stopColor="#20C7C7" />
          </linearGradient>
        </defs>
        <line
          x1="12"
          y1="0"
          x2="12"
          y2={height}
          stroke="url(#tracing-beam-gradient)"
          strokeWidth="2"
          strokeLinecap="round"
          strokeDasharray={height || 1}
          strokeDashoffset={isIntersecting ? 0 : height || 1}
          style={{ transition: 'stroke-dashoffset 1.6s cubic-bezier(0.22, 1, 0.36, 1)' }}
        />
      </svg>
      <div ref={contentRef}>{children}</div>
    </div>
  );
}
