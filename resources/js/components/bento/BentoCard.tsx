import type { ReactNode } from 'react';
import { RivaCard } from '../effects/RivaCard';

type BentoSize = 'sm' | 'md' | 'wide' | 'tall' | 'hero';

// Full literal strings — Tailwind's scanner can't see interpolated class names.
const SIZE_CLASSES: Record<BentoSize, string> = {
  sm: 'sm:col-span-1 lg:col-span-4',
  md: 'sm:col-span-2 lg:col-span-6',
  wide: 'sm:col-span-2 lg:col-span-8',
  tall: 'sm:col-span-1 lg:col-span-4 lg:row-span-2',
  hero: 'sm:col-span-2 lg:col-span-12',
};

interface BentoCardProps {
  size?: BentoSize;
  variant?: 'default' | 'subtle' | 'dark' | 'spectrum';
  className?: string;
  children: ReactNode;
}

export function BentoCard({ size = 'md', variant = 'default', className = '', children }: BentoCardProps) {
  return (
    <RivaCard
      variant={variant}
      intensity="subtle"
      radius="showcase"
      className={`flex flex-col overflow-hidden p-6 ${SIZE_CLASSES[size]} ${className}`}
    >
      {children}
    </RivaCard>
  );
}
