import type { ReactNode } from 'react';

export function CenteredLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen justify-center bg-neutral-50 px-4">
      <div className="w-full">{children}</div>
    </div>
  );
}
