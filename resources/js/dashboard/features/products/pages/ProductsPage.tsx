import { useDeferredValue, useEffect, useState } from 'react';
import { Archive, CheckSquare, ChevronDown, Copy, Download, Filter, Plus, Upload, X } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { DataTable } from '../../../components/ui/DataTable';
import { EmptyState } from '../../../components/ui/EmptyState';
import { Pagination } from '../../../components/ui/Pagination';
import { SearchInput } from '../../../components/ui/SearchInput';
import { TableToolbar } from '../../../components/ui/TableToolbar';
import { ApiError } from '../../../lib/api';
import { bulkUpdateProducts, duplicateProduct, getCatalogOrganization, listProducts } from '../api/productsApi';
import type { CatalogOrganization, InventoryStatus, ProductStatus, ProductSummary, ProductSummaryCounts } from '../api/types';
import { ProductStatusBadge } from '../components/ProductStatusBadge';

const EMPTY_COUNTS: ProductSummaryCounts = { all: 0, active: 0, draft: 0, archived: 0, out_of_stock: 0, low_stock: 0 };

type ProductFilters = {
  status: ProductStatus | '';
  categoryId: string;
  brandId: string;
  productType: string;
  inventoryStatus: InventoryStatus | '';
};

const EMPTY_FILTERS: ProductFilters = { status: '', categoryId: '', brandId: '', productType: '', inventoryStatus: '' };

function formatDate(value: string | null): string {
  if (!value) return '-';

  return new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

function inventoryLabel(product: ProductSummary): string {
  if (!product.inventory.is_tracked) return 'Takip edilmiyor';
  if (product.inventory.status === 'out_of_stock') return 'Stokta yok';
  if (product.inventory.status === 'low_stock') return `${product.inventory.sellable} az stok`;

  return `${product.inventory.sellable} stok`;
}

function inventoryTone(status: InventoryStatus): 'success' | 'warning' | 'neutral' {
  return status === 'in_stock' ? 'success' : status === 'low_stock' ? 'warning' : 'neutral';
}

export function ProductsPage() {
  usePageTitle('Ürünler');
  const [searchInput, setSearchInput] = useState('');
  const deferredSearch = useDeferredValue(searchInput);
  const [filters, setFilters] = useState<ProductFilters>(EMPTY_FILTERS);
  const [catalog, setCatalog] = useState<CatalogOrganization | null>(null);
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [counts, setCounts] = useState<ProductSummaryCounts>(EMPTY_COUNTS);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [bulkBusy, setBulkBusy] = useState(false);

  useEffect(() => {
    void getCatalogOrganization().then((response) => setCatalog(response.data)).catch(() => setCatalog(null));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listProducts({
      q: deferredSearch.trim() || undefined,
      status: filters.status || undefined,
      category_id: filters.categoryId || undefined,
      brand_id: filters.brandId || undefined,
      product_type: filters.productType || undefined,
      inventory_status: filters.inventoryStatus || undefined,
      page: String(page),
    })
      .then((response) => {
        if (!active) return;
        setProducts(response.data);
        setCounts(response.summary);
        setLastPage(response.meta.last_page);
        setTotal(response.meta.total);
        setSelected(new Set());
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof ApiError ? 'Ürünler yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [deferredSearch, filters, page]);

  function setFilter<K extends keyof ProductFilters>(key: K, value: ProductFilters[K]) {
    setPage(1);
    setFilters((current) => ({ ...current, [key]: value }));
  }

  function toggleSelected(id: string) {
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);

      return next;
    });
  }

  async function applyBulk(action: 'activate' | 'draft' | 'archive' | 'delete') {
    if (selected.size === 0) return;
    if (action === 'delete' && !window.confirm(`${selected.size} ürün silinsin mi?`)) return;
    setBulkBusy(true);
    try {
      await bulkUpdateProducts({ product_ids: Array.from(selected), action });
      setPage(1);
      setSelected(new Set());
      const response = await listProducts({ q: deferredSearch.trim() || undefined });
      setProducts(response.data);
      setCounts(response.summary);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
    } catch {
      setError('Toplu işlem tamamlanamadı. Lütfen tekrar deneyin.');
    } finally {
      setBulkBusy(false);
    }
  }

  async function duplicate(productId: string) {
    try {
      const response = await duplicateProduct(productId);
      setProducts((current) => [{ ...response.data, updated_at: new Date().toISOString() }, ...current]);
      setCounts((current) => ({ ...current, all: current.all + 1, draft: current.draft + 1 }));
    } catch {
      setError('Ürün kopyalanamadı.');
    }
  }

  const activeFilters = [
    filters.categoryId && { key: 'categoryId' as const, label: `Kategori: ${catalog?.categories.find((category) => category.id === filters.categoryId)?.name ?? ''}` },
    filters.brandId && { key: 'brandId' as const, label: `Marka: ${catalog?.brands.find((brand) => brand.id === filters.brandId)?.name ?? ''}` },
    filters.productType && { key: 'productType' as const, label: `Tip: ${filters.productType}` },
    filters.inventoryStatus && { key: 'inventoryStatus' as const, label: `Stok: ${filters.inventoryStatus === 'low_stock' ? 'Az stok' : filters.inventoryStatus === 'out_of_stock' ? 'Stokta yok' : 'Stokta'}` },
  ].filter(Boolean) as Array<{ key: keyof ProductFilters; label: string }>;

  return (
    <div className="mx-auto max-w-7xl space-y-5">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h2 className="text-xl font-semibold text-dark">Ürünler</h2>
          <p className="mt-1 text-sm text-muted">Mağazanızdaki ürünleri yönetin.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button fullWidth={false} variant="secondary" disabled title="CSV içe aktarma yakında"><Upload size={16} />İçe Aktar</Button>
          <Button fullWidth={false} variant="secondary" disabled title="CSV dışa aktarma yakında"><Download size={16} />Dışa Aktar</Button>
          <Link to="/products/create"><Button fullWidth={false}><Plus size={16} />Ürün Ekle</Button></Link>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {([
          ['all', 'Tümü', counts.all],
          ['active', 'Aktif', counts.active],
          ['draft', 'Taslak', counts.draft],
          ['archived', 'Arşivlenmiş', counts.archived],
          ['out_of_stock', 'Stokta Yok', counts.out_of_stock],
          ['low_stock', 'Az Stok', counts.low_stock],
        ] as const).map(([key, label, count]) => {
          const isActive = (key === 'all' && !filters.status && !filters.inventoryStatus) || filters.status === key || filters.inventoryStatus === key;
          return (
            <button
              key={key}
              onClick={() => {
                setPage(1);
                if (key === 'all') setFilters((current) => ({ ...current, status: '', inventoryStatus: '' }));
                else if (key === 'out_of_stock' || key === 'low_stock') setFilters((current) => ({ ...current, status: '', inventoryStatus: key }));
                else setFilters((current) => ({ ...current, status: key, inventoryStatus: '' }));
              }}
              className={`rounded-md border px-3 py-2 text-sm font-medium transition ${isActive ? 'border-primary bg-surface-orange text-primary-hover' : 'border-border bg-card text-muted hover:text-dark'}`}
            >{label} <span className="ml-1 text-xs opacity-80">{count}</span></button>
          );
        })}
      </div>

      <Card className="p-0">
        <TableToolbar>
          <SearchInput value={searchInput} onChange={(value) => { setPage(1); setSearchInput(value); }} placeholder="Ürün, SKU veya barkod ara..." className="w-full lg:max-w-md" />
          <details className="relative">
            <summary className="flex cursor-pointer list-none items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm font-medium text-dark hover:bg-app-bg"><Filter size={16} />Filtreler<ChevronDown size={15} /></summary>
            <div className="absolute right-0 z-20 mt-2 grid w-80 gap-3 rounded-md border border-border bg-card p-4 shadow-lg">
              <label className="text-xs font-semibold text-muted">Kategori<select value={filters.categoryId} onChange={(event) => setFilter('categoryId', event.target.value)} className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="">Tümü</option>{catalog?.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
              <label className="text-xs font-semibold text-muted">Marka<select value={filters.brandId} onChange={(event) => setFilter('brandId', event.target.value)} className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="">Tümü</option>{catalog?.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>
              <label className="text-xs font-semibold text-muted">Ürün tipi<select value={filters.productType} onChange={(event) => setFilter('productType', event.target.value)} className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="">Tümü</option><option value="physical">Fiziksel</option><option value="digital">Dijital</option><option value="service">Hizmet</option></select></label>
              <label className="text-xs font-semibold text-muted">Stok durumu<select value={filters.inventoryStatus} onChange={(event) => setFilter('inventoryStatus', event.target.value as InventoryStatus | '')} className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="">Tümü</option><option value="in_stock">Stokta</option><option value="low_stock">Az stok</option><option value="out_of_stock">Stokta yok</option></select></label>
            </div>
          </details>
        </TableToolbar>

        {activeFilters.length > 0 && <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">{activeFilters.map((filter) => <button key={filter.key} onClick={() => setFilter(filter.key, '')} className="inline-flex items-center gap-1 rounded-full bg-surface-orange px-2.5 py-1 text-xs font-medium text-primary-hover">{filter.label}<X size={13} /></button>)}<button onClick={() => { setFilters(EMPTY_FILTERS); setPage(1); }} className="text-xs font-medium text-muted hover:text-dark">Filtreleri temizle</button></div>}

        {selected.size > 0 && <div className="flex flex-wrap items-center gap-2 border-b border-primary/20 bg-surface-orange px-4 py-3"><span className="mr-2 text-sm font-semibold text-dark">{selected.size} ürün seçildi</span><Button fullWidth={false} variant="secondary" disabled={bulkBusy} onClick={() => void applyBulk('activate')}>Aktif Yap</Button><Button fullWidth={false} variant="secondary" disabled={bulkBusy} onClick={() => void applyBulk('draft')}>Taslak Yap</Button><Button fullWidth={false} variant="secondary" disabled={bulkBusy} onClick={() => void applyBulk('archive')}>Arşivle</Button><Button fullWidth={false} variant="secondary" disabled={bulkBusy} onClick={() => void applyBulk('delete')}>Sil</Button></div>}

        {error && <p className="border-b border-border px-4 py-3 text-sm text-red-600">{error}</p>}
        {loading ? <ProductsSkeleton /> : products.length === 0 ? <ProductsEmpty searchActive={Boolean(searchInput || activeFilters.length)} /> : <ProductsTable products={products} selected={selected} onToggle={toggleSelected} onDuplicate={(id) => void duplicate(id)} onArchive={(id) => { setSelected(new Set([id])); void applyBulk('archive'); }} />}
        <Pagination currentPage={page} lastPage={lastPage} onChange={setPage} />
      </Card>
      <p className="text-xs text-muted">{total} ürün gösteriliyor</p>
    </div>
  );
}

function ProductsTable({
  products,
  selected,
  onToggle,
  onDuplicate,
  onArchive,
}: {
  products: ProductSummary[];
  selected: Set<string>;
  onToggle: (id: string) => void;
  onDuplicate: (id: string) => void;
  onArchive: (id: string) => void;
}) {
  const allSelected = products.length > 0 && products.every((product) => selected.has(product.id));

  return (
    <>
      <div className="hidden lg:block">
        <DataTable>
          <table className="min-w-[1020px] w-full text-left text-sm">
            <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted"><tr><th className="w-12 px-4 py-3"><input type="checkbox" checked={allSelected} onChange={() => products.forEach((product) => { if (allSelected === selected.has(product.id)) onToggle(product.id); })} aria-label="Tüm ürünleri seç" className="h-4 w-4 accent-primary" /></th><th className="px-4 py-3">Ürün</th><th className="px-4 py-3">Durum</th><th className="px-4 py-3">Envanter</th><th className="px-4 py-3">Kategori</th><th className="px-4 py-3">Marka</th><th className="px-4 py-3">Kanallar</th><th className="px-4 py-3">Güncelleme</th><th className="px-4 py-3"><span className="sr-only">İşlemler</span></th></tr></thead>
            <tbody className="divide-y divide-border">
              {products.map((product) => <tr key={product.id} className="hover:bg-app-bg/60"><td className="px-4 py-4"><input type="checkbox" checked={selected.has(product.id)} onChange={() => onToggle(product.id)} aria-label={`${product.title} seç`} className="h-4 w-4 accent-primary" /></td><td className="px-4 py-4"><Link to={`/products/${product.id}`} className="flex items-center gap-3"><ProductThumbnail product={product} /><span><span className="block font-medium text-dark hover:text-primary-hover">{product.title}</span><span className="mt-0.5 block text-xs text-muted">{product.variant_count} varyant</span></span></Link></td><td className="px-4 py-4"><ProductStatusBadge status={product.status} /></td><td className="px-4 py-4"><Badge tone={inventoryTone(product.inventory.status)}>{inventoryLabel(product)}</Badge></td><td className="px-4 py-4 text-muted">{product.category?.name ?? '-'}</td><td className="px-4 py-4 text-muted">{product.brand?.name ?? '-'}</td><td className="px-4 py-4"><span className="text-xs text-dark">Online Mağaza</span></td><td className="px-4 py-4 text-muted">{formatDate(product.updated_at)}</td><td className="px-4 py-4"><details className="relative"><summary className="cursor-pointer list-none rounded p-1 text-muted hover:bg-app-bg hover:text-dark">•••</summary><div className="absolute right-0 z-10 mt-1 w-32 rounded-md border border-border bg-card py-1 shadow-lg"><Link to={`/products/${product.id}`} className="block px-3 py-2 text-sm text-dark hover:bg-app-bg">Düzenle</Link><button onClick={() => onDuplicate(product.id)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"><Copy size={14} />Kopyala</button><button onClick={() => onArchive(product.id)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"><Archive size={14} />Arşivle</button></div></details></td></tr>)}
            </tbody>
          </table>
        </DataTable>
      </div>
      <div className="divide-y divide-border lg:hidden">{products.map((product) => <div key={product.id} className="flex gap-3 p-4"><input type="checkbox" checked={selected.has(product.id)} onChange={() => onToggle(product.id)} aria-label={`${product.title} seç`} className="mt-1 h-4 w-4 accent-primary" /><Link to={`/products/${product.id}`} className="min-w-0 flex-1"><div className="flex gap-3"><ProductThumbnail product={product} /><span className="min-w-0"><span className="block truncate font-medium text-dark">{product.title}</span><span className="mt-1 block text-xs text-muted">{product.variant_count} varyant · {inventoryLabel(product)}</span><span className="mt-2 inline-block"><ProductStatusBadge status={product.status} /></span></span></div></Link></div>)}</div>
    </>
  );
}

function ProductThumbnail({ product }: { product: ProductSummary }) {
  return product.featured_media ? <img src={product.featured_media.url} alt="" className="h-10 w-10 rounded-md object-cover" /> : <span className="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-app-bg text-xs font-semibold text-muted">{product.title.slice(0, 1).toUpperCase()}</span>;
}

function ProductsSkeleton() {
  return <div className="space-y-3 p-4">{Array.from({ length: 6 }, (_, index) => <div key={index} className="h-14 animate-pulse rounded bg-app-bg" />)}</div>;
}

function ProductsEmpty({ searchActive }: { searchActive: boolean }) {
  return <EmptyState icon={CheckSquare} title={searchActive ? 'Ürün bulunamadı.' : 'Henüz ürününüz yok.'} description={searchActive ? 'Filtrelerinizi değiştirerek tekrar deneyin.' : 'Mağazanızda satış yapmaya başlamak için ilk ürününüzü ekleyin.'} action={!searchActive ? <Link to="/products/create"><Button fullWidth={false}><Plus size={16} />İlk Ürünü Ekle</Button></Link> : undefined} />;
}