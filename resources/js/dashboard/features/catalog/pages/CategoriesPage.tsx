import { useEffect, useState, type FormEvent } from 'react';
import { ChevronLeft, ChevronRight, FolderTree, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { EmptyState } from '../../../components/ui/EmptyState';
import { ApiError } from '../../../lib/api';
import { createCategory, deleteCategory, listCategories, listCategoryOptions, updateCategory } from '../api/catalogAdminApi';
import type { CatalogStatus, CategoryItem, CategoryOption, CategoryPayload, PaginationMeta } from '../api/types';

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 50, total: 0 };
const EMPTY_DRAFT: CategoryPayload = { name: '', slug: '', description: '', status: 'active', position: 0, parent_id: null };

function statusLabel(status: CatalogStatus): string {
  return status === 'active' ? 'Aktif' : status === 'draft' ? 'Taslak' : 'Arşiv';
}

export function CategoriesPage() {
  usePageTitle('Kategoriler');
  const [categories, setCategories] = useState<CategoryItem[]>([]);
  const [categoryOptions, setCategoryOptions] = useState<CategoryOption[]>([]);
  const [meta, setMeta] = useState(EMPTY_META);
  const [draftQuery, setDraftQuery] = useState('');
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [editor, setEditor] = useState<{ id: string | null; value: CategoryPayload } | null>(null);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    void listCategories({ q: query || undefined, status: status || undefined, page: String(meta.current_page) })
      .then((response) => {
        if (!active) return;
        setCategories(response.data);
        setMeta(response.meta);
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof ApiError ? 'Kategoriler yüklenemedi.' : 'Beklenmeyen bir hata oluştu.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, [meta.current_page, query, reloadKey, status]);

  useEffect(() => {
    void listCategoryOptions().then((response) => setCategoryOptions(response.data.categories)).catch(() => undefined);
  }, [reloadKey]);

  function openCreate() {
    setEditor({ id: null, value: { ...EMPTY_DRAFT } });
  }

  function openEdit(category: CategoryItem) {
    setEditor({
      id: category.id,
      value: {
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        status: category.status,
        position: category.position,
        parent_id: category.parent?.id ?? null,
      },
    });
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editor || !editor.value.name.trim()) return;
    setSaving(true);
    setError(null);
    try {
      if (editor.id) await updateCategory(editor.id, editor.value);
      else await createCategory(editor.value);
      setEditor(null);
      setReloadKey((value) => value + 1);
    } catch (requestError) {
      setError(requestError instanceof ApiError ? 'Kategori kaydedilemedi. Alanları kontrol edin.' : 'Beklenmeyen bir hata oluştu.');
    } finally {
      setSaving(false);
    }
  }

  async function remove(category: CategoryItem) {
    if (!window.confirm(`“${category.name}” kategorisi silinsin mi? Ürünler silinmez, kategorisiz kalır.`)) return;
    setError(null);
    try {
      await deleteCategory(category.id);
      if (categories.length === 1 && meta.current_page > 1) setMeta((current) => ({ ...current, current_page: current.current_page - 1 }));
      else setReloadKey((value) => value + 1);
    } catch {
      setError('Kategori silinemedi. Lütfen tekrar deneyin.');
    }
  }

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMeta((current) => ({ ...current, current_page: 1 }));
    setQuery(draftQuery.trim());
  }

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 className="text-xl font-semibold text-dark">Kategoriler</h2><p className="mt-1 text-sm text-muted">Ürün kataloğunu hiyerarşik kategorilerle düzenle.</p></div>
        <Button fullWidth={false} onClick={openCreate}><Plus size={16} />Kategori oluştur</Button>
      </div>

      <Card className="p-0">
        <form onSubmit={submitSearch} className="grid gap-3 border-b border-border p-4 sm:grid-cols-[minmax(0,1fr)_11rem_auto]">
          <label className="relative"><span className="sr-only">Kategori ara</span><Search size={17} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"/><input value={draftQuery} onChange={(event) => setDraftQuery(event.target.value)} placeholder="Kategori adı veya slug ara..." className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"/></label>
          <select value={status} onChange={(event) => { setStatus(event.target.value); setMeta((current) => ({ ...current, current_page: 1 })); }} aria-label="Kategori durumu" className="rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary"><option value="">Tüm durumlar</option><option value="active">Aktif</option><option value="draft">Taslak</option><option value="archived">Arşiv</option></select>
          <Button fullWidth={false} type="submit">Ara</Button>
        </form>
        {error && <p className="border-b border-border px-4 py-3 text-sm text-red-600">{error}</p>}
        {loading ? <div className="p-8 text-sm text-muted">Kategoriler yükleniyor...</div> : categories.length === 0 ? <EmptyState icon={FolderTree} title="Kategori bulunamadı" description="İlk kategorini oluşturarak ürünlerini düzenlemeye başla." action={<Button fullWidth={false} onClick={openCreate}><Plus size={16}/>Kategori oluştur</Button>}/> : (
          <div className="overflow-x-auto"><table className="min-w-[760px] w-full text-left text-sm"><thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted"><tr><th className="px-4 py-3">Kategori</th><th className="px-4 py-3">Üst kategori</th><th className="px-4 py-3 text-right">Ürün</th><th className="px-4 py-3 text-right">Alt kategori</th><th className="px-4 py-3">Durum</th><th className="px-4 py-3 text-right">İşlemler</th></tr></thead><tbody className="divide-y divide-border">{categories.map((category) => <tr key={category.id} className="hover:bg-app-bg/60"><td className="px-4 py-3"><p className="font-medium text-dark">{category.name}</p><p className="text-xs text-muted">/{category.slug}</p></td><td className="px-4 py-3 text-muted">{category.parent?.name ?? '—'}</td><td className="px-4 py-3 text-right text-dark">{category.product_count}</td><td className="px-4 py-3 text-right text-dark">{category.children_count}</td><td className="px-4 py-3"><Badge tone={category.status === 'active' ? 'success' : category.status === 'draft' ? 'warning' : 'neutral'}>{statusLabel(category.status)}</Badge></td><td className="px-4 py-3"><div className="flex justify-end gap-1"><button onClick={() => openEdit(category)} className="rounded p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label={`${category.name} düzenle`}><Pencil size={16}/></button><button onClick={() => void remove(category)} className="rounded p-2 text-muted hover:bg-red-50 hover:text-red-600" aria-label={`${category.name} sil`}><Trash2 size={16}/></button></div></td></tr>)}</tbody></table></div>
        )}
        <div className="flex items-center justify-between border-t border-border px-4 py-3"><p className="text-sm text-muted">{meta.total} kategori</p><div className="flex gap-2"><Button fullWidth={false} variant="secondary" disabled={meta.current_page <= 1} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page - 1 }))}><ChevronLeft size={16}/></Button><span className="px-2 py-2 text-sm text-muted">{meta.current_page} / {meta.last_page}</span><Button fullWidth={false} variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setMeta((current) => ({ ...current, current_page: current.current_page + 1 }))}><ChevronRight size={16}/></Button></div></div>
      </Card>

      {editor && <div className="fixed inset-0 z-50 grid place-items-center bg-dark/35 p-4" onMouseDown={() => !saving && setEditor(null)}><form onSubmit={save} role="dialog" aria-modal="true" aria-label={editor.id ? 'Kategori düzenle' : 'Kategori oluştur'} className="w-full max-w-xl rounded-xl border border-border bg-card shadow-xl" onMouseDown={(event) => event.stopPropagation()}><div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h3 className="font-semibold text-dark">{editor.id ? 'Kategoriyi düzenle' : 'Yeni kategori'}</h3><p className="text-sm text-muted">Hiyerarşi, yayın durumu ve mağaza URL’sini yönet.</p></div><button type="button" onClick={() => setEditor(null)} className="text-muted hover:text-dark"><X size={19}/></button></div><div className="grid gap-4 p-5"><label className="text-sm font-medium text-dark">Kategori adı<input required value={editor.value.name} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, name: event.target.value } })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><label className="text-sm font-medium text-dark">URL slug<input value={editor.value.slug ?? ''} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, slug: event.target.value } })} placeholder="Boş bırakılırsa otomatik oluşur" className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><label className="text-sm font-medium text-dark">Açıklama<textarea rows={3} value={editor.value.description ?? ''} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, description: event.target.value } })} className="mt-1.5 w-full resize-y rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary"/></label><div className="grid gap-4 sm:grid-cols-3"><label className="text-sm font-medium text-dark">Üst kategori<select value={editor.value.parent_id ?? ''} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, parent_id: event.target.value || null } })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm"><option value="">Ana kategori</option>{categoryOptions.filter((category) => category.id !== editor.id).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label><label className="text-sm font-medium text-dark">Durum<select value={editor.value.status} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, status: event.target.value as CatalogStatus } })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm"><option value="active">Aktif</option><option value="draft">Taslak</option><option value="archived">Arşiv</option></select></label><label className="text-sm font-medium text-dark">Sıra<input type="number" min="0" max="65535" value={editor.value.position} onChange={(event) => setEditor({ ...editor, value: { ...editor.value, position: Number(event.target.value) || 0 } })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm"/></label></div></div><div className="flex justify-end gap-2 border-t border-border px-5 py-4"><Button type="button" fullWidth={false} variant="secondary" onClick={() => setEditor(null)} disabled={saving}>Vazgeç</Button><Button type="submit" fullWidth={false} disabled={saving}>{saving ? 'Kaydediliyor...' : 'Kaydet'}</Button></div></form></div>}
    </div>
  );
}

