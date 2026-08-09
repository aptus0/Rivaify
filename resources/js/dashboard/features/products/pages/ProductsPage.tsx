import { useEffect, useRef, useState } from 'react';
import { Archive, CheckCircle2, CheckSquare, ChevronDown, CircleAlert, Copy, Download, FileSpreadsheet, Filter, Plus, Upload, X } from 'lucide-react';
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
import { bulkUpdateProducts, duplicateProduct, exportProductsCsv, getCatalogOrganization, importProductCsv, listProducts, previewProductCsv, type ProductCsvResult } from '../api/productsApi';
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
  const [debouncedSearch, setDebouncedSearch] = useState('');
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
  const [csvBusy, setCsvBusy] = useState<'preview' | 'commit' | 'export' | null>(null);
  const [csvFile, setCsvFile] = useState<File | null>(null);
  const [csvResult, setCsvResult] = useState<ProductCsvResult | null>(null);
  const [csvNotice, setCsvNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
  const [refreshKey, setRefreshKey] = useState(0);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedSearch(searchInput), 300);

    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => {
    void getCatalogOrganization().then((response) => setCatalog(response.data)).catch(() => setCatalog(null));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listProducts({
      q: debouncedSearch.trim() || undefined,
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
  }, [debouncedSearch, filters, page, refreshKey]);

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
      const response = await listProducts({ q: debouncedSearch.trim() || undefined });
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

  async function archive(productId: string) {
    setBulkBusy(true);
    try {
      await bulkUpdateProducts({ product_ids: [productId], action: 'archive' });
      const response = await listProducts({
        q: debouncedSearch.trim() || undefined,
        status: filters.status || undefined,
        category_id: filters.categoryId || undefined,
        brand_id: filters.brandId || undefined,
        product_type: filters.productType || undefined,
        inventory_status: filters.inventoryStatus || undefined,
        page: String(page),
      });
      setProducts(response.data);
      setCounts(response.summary);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
    } catch {
      setError('Ürün arşivlenemedi. Lütfen tekrar deneyin.');
    } finally {
      setBulkBusy(false);
    }
  }

  function currentExportFilters() {
    return {
      q: debouncedSearch.trim() || undefined,
      status: filters.status || undefined,
      category_id: filters.categoryId || undefined,
      brand_id: filters.brandId || undefined,
      product_type: filters.productType || undefined,
      inventory_status: filters.inventoryStatus || undefined,
    };
  }

  async function exportCsv() {
    setCsvBusy('export');
    setCsvNotice(null);
    try {
      await exportProductsCsv(currentExportFilters());
      setCsvNotice({ tone: 'success', text: 'CSV dosyası mevcut filtrelerle hazırlandı.' });
    } catch {
      setCsvNotice({ tone: 'error', text: 'CSV dışa aktarılamadı. Lütfen tekrar deneyin.' });
    } finally {
      setCsvBusy(null);
    }
  }

  async function selectCsv(file: File | null) {
    if (!file) return;
    setCsvNotice(null);
    if (!file.name.toLowerCase().endsWith('.csv')) {
      setCsvNotice({ tone: 'error', text: 'Yalnızca .csv uzantılı dosyalar kabul edilir.' });
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setCsvNotice({ tone: 'error', text: 'CSV dosyası en fazla 2 MB olabilir.' });
      return;
    }
    setCsvFile(file);
    setCsvResult(null);
    setCsvBusy('preview');
    try {
      const response = await previewProductCsv(file);
      setCsvResult(response.data);
    } catch (requestError) {
      const validation = requestError instanceof ApiError ? requestError.validationErrors?.file?.[0] : null;
      setCsvFile(null);
      setCsvNotice({ tone: 'error', text: validation ?? 'CSV önizlemesi oluşturulamadı.' });
    } finally {
      setCsvBusy(null);
    }
  }

  async function commitCsv() {
    if (!csvFile || !csvResult?.can_import) return;
    setCsvBusy('commit');
    try {
      const response = await importProductCsv(csvFile);
      setCsvResult(response.data);
      if (response.data.failed === 0) {
        setCsvNotice({ tone: 'success', text: `${response.data.created} ürün oluşturuldu, ${response.data.updated} ürün güncellendi.` });
        setRefreshKey((current) => current + 1);
      } else {
        setCsvNotice({ tone: 'error', text: `${response.data.failed} ürün aktarılamadı; ayrıntıları kontrol edin.` });
      }
    } catch (requestError) {
      const validation = requestError instanceof ApiError ? requestError.validationErrors?.file?.[0] : null;
      setCsvNotice({ tone: 'error', text: validation ?? 'CSV içe aktarma tamamlanamadı.' });
    } finally {
      setCsvBusy(null);
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
          <input ref={fileInputRef} type="file" accept=".csv,text/csv" className="hidden" onChange={(event) => { void selectCsv(event.target.files?.[0] ?? null); event.currentTarget.value = ''; }} />
          <Button fullWidth={false} variant="secondary" disabled={csvBusy !== null} onClick={() => fileInputRef.current?.click()}><Upload size={16} />{csvBusy === 'preview' ? 'Kontrol ediliyor…' : 'İçe Aktar'}</Button>
          <Button fullWidth={false} variant="secondary" disabled={csvBusy !== null} onClick={() => void exportCsv()}><Download size={16} />{csvBusy === 'export' ? 'Hazırlanıyor…' : 'Dışa Aktar'}</Button>
          <Link to="/products/create"><Button fullWidth={false}><Plus size={16} />Ürün Ekle</Button></Link>
        </div>
      </div>

      {csvNotice && <div className={`flex items-start gap-2 rounded-lg border px-4 py-3 text-sm ${csvNotice.tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{csvNotice.tone === 'success' ? <CheckCircle2 size={17} className="mt-0.5 shrink-0" /> : <CircleAlert size={17} className="mt-0.5 shrink-0" />}<span>{csvNotice.text}</span><button onClick={() => setCsvNotice(null)} className="ml-auto rounded p-0.5" aria-label="Bildirimi kapat"><X size={15} /></button></div>}

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
        {loading ? <ProductsSkeleton /> : products.length === 0 ? <ProductsEmpty searchActive={Boolean(searchInput || activeFilters.length)} /> : <ProductsTable products={products} selected={selected} onToggle={toggleSelected} onDuplicate={(id) => void duplicate(id)} onArchive={(id) => void archive(id)} />}
        <Pagination currentPage={page} lastPage={lastPage} onChange={setPage} />
      </Card>
      <p className="text-xs text-muted">{total} ürün gösteriliyor</p>
      {csvFile && csvResult && <CsvImportDialog file={csvFile} result={csvResult} busy={csvBusy === 'commit'} onImport={() => void commitCsv()} onClose={() => { setCsvFile(null); setCsvResult(null); }} />}
    </div>
  );
}

function CsvImportDialog({ file, result, busy, onImport, onClose }: { file: File; result: ProductCsvResult; busy: boolean; onImport: () => void; onClose: () => void }) {
  const committed = result.mode === 'commit';
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-dark/45 sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="csv-import-title">
      <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-card shadow-2xl sm:rounded-2xl">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card px-5 py-4"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-surface-orange text-primary"><FileSpreadsheet size={20} /></span><div><h3 id="csv-import-title" className="font-semibold text-dark">{committed ? 'İçe aktarma sonucu' : 'CSV içe aktarma önizlemesi'}</h3><p className="max-w-md truncate text-xs text-muted">{file.name} · {(file.size / 1024).toLocaleString('tr-TR', { maximumFractionDigits: 1 })} KB</p></div></div><button onClick={onClose} className="rounded-md p-2 text-muted hover:bg-app-bg" aria-label="Pencereyi kapat"><X size={19} /></button></div>
        <div className="space-y-5 p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><CsvMetric label="CSV satırı" value={result.row_count} /><CsvMetric label="Ürün" value={result.product_count} /><CsvMetric label={committed ? 'Oluşturuldu' : 'Oluşturulacak'} value={committed ? result.created : result.will_create} /><CsvMetric label={committed ? 'Güncellendi' : 'Güncellenecek'} value={committed ? result.updated : result.will_update} /></div>
          {!committed && result.can_import && <div className="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"><CheckCircle2 size={20} className="shrink-0" /><div><p className="font-semibold">Dosya içe aktarmaya hazır.</p><p className="mt-1 leading-5">Tüm satırlar doğrulandı. Her ürün varyantları ve lokasyon stoklarıyla birlikte ayrı bir transaction içinde işlenecek.</p></div></div>}
          {result.error_count > 0 && <div><div className="mb-2 flex items-center justify-between"><h4 className="font-semibold text-red-700">{result.error_count} doğrulama hatası</h4>{result.errors_truncated && <span className="text-xs text-muted">İlk {result.errors.length} hata gösteriliyor</span>}</div><div className="max-h-72 overflow-auto rounded-lg border border-red-200"><table className="w-full text-left text-xs"><thead className="sticky top-0 bg-red-50 text-red-800"><tr><th className="px-3 py-2">Satır</th><th className="px-3 py-2">Alan</th><th className="px-3 py-2">Açıklama</th></tr></thead><tbody className="divide-y divide-red-100">{result.errors.map((item, index) => <tr key={`${item.row}-${item.field}-${index}`}><td className="whitespace-nowrap px-3 py-2 font-semibold text-dark">{item.row}</td><td className="whitespace-nowrap px-3 py-2 text-muted">{item.field}</td><td className="px-3 py-2 text-red-700">{item.message}{item.handle ? <span className="ml-1 text-muted">({item.handle})</span> : null}</td></tr>)}</tbody></table></div></div>}
          <div className="rounded-lg bg-app-bg p-4 text-xs leading-5 text-muted"><p className="font-semibold text-dark">Güvenli CSV akışı</p><p className="mt-1">En fazla 2 MB ve 1.000 veri satırı kabul edilir. Mevcut ürünü güncellemek için dışa aktarılan <code>product_id</code> korunmalıdır. Kimliği boş satırlar yeni ürün oluşturur; tenant dışındaki ürün, kategori, marka veya lokasyon kimlikleri reddedilir.</p></div>
        </div>
        <div className="sticky bottom-0 flex justify-end gap-2 border-t border-border bg-card px-5 py-4"><Button fullWidth={false} variant="secondary" onClick={onClose}>{committed ? 'Kapat' : 'Vazgeç'}</Button>{!committed && <Button fullWidth={false} disabled={!result.can_import || busy} onClick={onImport}><Upload size={16} />{busy ? 'İçe aktarılıyor…' : `${result.product_count} ürünü içe aktar`}</Button>}</div>
      </div>
    </div>
  );
}

function CsvMetric({ label, value }: { label: string; value: number }) {
  return <div className="rounded-lg border border-border bg-app-bg p-3"><p className="text-xs text-muted">{label}</p><p className="mt-1 text-xl font-semibold text-dark">{value.toLocaleString('tr-TR')}</p></div>;
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
          <table className="min-w-[980px] w-full text-left text-sm">
            <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted">
              <tr>
                <th className="w-12 px-4 py-3"><input type="checkbox" checked={allSelected} onChange={() => products.forEach((product) => { if (allSelected === selected.has(product.id)) onToggle(product.id); })} aria-label="Tüm ürünleri seç" className="h-4 w-4 accent-primary" /></th>
                <th className="px-4 py-3">Ürün</th>
                <th className="px-4 py-3">Durum</th>
                <th className="px-4 py-3">Stok</th>
                <th className="px-4 py-3">Organizasyon</th>
                <th className="px-4 py-3">Kanal</th>
                <th className="px-4 py-3">Güncelleme</th>
                <th className="px-4 py-3"><span className="sr-only">İşlemler</span></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {products.map((product) => (
                <tr key={product.id} className="hover:bg-app-bg/60">
                  <td className="px-4 py-4"><input type="checkbox" checked={selected.has(product.id)} onChange={() => onToggle(product.id)} aria-label={`${product.title} seç`} className="h-4 w-4 accent-primary" /></td>
                  <td className="px-4 py-4">
                    <Link to={`/products/${product.id}`} className="flex items-center gap-3">
                      <ProductThumbnail product={product} />
                      <span className="min-w-0">
                        <span className="block max-w-[320px] truncate font-medium text-dark hover:text-primary-hover">{product.title}</span>
                        <span className="mt-0.5 block text-xs text-muted">{product.variant_count} varyant</span>
                      </span>
                    </Link>
                  </td>
                  <td className="px-4 py-4"><ProductStatusBadge status={product.status} /></td>
                  <td className="px-4 py-4"><Badge tone={inventoryTone(product.inventory.status)}>{inventoryLabel(product)}</Badge></td>
                  <td className="px-4 py-4 text-muted"><p className="max-w-[170px] truncate text-dark">{product.category?.name ?? 'Kategorisiz'}</p><p className="mt-1 max-w-[170px] truncate text-xs">{product.brand?.name ?? 'Marka yok'}</p></td>
                  <td className="px-4 py-4"><ProductChannelBadge product={product} /></td>
                  <td className="px-4 py-4 text-muted">{formatDate(product.updated_at)}</td>
                  <td className="px-4 py-4"><details className="relative"><summary className="cursor-pointer list-none rounded p-1 text-muted hover:bg-app-bg hover:text-dark">•••</summary><div className="absolute right-0 z-10 mt-1 w-32 rounded-md border border-border bg-card py-1 shadow-lg"><Link to={`/products/${product.id}`} className="block px-3 py-2 text-sm text-dark hover:bg-app-bg">Düzenle</Link><button onClick={() => onDuplicate(product.id)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"><Copy size={14} />Kopyala</button><button onClick={() => onArchive(product.id)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"><Archive size={14} />Arşivle</button></div></details></td>
                </tr>
              ))}
            </tbody>
          </table>
        </DataTable>
      </div>
      <div className="divide-y divide-border lg:hidden">
        {products.map((product) => (
          <div key={product.id} className="flex gap-3 p-4">
            <input type="checkbox" checked={selected.has(product.id)} onChange={() => onToggle(product.id)} aria-label={`${product.title} seç`} className="mt-1 h-4 w-4 accent-primary" />
            <Link to={`/products/${product.id}`} className="min-w-0 flex-1">
              <div className="flex gap-3">
                <ProductThumbnail product={product} />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium text-dark">{product.title}</span>
                  <span className="mt-1 block text-xs text-muted">{product.variant_count} varyant · {inventoryLabel(product)}</span>
                  <span className="mt-3 flex flex-wrap gap-2"><ProductStatusBadge status={product.status} /><ProductChannelBadge product={product} /></span>
                </span>
              </div>
            </Link>
          </div>
        ))}
      </div>
    </>
  );
}

function ProductChannelBadge({ product }: { product: ProductSummary }) {
  const onlineStore = product.sales_channels.find((channel) => channel.key === 'online_store');
  if (!onlineStore) return <Badge tone="neutral">Kanal yok</Badge>;

  return <Badge tone={onlineStore.enabled ? 'success' : 'warning'}>{onlineStore.enabled ? onlineStore.label : onlineStore.detail ?? 'Hazır değil'}</Badge>;
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
