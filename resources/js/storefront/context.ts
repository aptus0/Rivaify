import { useOutletContext } from 'react-router-dom';
import type { StorefrontRuntime, StorefrontStore } from './types';

export interface StorefrontContext {
  store: StorefrontStore;
  runtime: StorefrontRuntime;
}

export function useStorefront(): StorefrontContext {
  return useOutletContext<StorefrontContext>();
}
