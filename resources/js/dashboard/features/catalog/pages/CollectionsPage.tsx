import { useEffect, useState, type FormEvent } from 'react';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Layers3, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { ApiError } from '../../../lib/api';
import { createCollection, deleteCollection, getCollection, listCollections, listProductPicker, updateCollection } from '../api/catalogAdminApi';
import type { CatalogStatus, CollectionPayload, CollectionProduct, PaginationMeta, ProductCollection, ProductPickerItem } from '../api/types';

interface CollectionDraft {
  name: string;
  slug: string;
  description: string;
  status: CatalogStatus;
  position: number;
  products: CollectionProduct[];
}

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

function emptyDraft(): CollectionDraft {
  return { name: '', slug: '', description: '', status: 'draft', position: 0, products: [] };
}

function statusLabel(status: CatalogStatus): string {
  return status === 'active' ? 'Aktif' : status === 'draft' ? 'Taslak' : 'Arşiv';
}

export function CollectionsPage() {
  usePageTitle('Koleksiyonlar');
  const [collections, setCollections] = useState<ProductCollection[]>([]);
  const [meta, setMeta] = useState(EMPTY_META);
  const [draftQuery, setDraftQuery] = useState('');
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [editor, setEditor] = useState<{ id: string | null; value: CollectionDraft } | null>(null);
  const [productQuery, setProductQuery] = useState('');
  const [pickerProducts, setPickerProducts] = useState<ProductPickerItem[]>([]);
  const [pickerLoading, setPickerLoading] = useState(false);
  const editorOpen = editor !== null;

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listCollections({ q: query || undefined, status: status || undefined, page: String(meta.current_page) })
      .then((response) => {
        if (!active) return;
        setCollections(response.data);
        setMeta(response.meta);
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof ApiError ? 'Koleksiyonlar yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, [meta.current_page, query, reloadKey, status]);

  useEffect(() => {
    if (!editorOpen) return;
    let active = true;
    const timeout = window.setTimeout(() => {
      setPickerLoading(true);
      void listProductPicker(productQuery.trim() || undefined)
        .then((response) => { if (active) setPickerProducts(response.data); })
        .catch(() => { if (active) setPickerProducts([]); })
        .finally(() => { if (active) setPickerLoading(false); });
    }, 250);

    return () => { active = false; window.clearTimeout(timeout); };
  }, [editorOpen, productQuery]);

  function openCreate() {
    setProductQuery('');
    setEditor({ id: null, value: emptyDraft() });
  }

  async function openEdit(collection: ProductCollection) {
    setError(null);
    try {
      const response = await getCollection(collection.id);
      setProductQuery('');
      setEditor({
        id: collection.id,
        value: {
          name: response.data.name,
          slug: response.data.slug,
          description: response.data.description ?? '',
          status: response.data.status,
          position: response.data.position,
          products: response.data.products ?? [],
        },
      });
    } catch {
      setError('Koleksiyon ayrıntıları yüklenemedi.');
    }
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editor || !editor.value.name.trim()) return;
    const payload: CollectionPayload = {
      name: editor.value.name,
      slug: editor.value.slug || null,
      description: editor.value.description || null,
      status: editor.value.status,
      position: editor.value.position,
      product_ids: editor.value.products.map((product) => product.id),
    };
    setSaving(true);
    setError(null);
    try {
      if (editor.id) await updateCollection(editor.id, payload);
      else await createCollection(payload);
      setEditor(null);
      setReloadKey((value) => value + 1);
    } catch (requestError) {
      setError(requestError instanceof ApiError ? 'Koleksiyon kaydedilemedi. Alanları ve ürün seçimini kontrol edin.' : 'Beklenmeyen bir hata oluştu.');
    } finally {
      setSaving(false);
    }
  }

  async function remove(collection: ProductCollection) {
    if (!window.confirm(`“${collection.name}” koleksiyonu silinsin mi? Ürünler silinmez.`)) return;
    try {
      await deleteCollection(collection.id);
      if (collections.length === 1 && meta.current_page > 1) setMeta((current) => ({ ...current, current_page: current.current_page - 1 }));
      else setReloadKey((value) => value + 1);
    } catch {
      setError('Koleksiyon silinemedi.');
    }
  }

  function toggleProduct(product: ProductPickerItem) {
    if (!editor) return;
    const selected = editor.value.products.some((item) => item.id === product.id);
    const products = selected
      ? editor.value.products.filter((item) => item.id !== product.id)
      : [...editor.value.products, { id: product.id, title: product.title, slug: product.slug, status: product.status, featured_media_url: product.featured_media?.url ?? null, position: editor.value.products.length }];
    setEditor({ ...editor, value: { ...editor.value, products } });
  }

  function moveProduct(index: number, direction: -1 | 1) {
    if (!editor) return;
    const target = index + direction;
    if (target < 0 || target >= editor.value.products.length) return;
    const products = [...editor.value.products];
    [products[index], products[target]] = [products[target], products[index]];
    setEditor({ ...editor, value: { ...editor.value, products } });
  }

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMeta((current) => ({ ...current, current_page: 1 }));
    setQuery(draftQuery.trim());
  }

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h2 className="text-xl font-semibold text-dark">Koleksiyonlar</h2><p className="mt-1 text-sm text-muted">Vitrin ve kampanyalar için sıralanabilir ürün grupları oluştur.</p></div><Button fullWidth={false} onClick={openCreate}><Plus size={16}/>Koleksiyon oluştur</Button></div>

      <Card className="p-0">
        <form onSubmit={submitSearch} className="grid gap-3 border-b border-border p-4 sm:grid-cols-[minmax(0,1fr)_11rem_auto]"><label className="relative"><span className="sr-only">Koleksiyon ara</span><Search size={17} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"/><input value={draftQuery} onChange={(event) => setDraftQuery(event.target.value)} placeholder="Koleksiyon adı veya slug ara..." className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"/></label><select value={status} onChange={(event) => { setStatus(event.target.value); setMeta((current) => ({ ...current, current_page: 1 })); }} aria-label="Koleksiyon durumu" className="rounded-md border border-border bg-card px-3 py-2 text-sm"><option value="">Tüm durumlar</option><option value="active">Aktif</option><option value="draft">Taslak</option><option value="archived">Arşiv</option></select><Button fullWidth={false} type="submit">Ara</Button></form>
        {error && <p className="border-b border-border px-4 py-3 text-sm text-red-600">{error}</p>}
        {loading ? <div className="p-8 text-sm text-muted">Koleksiyonlar yükleniyor...</div> : collections.length === 0 ? <EmptyState icon={Layers3} title="Koleksiyon bulunamadı" description="Ürünlerini vitrin ve kampanya gruplarında bir araya getir." action={<Button fullWidth={false} onClick={openCreate}><Plus size={16}/>Koleksiyon oluştur</Button>}/> : <div className="overflow-x-auto"><table className="min-w-[700px] w-full text-left text-sm"><thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted"><tr><th className="px-4 py-3">Koleksiyon</th><th className="px-4 py-3 text-right">Ürün</th><th className="px-4 py-3">Durum</th><th className="px-4 py-3">Sıra</th><th className="px-4 py-3 text-right">İşlemler</th></tr></thead><tbody className="divide-y divide-border">{collections.map((collection) => <tr key={collection.id} className="hover:bg-app-bg/60"><td className="px-4 py-3"><p className="font-medium text-dark">{collection.name}</p><p className="text-xs text-muted">/{collection.slug}</p></td><td className="px-4 py-3 text-right font-medium text-dark">{collection.product_count}</td><td className="px-4 py-3"><Badge tone={collection.status === 'active' ? 'success' : collection.status === 'draft' ? 'warning' : 'neutral'}>{statusLabel(collection.status)}</Badge></td><td className="px-4 py-3 text-muted">{collection.position}</td><td className="px-4 py-3"><div className="flex justify-end gap-1"><button onClick={() => void openEdit(collection)} className="rounded p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label={`${collection.name} düzenle`}><Pencil size={16}/></button><button onClick={() => void remove(collection)} className="rounded p-2 text-muted hover:bg-red-50 hover:text-red-600" aria-label={`${collection.name} sil`}><Trash2 size={16}/></button></div></td></tr>)}</tbody></table></div>}
        <div className="flex items-center justify-between border-t border-border px-4 py-3"><p className="text-sm text-muted">{meta.total} koleksiyon</p><div className="flex gap-2"><Button fullWidth={false} variant="secondary" disabled={meta.current_page <= 1} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page - 1 }))}><ChevronLeft size={16}/></Button><span className="px-2 py-2 text-sm text-muted">{meta.current_page} / {meta.last_page}</span><Button fullWidth={false} variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page + 1 }))}><ChevronRight size={16}/></Button></div></div>
      </Card>

      {editor && <div className="fixed inset-0 z-50 overflow-y-auto bg-dark/35 p-4" onMouseDown={() => !saving && setEditor(null)}><form onSubmit={save} role="dialog" aria-modal="true" aria-label={editor.id ? 'Koleksiyon düzenle' : 'Koleksiyon oluştur'} className="mx-auto my-4 w-full max-w-5xl rounded-xl border border-border bg-card shadow-xl" onMouseDown={(event) => event.stopPropagation()}><div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h3 className="font-semibold text-dark">{editor.id ? 'Koleksiyonu düzenle' : 'Yeni koleksiyon'}</h3><p className="text-sm text-muted">Bilgileri düzenle, ürünleri seç ve vitrindeki sıralarını belirle.</p></div><button type="button" onClick={() => setEditor(null)} className="text-muted hover:text-dark"><X size={19}/></button></div><div className="grid gap-6 p-5 lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]"><div className="space-y-4"><label className="block text-sm font-medium text-dark">Koleksiyon adı<input required value={editor.value.name} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, name: event.target.value } })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><label className="block text-sm font-medium text-dark">URL slug<input value={editor.value.slug} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, slug: event.target.value } })} placeholder="Boş bırakılırsa otomatik oluşur" className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><label className="block text-sm font-medium text-dark">Açıklama<textarea rows={4} value={editor.value.description} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, description: event.target.value } })} className="mt-1.5 w-full resize-y rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><div className="grid grid-cols-2 gap-4"><label className="text-sm font-medium text-dark">Durum<select value={editor.value.status} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, status: event.target.value as CatalogStatus } })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm"><option value="active">Aktif</option><option value="draft">Taslak</option><option value="archived">Arşiv</option></select></label><label className="text-sm font-medium text-dark">Sıra<input type="number" min="0" max="65535" value={editor.value.position} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, position: Number(event.target.value) || 0 } })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm"/></label></div><div><div className="mb-2 flex items-center justify-between"><h4 className="text-sm font-semibold text-dark">Seçilen ürünler</h4><span className="text-xs text-muted">{editor.value.products.length} ürün</span></div>{editor.value.products.length === 0 ? <p className="rounded-lg bg-app-bg p-4 text-sm text-muted">Henüz ürün seçilmedi.</p> : <ol className="max-h-72 divide-y divide-border overflow-y-auto rounded-lg border border-border">{editor.value.products.map((product, index) => <li key={product.id} className="flex items-center gap-2 p-2"><span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded bg-app-bg text-xs text-muted">{product.featured_media_url ? <img src={product.featured_media_url} alt="" className="h-full w-full object-cover"/> : product.title.slice(0, 1)}</span><span className="min-w-0 flex-1 truncate text-sm font-medium text-dark">{product.title}</span><button type="button" disabled={index === 0} onClick={() => moveProduct(index, -1)} className="rounded p-1 text-muted disabled:opacity-30" aria-label="Yukarı taşı"><ArrowUp size={14}/></button><button type="button" disabled={index === editor.value.products.length - 1} onClick={() => moveProduct(index, 1)} className="rounded p-1 text-muted disabled:opacity-30" aria-label="Aşağı taşı"><ArrowDown size={14}/></button><button type="button" onClick={() => setEditor({ ...editor, value: { ...editor.value, products: editor.value.products.filter((item) => item.id !== product.id) } })} className="rounded p-1 text-red-500" aria-label="Ürünü çıkar"><X size={14}/></button></li>)}</ol>}</div></div><div><h4 className="text-sm font-semibold text-dark">Ürün ekle</h4><label className="relative mt-2 block"><Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"/><input value={productQuery} onChange={(event) => setProductQuery(event.target.value)} placeholder="Ürün adı, SKU veya barkod ara..." className="w-full rounded-md border border-border py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"/></label><div className="mt-3 max-h-[32rem] divide-y divide-border overflow-y-auto rounded-lg border border-border">{pickerLoading ? <p className="p-5 text-sm text-muted">Ürünler yükleniyor...</p> : pickerProducts.length === 0 ? <p className="p-5 text-sm text-muted">Ürün bulunamadı.</p> : pickerProducts.map((product) => { const selected = editor.value.products.some((item) => item.id === product.id); return <button key={product.id} type="button" onClick={() => toggleProduct(product)} className={`flex w-full items-center gap-3 p-3 text-left hover:bg-app-bg ${selected ? 'bg-surface-orange/60' : ''}`}><span className="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded bg-app-bg text-xs text-muted">{product.featured_media ? <img src={product.featured_media.url} alt="" className="h-full w-full object-cover"/> : product.title.slice(0, 1)}</span><span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium text-dark">{product.title}</span><span className="block text-xs text-muted">/{product.slug}</span></span><Badge tone={selected ? 'primary' : 'neutral'}>{selected ? 'Seçildi' : 'Ekle'}</Badge></button>; })}</div></div></div><div className="flex justify-end gap-2 border-t border-border px-5 py-4"><Button type="button" fullWidth={false} variant="secondary" onClick={() => setEditor(null)} disabled={saving}>Vazgeç</Button><Button type="submit" fullWidth={false} disabled={saving}>{saving ? 'Kaydediliyor...' : 'Koleksiyonu kaydet'}</Button></div></form></div>}
    </div>
  );
}

