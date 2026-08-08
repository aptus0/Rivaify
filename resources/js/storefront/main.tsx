import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { StorefrontApp } from './StorefrontApp';

const container = document.getElementById('root');

if (!container) {
  throw new Error('Root element not found');
}

createRoot(container).render(
  <StrictMode>
    <StorefrontApp />
  </StrictMode>,
);