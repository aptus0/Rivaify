import type { ReactNode } from 'react';

interface BentoVisualProps {
  label?: string;
  children: ReactNode;
  className?: string;
}

/** Small "window chrome" frame for embedding a real mini product mockup
 * inside a bento tile — brief: "no meaningless icon-only cards." */
export function BentoVisual({ label, children, className = '' }: BentoVisualProps) {
  return (
    <div className={`overflow-hidden rounded-control border border-dark/[0.07] bg-white ${className}`}>
      {label && (
        <div className="flex items-center gap-1.5 border-b border-dark/[0.06] bg-surface px-3 py-2">
          <span className="h-1.5 w-1.5 rounded-full bg-dark/15" />
          <span className="h-1.5 w-1.5 rounded-full bg-dark/15" />
          <span className="h-1.5 w-1.5 rounded-full bg-dark/15" />
          <span className="ml-2 text-[10px] font-medium text-dark/35">{label}</span>
        </div>
      )}
      <div className="p-3">{children}</div>
    </div>
  );
}
