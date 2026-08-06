import { useCallback, useRef } from 'react';

/**
 * Binds --mouse-x/--mouse-y CSS custom properties on an element, updated only
 * while the pointer is inside it (onPointerMove is a React event handler
 * scoped to that element — never a global window listener), for CSS-driven
 * pointer-aware effects like AuraCard's halo. Writes via ref.style directly
 * instead of React state so movement never triggers a re-render.
 */
export function usePointerPosition<T extends HTMLElement>() {
  const ref = useRef<T>(null);

  const handlePointerMove = useCallback((event: React.PointerEvent<T>) => {
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    el.style.setProperty('--mouse-x', `${event.clientX - rect.left}px`);
    el.style.setProperty('--mouse-y', `${event.clientY - rect.top}px`);
  }, []);

  const handlePointerLeave = useCallback(() => {
    const el = ref.current;
    if (!el) return;
    el.style.removeProperty('--mouse-x');
    el.style.removeProperty('--mouse-y');
  }, []);

  return { ref, handlePointerMove, handlePointerLeave };
}
