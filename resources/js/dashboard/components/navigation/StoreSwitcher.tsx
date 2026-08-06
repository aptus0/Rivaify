import { Check, ChevronDown, Plus } from 'lucide-react';
import { Dropdown } from '../ui/Dropdown';
import type { CurrentStoreSummary } from '../../types';

/**
 * Sprint 01 hard-enforces one store per merchant (CreateStore throws
 * MerchantAlreadyHasStoreException on a second store), and the backend has
 * no switch-store endpoint yet — so this only ever lists the single current
 * store. The shell is built now (brief §9/§11) so wiring real multi-store
 * data later is a data change, not a UI rewrite.
 */
export function StoreSwitcher({ store }: { store: CurrentStoreSummary }) {
  return (
    <Dropdown
      trigger={({ toggle }) => (
        <button
          onClick={toggle}
          className="flex items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-app-bg"
        >
          <div>
            <p className="text-sm font-medium leading-tight text-dark">{store.name}</p>
            <p className="text-xs leading-tight text-muted">{store.slug}.rivaify.com</p>
          </div>
          <ChevronDown size={16} className="text-muted" />
        </button>
      )}
    >
      {({ close }) => (
        <>
          <p className="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-muted">
            Mağazalar
          </p>
          <button
            onClick={close}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"
          >
            <Check size={14} className="text-primary" />
            {store.name}
          </button>

          <div className="my-1 border-t border-border" />

          <button
            disabled
            title="Çoklu mağaza yakında"
            className="flex w-full cursor-not-allowed items-center gap-2 px-3 py-2 text-left text-sm text-muted"
          >
            <Plus size={14} />
            Yeni mağaza oluştur
          </button>
        </>
      )}
    </Dropdown>
  );
}
