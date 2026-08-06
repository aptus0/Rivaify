import { useEffect, useRef, useState, type ReactNode } from 'react';

interface DropdownProps {
  trigger: (props: { open: boolean; toggle: () => void }) => ReactNode;
  children: (props: { close: () => void }) => ReactNode;
  align?: 'left' | 'right';
  panelClassName?: string;
}

/** Minimal headless dropdown (no new dependency) backing StoreSwitcher and
 * UserMenu — closes on outside click or Escape. */
export function Dropdown({ trigger, children, align = 'left', panelClassName = '' }: DropdownProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    function handlePointerDown(event: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [open]);

  return (
    <div ref={rootRef} className="relative">
      {trigger({ open, toggle: () => setOpen((value) => !value) })}
      {open && (
        <div
          className={`absolute z-20 mt-2 min-w-56 rounded-lg border border-border bg-card py-1 shadow-lg ${
            align === 'right' ? 'right-0' : 'left-0'
          } ${panelClassName}`}
        >
          {children({ close: () => setOpen(false) })}
        </div>
      )}
    </div>
  );
}
