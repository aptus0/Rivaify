import type { ReactNode } from 'react';

export function DataTable({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`overflow-x-auto ${className}`}>{children}</div>;
}