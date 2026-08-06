import type { ReactNode } from 'react';

const WIDTHS = {
  default: 'max-w-6xl',
  narrow: 'max-w-4xl',
  wide: 'max-w-7xl',
} as const;

interface ContainerProps {
  size?: keyof typeof WIDTHS;
  className?: string;
  children: ReactNode;
}

export function Container({ size = 'default', className = '', children }: ContainerProps) {
  return <div className={`mx-auto ${WIDTHS[size]} px-6 lg:px-8 ${className}`}>{children}</div>;
}
