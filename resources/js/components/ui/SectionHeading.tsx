import type { ReactNode } from 'react';
import { Reveal } from '../effects/Reveal';

interface SectionHeadingProps {
  eyebrow?: string;
  title: ReactNode;
  description?: ReactNode;
  align?: 'center' | 'left';
  onDark?: boolean;
  className?: string;
}

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = 'center',
  onDark = true,
  className = '',
}: SectionHeadingProps) {
  const alignClass = align === 'center' ? 'mx-auto text-center items-center' : 'text-left items-start';
  const mutedClass = onDark ? 'text-white/55' : 'text-dark/50';

  return (
    <Reveal className={`flex max-w-2xl flex-col ${alignClass} ${className}`}>
      {eyebrow && (
        <span
          className={`mb-4 inline-flex items-center rounded-full border px-3.5 py-1 text-xs font-semibold uppercase tracking-wide ${
            onDark ? 'border-white/15 bg-white/[0.06] text-primary-soft' : 'border-primary/20 bg-surface-orange text-primary'
          }`}
        >
          {eyebrow}
        </span>
      )}
      <h2
        className={`text-3xl font-extrabold leading-[1.15] tracking-tight sm:text-4xl ${
          onDark ? 'text-white' : 'text-dark'
        }`}
      >
        {title}
      </h2>
      {description && <p className={`mt-4 text-base leading-relaxed ${mutedClass}`}>{description}</p>}
    </Reveal>
  );
}
