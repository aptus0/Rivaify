import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
  // Non-eager glob — each of the 15 marketing pages becomes its own chunk,
  // fetched only when actually navigated to, instead of one ~560KB bundle
  // containing every page up front. Inertia's ComponentResolver wants the
  // component itself (or a Promise of it) back, not the module namespace
  // object resolvePageComponent returns, hence the `.then(m => m.default)`.
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx'),
    ).then((module) => module.default),
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
