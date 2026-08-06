import type { ReactNode } from 'react';

export function TableToolbar({ children }: { children: ReactNode }) {
  return <div className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-center lg:justify-between">{children}</div>;
}