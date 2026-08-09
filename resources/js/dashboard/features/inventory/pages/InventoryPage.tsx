import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Boxes, CircleAlert, PackageOpen, PencilLine, Search, Warehouse, X } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { Pagination } from '../../../components/ui/Pagination';
import { SearchInput } from '../../../components/ui/SearchInput';
import { ApiError } from '../../../lib/api';
import { adjustInventory, listInventory } from '../api/inventoryApi';
import type {
  InventoryItem,
  InventoryLocation,
  InventoryStatus,
  InventorySummary,
  PaginationMeta,
} from '../api/types';

const EMPTY_SUMMARY: InventorySummary = {
  tracked_variants: 0,
  available: 0,
  reserved: 0,
  sellable: 0,
  incoming: 0,
  low_stock: 0,
  out_of_stock: 0,
};

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

const numberFormatter = new Intl.NumberFormat('tr-TR');

function statusLabel(status: InventoryStatus): string {
  if (status === 'in_stock') return 'Stokta';
  if (status === 'low_stock') return 'Az stok';

  return 'Stokta yok';
}

function statusTone(status: InventoryStatus): 'success' | 'warning' | 'neutral' {
  if (status === 'in_stock') return 'success';
  if (status === 'low_stock') return 'warning';

  return 'neutral';
}

export function InventoryPage() {
  usePageTitle('Envanter');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<InventoryStatus | ''>('');
  const [locationId, setLocationId] = useState('');
  const [items, setItems] = useState<InventoryItem[]>([]);
  const [locations, setLocations] = useState<InventoryLocation[]>([]);
  const [summary, setSummary] = useState<InventorySummary>(EMPTY_SUMMARY);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_META);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingItem, setEditingItem] = useState<InventoryItem | null>(null);
  const [reloadVersion, setReloadVersion] = useState(0);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setMeta((current) => ({ ...current, current_page: 1 }));
      setSearch(searchInput.trim());
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);

    void listInventory({
      q: search || undefined,
      status: status || undefined,
      location_id: locationId || undefined,
      page: meta.current_page,
    })
      .then((response) => {
        if (!active) return;
        setItems(response.data);
        setLocations(response.locations);
        setSummary(response.summary);
        setMeta(response.meta);
      })
      .catch((requestError: unknown) => {
        if (!active) return;
        setError(requestError instanceof ApiError ? 'Envanter verileri yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [locationId, meta.current_page, reloadVersion, search, status]);

  const metrics = useMemo(() => [
    { label: 'Satılabilir stok', value: summary.sellable, detail: `${summary.tracked_variants} takip edilen varyant`, icon: Boxes, tone: 'text-primary' },
    { label: 'Rezerve', value: summary.reserved, detail: 'Aktif ödeme oturumlarında', icon: Warehouse, tone: 'text-sky-600' },
    { label: 'Az stok', value: summary.low_stock, detail: '1–5 adet satılabilir', icon: CircleAlert, tone: 'text-amber-600' },
    { label: 'Stokta yok', value: summary.out_of_stock, detail: `${numberFormatter.format(summary.incoming)} adet yolda`, icon: PackageOpen, tone: 'text-red-600' },
  ], [summary]);

  function refreshInventory() {
    setReloadVersion((version) => version + 1);
  }

  return (
    <div className="mx-auto max-w-7xl space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-xl font-semibold text-dark">Envanter merkezi</h2>
          <p className="mt-1 text-sm text-muted">Lokasyon bazında kullanılabilir, rezerve ve satılabilir stokları yönetin.</p>
        </div>
        <Link to="/products" className="self-start sm:self-auto">
          <Button fullWidth={false} variant="secondary">Ürünlere Git</Button>
        </Link>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map(({ label, value, detail, icon: Icon, tone }) => (
          <Card key={label} className="p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</p>
                <p className="mt-2 text-2xl font-semibold text-dark">{numberFormatter.format(value)}</p>
                <p className="mt-1 text-xs text-muted">{detail}</p>
              </div>
              <span className={`rounded-lg bg-app-bg p-2 ${tone}`}><Icon size={19} /></span>
            </div>
          </Card>
        ))}
      </div>

      <Card className="p-0">
        <div className="grid gap-3 border-b border-border p-4 lg:grid-cols-[minmax(0,1fr)_12rem_14rem]">
          <SearchInput
            value={searchInput}
            onChange={setSearchInput}
            placeholder="Ürün, varyant, SKU veya barkod ara..."
          />
          <select
            value={status}
            onChange={(event) => {
              setMeta((current) => ({ ...current, current_page: 1 }));
              setStatus(event.target.value as InventoryStatus | '');
            }}
            aria-label="Stok durumu"
            className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
          >
            <option value="">Tüm stok durumları</option>
            <option value="in_stock">Stokta</option>
            <option value="low_stock">Az stok</option>
            <option value="out_of_stock">Stokta yok</option>
          </select>
          <select
            value={locationId}
            onChange={(event) => {
              setMeta((current) => ({ ...current, current_page: 1 }));
              setLocationId(event.target.value);
            }}
            aria-label="Stok lokasyonu"
            className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
          >
            <option value="">Tüm lokasyonlar</option>
            {locations.map((location) => <option key={location.id} value={location.id}>{location.name}</option>)}
          </select>
        </div>

        {error && (
          <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3 text-sm text-red-600">
            <span>{error}</span>
            <button onClick={refreshInventory} className="font-medium hover:underline">Tekrar dene</button>
          </div>
        )}

        {loading ? (
          <InventorySkeleton />
        ) : items.length === 0 ? (
          <EmptyState
            icon={Search}
            title={search || status || locationId ? 'Bu filtrelerle envanter kaydı bulunamadı.' : 'Takip edilen envanter henüz yok.'}
            description={search || status || locationId ? 'Arama veya filtreleri değiştirerek tekrar deneyin.' : 'Bir üründe stok takibini açıp başlangıç adedini girdiğinizde burada görünecek.'}
            action={!search && !status && !locationId ? <Link to="/products/create"><Button fullWidth={false}>İlk Ürünü Ekle</Button></Link> : undefined}
          />
        ) : (
          <InventoryTable items={items} onEdit={setEditingItem} />
        )}

        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          onChange={(page) => setMeta((current) => ({ ...current, current_page: page }))}
        />
      </Card>

      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
        <span>{numberFormatter.format(meta.total)} envanter kaydı</span>
        <span>Toplam fiziksel adet: {numberFormatter.format(summary.available)} · Rezerve: {numberFormatter.format(summary.reserved)}</span>
      </div>

      {editingItem && (
        <AdjustmentDialog
          item={editingItem}
          locations={locations}
          preferredLocationId={locationId}
          onClose={() => setEditingItem(null)}
          onSaved={() => {
            setEditingItem(null);
            refreshInventory();
          }}
        />
      )}
    </div>
  );
}

function InventoryTable({ items, onEdit }: { items: InventoryItem[]; onEdit: (item: InventoryItem) => void }) {
  return (
    <>
      <div className="hidden overflow-x-auto lg:block">
        <table className="min-w-[920px] w-full text-left text-sm">
          <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted">
            <tr>
              <th className="px-4 py-3">Ürün / Varyant</th>
              <th className="px-4 py-3">SKU</th>
              <th className="px-4 py-3">Lokasyon</th>
              <th className="px-4 py-3 text-right">Mevcut</th>
              <th className="px-4 py-3 text-right">Rezerve</th>
              <th className="px-4 py-3 text-right">Satılabilir</th>
              <th className="px-4 py-3">Durum</th>
              <th className="px-4 py-3"><span className="sr-only">İşlem</span></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {items.map((item) => (
              <tr key={item.id} className="hover:bg-app-bg/60">
                <td className="px-4 py-4">
                  <Link to={`/products/${item.product.id}`} className="font-medium text-dark hover:text-primary-hover">{item.product.title}</Link>
                  <p className="mt-0.5 text-xs text-muted">{item.variant.title}</p>
                </td>
                <td className="px-4 py-4 text-muted">{item.variant.sku ?? '-'}</td>
                <td className="px-4 py-4">
                  <p className="text-dark">{item.levels[0]?.location.name ?? 'Henüz atanmadı'}</p>
                  {item.levels.length > 1 && <p className="mt-0.5 text-xs text-muted">+{item.levels.length - 1} lokasyon daha</p>}
                </td>
                <td className="px-4 py-4 text-right text-dark">{numberFormatter.format(item.quantities.available)}</td>
                <td className="px-4 py-4 text-right text-muted">{numberFormatter.format(item.quantities.reserved)}</td>
                <td className="px-4 py-4 text-right font-semibold text-dark">{numberFormatter.format(item.quantities.sellable)}</td>
                <td className="px-4 py-4"><Badge tone={statusTone(item.status)}>{statusLabel(item.status)}</Badge></td>
                <td className="px-4 py-4 text-right">
                  <Button fullWidth={false} variant="secondary" onClick={() => onEdit(item)}><PencilLine size={15} />Stok Ayarla</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="divide-y divide-border lg:hidden">
        {items.map((item) => (
          <div key={item.id} className="space-y-3 p-4">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <Link to={`/products/${item.product.id}`} className="block truncate font-medium text-dark">{item.product.title}</Link>
                <p className="mt-0.5 truncate text-xs text-muted">{item.variant.title} · {item.variant.sku ?? 'SKU yok'}</p>
              </div>
              <Badge tone={statusTone(item.status)}>{statusLabel(item.status)}</Badge>
            </div>
            <div className="grid grid-cols-3 gap-2 rounded-md bg-app-bg p-3 text-center">
              <Quantity label="Mevcut" value={item.quantities.available} />
              <Quantity label="Rezerve" value={item.quantities.reserved} />
              <Quantity label="Satılabilir" value={item.quantities.sellable} strong />
            </div>
            <Button fullWidth={false} variant="secondary" onClick={() => onEdit(item)}><PencilLine size={15} />Stok Ayarla</Button>
          </div>
        ))}
      </div>
    </>
  );
}

function Quantity({ label, value, strong = false }: { label: string; value: number; strong?: boolean }) {
  return <div><p className="text-[11px] text-muted">{label}</p><p className={`mt-1 text-sm ${strong ? 'font-semibold text-dark' : 'text-dark'}`}>{numberFormatter.format(value)}</p></div>;
}

function AdjustmentDialog({
  item,
  locations,
  preferredLocationId,
  onClose,
  onSaved,
}: {
  item: InventoryItem;
  locations: InventoryLocation[];
  preferredLocationId: string;
  onClose: () => void;
  onSaved: () => void;
}) {
  const initialLocationId = preferredLocationId || item.levels[0]?.location.id || locations[0]?.id || '';
  const [selectedLocationId, setSelectedLocationId] = useState(initialLocationId);
  const [quantity, setQuantity] = useState(() => String(item.levels.find((level) => level.location.id === initialLocationId)?.available ?? 0));
  const [reason, setReason] = useState('Manuel stok sayımı');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function selectLocation(nextLocationId: string) {
    setSelectedLocationId(nextLocationId);
    setQuantity(String(item.levels.find((level) => level.location.id === nextLocationId)?.available ?? 0));
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const availableQuantity = Number(quantity);
    if (!Number.isInteger(availableQuantity) || availableQuantity < 0) {
      setError('Stok adedi 0 veya daha büyük bir tam sayı olmalıdır.');
      return;
    }
    if (!selectedLocationId) {
      setError('Stok lokasyonu seçilmelidir.');
      return;
    }

    setSaving(true);
    setError(null);
    try {
      await adjustInventory(item.id, selectedLocationId, {
        available_quantity: availableQuantity,
        reason: reason.trim() || undefined,
      });
      onSaved();
    } catch (requestError) {
      if (requestError instanceof ApiError && requestError.status === 422) {
        setError('Mevcut adet rezerve stoktan düşük olamaz. Rezervasyonlar tamamlandıktan sonra tekrar deneyin.');
      } else {
        setError('Stok güncellenemedi. Lütfen tekrar deneyin.');
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-dark/35 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="inventory-adjustment-title">
      <div className="w-full rounded-t-xl border border-border bg-card shadow-xl sm:max-w-lg sm:rounded-xl">
        <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
          <div>
            <h3 id="inventory-adjustment-title" className="font-semibold text-dark">Stok adedini ayarla</h3>
            <p className="mt-1 text-sm text-muted">{item.product.title} · {item.variant.title}</p>
          </div>
          <button onClick={onClose} className="rounded p-1 text-muted hover:bg-app-bg hover:text-dark" aria-label="Kapat"><X size={19} /></button>
        </div>

        <form onSubmit={submit} className="space-y-4 p-5">
          <label className="block text-sm font-medium text-dark">
            Stok lokasyonu
            <select
              value={selectedLocationId}
              onChange={(event) => selectLocation(event.target.value)}
              required
              className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
            >
              <option value="" disabled>Lokasyon seçin</option>
              {locations.map((location) => <option key={location.id} value={location.id}>{location.name}{location.code ? ` (${location.code})` : ''}</option>)}
            </select>
          </label>

          <label className="block text-sm font-medium text-dark">
            Fiziksel mevcut adet
            <input
              type="number"
              min="0"
              step="1"
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
              required
              className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
            />
          </label>

          <label className="block text-sm font-medium text-dark">
            Değişiklik nedeni
            <input
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              maxLength={255}
              className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
            />
          </label>

          <p className="rounded-md bg-app-bg px-3 py-2 text-xs leading-5 text-muted">
            Rezerve adetler korunur. Yeni mevcut adet, bu lokasyondaki rezerve stoktan daha düşük olamaz; her değişiklik envanter hareketlerine kaydedilir.
          </p>
          {error && <p className="text-sm text-red-600">{error}</p>}

          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <Button fullWidth={false} type="button" variant="secondary" onClick={onClose} disabled={saving}>Vazgeç</Button>
            <Button fullWidth={false} type="submit" disabled={saving || locations.length === 0}>{saving ? 'Kaydediliyor…' : 'Stoku Kaydet'}</Button>
          </div>
        </form>
      </div>
    </div>
  );
}

function InventorySkeleton() {
  return <div className="space-y-3 p-4">{Array.from({ length: 6 }, (_, index) => <div key={index} className="h-16 animate-pulse rounded-md bg-app-bg" />)}</div>;
}
