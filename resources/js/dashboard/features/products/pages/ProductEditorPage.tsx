import { useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, ArrowLeft, Check, CircleDollarSign, CopyPlus, Eye, Plus, Save, Tag, X } from 'lucide-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '../../../app/layouts/AppLayout';
import { useAuth } from '../../../app/providers/AuthProvider';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { ApiError } from '../../../lib/api';
import { formatMoney } from '../../../utils/commerceFormat';
import {
  createProduct,
  createQuickBrand,
  createQuickCategory,
  deleteProductMedia,
  getCatalogOrganization,
  getProduct,
  reorderProductMedia,
  updateProduct,
  updateProductMedia,
  uploadProductMedia,
} from '../api/productsApi';
import type {
  CatalogOrganization,
  ProductDetail,
  ProductMedia,
  ProductOptionDraft,
  ProductPayload,
  ProductStatus,
  ProductType,
  ProductVariant,
  ProductVariantDraft,
} from '../api/types';
import { FormSection } from '../components/FormSection';
import { type PendingProductMedia, ProductMediaUploader } from '../components/ProductMediaUploader';
import { ProductStatusBadge } from '../components/ProductStatusBadge';
import { RichTextEditor } from '../components/RichTextEditor';

type EditorOption = ProductOptionDraft & { clientId: string };
type EditorVariant = ProductVariantDraft & { clientId: string };

interface ProductDraft {
  title: string;
  description: string;
  slug: string;
  categoryId: string;
  brandId: string;
  productType: ProductType;
  status: ProductStatus;
  vendor: string;
  isTaxable: boolean;
  requiresShipping: boolean;
  metaTitle: string;
  metaDescription: string;
  packageWidth: string;
  packageHeight: string;
  packageLength: string;
  packageDimensionUnit: 'cm' | 'in';
  tags: string[];
  options: EditorOption[];
  variants: EditorVariant[];
}

function clientId(): string {
  return typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

function defaultVariant(title: string, draft?: Partial<ProductDraft>): EditorVariant {
  return {
    clientId: clientId(),
    title,
    price: '0.00',
    compare_at_price: null,
    cost_price: null,
    sku: null,
    barcode: null,
    weight: null,
    weight_unit: 'kg',
    requires_shipping: draft?.requiresShipping ?? true,
    is_taxable: draft?.isTaxable ?? true,
    status: draft?.status ?? 'draft',
    track_inventory: false,
    allow_oversell: false,
    inventory: [],
  };
}

function emptyDraft(): ProductDraft {
  return {
    title: '',
    description: '',
    slug: '',
    categoryId: '',
    brandId: '',
    productType: 'physical',
    status: 'draft',
    vendor: '',
    isTaxable: true,
    requiresShipping: true,
    metaTitle: '',
    metaDescription: '',
    packageWidth: '',
    packageHeight: '',
    packageLength: '',
    packageDimensionUnit: 'cm',
    tags: [],
    options: [],
    variants: [defaultVariant('Default')],
  };
}

function optionTitles(options: EditorOption[]): string[] | null {
  if (options.length === 0) return ['Default'];
  if (options.some((option) => option.name.trim() === '' || option.values.length === 0)) return null;

  return options.reduce<string[]>((combinations, option) => combinations.flatMap((combination) => option.values.map((value) => combination ? `${combination} / ${value}` : value)), ['']);
}

function toPayload(draft: ProductDraft): ProductPayload {
  return {
    title: draft.title,
    description: draft.description || null,
    slug: draft.slug || null,
    category_id: draft.categoryId || null,
    brand_id: draft.brandId || null,
    product_type: draft.productType,
    status: draft.status,
    vendor: draft.vendor || null,
    is_taxable: draft.isTaxable,
    requires_shipping: draft.requiresShipping,
    meta_title: draft.metaTitle || null,
    meta_description: draft.metaDescription || null,
    package: draft.productType === 'physical' ? {
      width: draft.packageWidth || null,
      height: draft.packageHeight || null,
      length: draft.packageLength || null,
      dimension_unit: draft.packageDimensionUnit,
    } : undefined,
    tags: draft.tags,
    options: draft.options.map(({ name, values }) => ({ name, values })),
    variants: draft.variants.map(({ clientId: _clientId, ...variant }) => variant),
  };
}

function draftFromProduct(product: ProductDetail): ProductDraft {
  return {
    title: product.title,
    description: product.description ?? '',
    slug: product.slug,
    categoryId: product.category?.id ?? '',
    brandId: product.brand?.id ?? '',
    productType: product.product_type,
    status: product.status,
    vendor: product.vendor ?? '',
    isTaxable: product.is_taxable,
    requiresShipping: product.requires_shipping,
    metaTitle: product.seo.meta_title ?? '',
    metaDescription: product.seo.meta_description ?? '',
    packageWidth: product.package.width ?? '',
    packageHeight: product.package.height ?? '',
    packageLength: product.package.length ?? '',
    packageDimensionUnit: product.package.dimension_unit,
    tags: product.tags,
    options: product.options.map((option) => ({ clientId: option.id, name: option.name, values: option.values.map((value) => value.value) })),
    variants: product.variants.map((variant) => variantFromProduct(variant)),
  };
}

function variantFromProduct(variant: ProductVariant): EditorVariant {
  return {
    clientId: variant.id,
    title: variant.title,
    price: variant.price,
    compare_at_price: variant.compare_at_price,
    cost_price: variant.cost_price,
    sku: variant.sku,
    barcode: variant.barcode,
    weight: variant.weight,
    weight_unit: variant.weight_unit,
    requires_shipping: variant.requires_shipping,
    is_taxable: variant.is_taxable,
    status: variant.status,
    track_inventory: variant.inventory.is_tracked,
    allow_oversell: variant.inventory.allow_oversell,
    inventory: variant.inventory.levels.map((level) => ({ location_id: level.location_id, available_quantity: level.available })),
  };
}

function messageFor(error: unknown): string {
  if (error instanceof ApiError && error.validationErrors) return 'Ürün kaydedilemedi. Lütfen alanları kontrol edin.';
  if (error instanceof ApiError && error.body && typeof error.body === 'object' && 'message' in error.body) return String((error.body as { message: unknown }).message);

  return 'Ürün kaydedilemedi. Lütfen tekrar deneyin.';
}

export function ProductEditorPage() {
  const { store } = useAuth();
  const { productId } = useParams();
  const navigate = useNavigate();
  const creating = !productId;
  usePageTitle(creating ? 'Ürün Ekle' : 'Ürünü Düzenle');
  const [catalog, setCatalog] = useState<CatalogOrganization | null>(null);
  const [draft, setDraft] = useState<ProductDraft>(emptyDraft);
  const [initialDraft, setInitialDraft] = useState<ProductDraft>(emptyDraft);
  const [media, setMedia] = useState<ProductMedia[]>([]);
  const [initialMedia, setInitialMedia] = useState<ProductMedia[]>([]);
  const [pendingMedia, setPendingMedia] = useState<PendingProductMedia[]>([]);
  const [tagInput, setTagInput] = useState('');
  const [quickCategoryName, setQuickCategoryName] = useState('');
  const [quickBrandName, setQuickBrandName] = useState('');
  const [loading, setLoading] = useState(!creating);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const pendingMediaRef = useRef<PendingProductMedia[]>([]);
  const snapshot = useMemo(() => JSON.stringify(toPayload(draft)), [draft]);
  const initialSnapshot = useMemo(() => JSON.stringify(toPayload(initialDraft)), [initialDraft]);
  const dirty = snapshot !== initialSnapshot || pendingMedia.length > 0 || media.length !== initialMedia.length || media.some((item, index) => item.id !== initialMedia[index]?.id);

  useEffect(() => {
    let active = true;
    void getCatalogOrganization().then((response) => { if (active) setCatalog(response.data); }).catch(() => { if (active) setError('Ürün organizasyon bilgileri yüklenemedi.'); });
    if (!productId) return () => { active = false; };

    void getProduct(productId)
      .then((response) => {
        if (!active) return;
        const nextDraft = draftFromProduct(response.data);
        setDraft(nextDraft);
        setInitialDraft(nextDraft);
        setMedia(response.data.media);
        setInitialMedia(response.data.media);
      })
      .catch(() => { if (active) setError('Ürün yüklenemedi.'); })
      .finally(() => { if (active) setLoading(false); });

    return () => { active = false; };
  }, [productId]);

  useEffect(() => {
    pendingMediaRef.current = pendingMedia;
  }, [pendingMedia]);

  useEffect(() => () => pendingMediaRef.current.forEach((item) => URL.revokeObjectURL(item.previewUrl)), []);

  useEffect(() => {
    function warn(event: BeforeUnloadEvent) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    }
    window.addEventListener('beforeunload', warn);

    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  function updateDraft(next: Partial<ProductDraft>) {
    setDraft((current) => ({ ...current, ...next }));
  }

  function setOptions(nextOptions: EditorOption[]) {
    setDraft((current) => {
      const titles = optionTitles(nextOptions);
      if (titles === null) return { ...current, options: nextOptions };
      const existing = new Map(current.variants.map((variant) => [variant.title, variant]));

      return {
        ...current,
        options: nextOptions,
        variants: titles.map((title) => existing.get(title) ?? defaultVariant(title, current)),
      };
    });
  }

  function updateVariant(clientId: string, patch: Partial<EditorVariant>) {
    setDraft((current) => ({
      ...current,
      variants: current.variants.map((variant) => variant.clientId === clientId ? { ...variant, ...patch } : variant),
    }));
  }

  function setVariantInventory(clientId: string, locationId: string, quantity: number) {
    setDraft((current) => ({
      ...current,
      variants: current.variants.map((variant) => {
        if (variant.clientId !== clientId) return variant;
        const inventory = variant.inventory.filter((level) => level.location_id !== locationId);
        if (quantity > 0) inventory.push({ location_id: locationId, available_quantity: quantity });

        return { ...variant, inventory };
      }),
    }));
  }

  function addTag() {
    const tag = tagInput.trim();
    if (!tag || draft.tags.some((item) => item.toLocaleLowerCase('tr-TR') === tag.toLocaleLowerCase('tr-TR'))) return;
    updateDraft({ tags: [...draft.tags, tag] });
    setTagInput('');
  }

  function stageFiles(files: File[]) {
    const valid = files.filter((file) => file.size <= 20 * 1024 * 1024 && ['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes(file.type));
    setPendingMedia((current) => [...current, ...valid.map((file) => ({ id: clientId(), file, previewUrl: URL.createObjectURL(file), altText: '' }))]);
  }

  async function uploadPending(product: ProductDetail, pending: PendingProductMedia[]) {
    if (pending.length === 0) return product.media;
    setUploading(true);
    try {
      const uploads: ProductMedia[] = [];
      for (const item of pending) {
        const response = await uploadProductMedia(product.id, item.file, item.altText);
        uploads.push(response.data);
      }
      pending.forEach((item) => URL.revokeObjectURL(item.previewUrl));
      setPendingMedia([]);
      setMedia((current) => [...current, ...uploads]);

      return [...product.media, ...uploads];
    } finally {
      setUploading(false);
    }
  }

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const response = creating ? await createProduct(toPayload(draft)) : await updateProduct(productId, toPayload(draft));
      const uploadedMedia = await uploadPending(response.data, pendingMedia);
      const savedDraft = draftFromProduct({ ...response.data, media: uploadedMedia });
      setDraft(savedDraft);
      setInitialDraft(savedDraft);
      setMedia(uploadedMedia);
      setInitialMedia(uploadedMedia);
      if (creating) navigate(`/products/${response.data.id}`, { replace: true });
    } catch (requestError) {
      setError(messageFor(requestError));
    } finally {
      setSaving(false);
    }
  }

  async function quickCreateCategory() {
    if (!quickCategoryName.trim()) return;
    try {
      const response = await createQuickCategory({ name: quickCategoryName.trim() });
      setCatalog((current) => current ? { ...current, categories: [...current.categories, response.data] } : current);
      updateDraft({ categoryId: response.data.id });
      setQuickCategoryName('');
    } catch {
      setError('Kategori oluşturulamadı.');
    }
  }

  async function quickCreateBrand() {
    if (!quickBrandName.trim()) return;
    try {
      const response = await createQuickBrand({ name: quickBrandName.trim() });
      setCatalog((current) => current ? { ...current, brands: [...current.brands, response.data] } : current);
      updateDraft({ brandId: response.data.id });
      setQuickBrandName('');
    } catch {
      setError('Marka oluşturulamadı.');
    }
  }

  async function updateMedia(item: ProductMedia, next: { altText: string; isFeatured: boolean }) {
    if (!productId) return;
    try {
      const response = await updateProductMedia(productId, item.id, { alt_text: next.altText || null, is_featured: next.isFeatured });
      setMedia((current) => current.map((mediaItem) => mediaItem.id === item.id ? response.data : { ...mediaItem, is_featured: next.isFeatured ? false : mediaItem.is_featured }));
    } catch {
      setError('Medya bilgisi güncellenemedi.');
    }
  }

  async function reorderMedia(ids: string[]) {
    if (!productId) return;
    try {
      const response = await reorderProductMedia(productId, ids);
      setMedia(response.data.media);
    } catch {
      setError('Medya sırası güncellenemedi.');
    }
  }

  async function removeMedia(item: ProductMedia) {
    if (!productId || !window.confirm('Bu medya silinsin mi?')) return;
    try {
      await deleteProductMedia(productId, item.id);
      setMedia((current) => current.filter((mediaItem) => mediaItem.id !== item.id));
    } catch {
      setError('Medya silinemedi.');
    }
  }

  function discardChanges() {
    if (!window.confirm('Kaydedilmemiş değişiklikler silinsin mi?')) return;
    pendingMedia.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    setPendingMedia([]);
    setDraft(initialDraft);
    setMedia(initialMedia);
  }

  if (loading) return <div className="mx-auto max-w-7xl space-y-4"><div className="h-8 w-48 animate-pulse rounded bg-app-bg" />{Array.from({ length: 4 }, (_, index) => <div key={index} className="h-48 animate-pulse rounded-lg bg-card" />)}</div>;

  return (
    <div className="mx-auto max-w-7xl pb-28">
      <div className="mb-5 flex items-center justify-between gap-3">
        <Link to="/products" className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"><ArrowLeft size={16} />Ürünler</Link>
        {!creating && store && draft.slug && <a href={`https://${store.slug}.rivaify.com/products/${draft.slug}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"><Eye size={16} />Mağazada gör</a>}
      </div>
      <div className="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div><h2 className="text-xl font-semibold text-dark">{creating ? 'Ürün Ekle' : draft.title || 'Ürünü Düzenle'}</h2><p className="mt-1 text-sm text-muted">Ürün bilgileri, varyantlar ve satışa hazır stok seviyeleri.</p></div>
        {!creating && <ProductStatusBadge status={draft.status} />}
      </div>
      {error && <div className="mb-5 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"><AlertCircle size={17} className="mt-0.5 shrink-0" />{error}</div>}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div className="space-y-5">
          <FormSection title="Ürün Bilgileri" description="Başlık ve açıklama mağaza ve arama motorlarında kullanılır.">
            <div className="space-y-4"><Input label="Ürün adı" value={draft.title} onChange={(event) => updateDraft({ title: event.target.value })} placeholder="Örn. Nike Air Max" required /><div><label className="mb-1 block text-sm font-medium text-dark">Açıklama</label><RichTextEditor value={draft.description} onChange={(description) => updateDraft({ description })} /></div></div>
          </FormSection>

          <FormSection title="Medya" description="Görselleri sürükleyerek sırala, öne çıkan görseli belirle ve alt metin ekle.">
            <ProductMediaUploader media={media} pending={pendingMedia} uploading={uploading} onFiles={stageFiles} onDelete={(item) => void removeMedia(item)} onReorder={(ids) => void reorderMedia(ids)} onMetadata={(item, next) => void updateMedia(item, next)} onRemovePending={(id) => setPendingMedia((current) => current.filter((item) => item.id !== id))} onPendingAltChange={(id, altText) => setPendingMedia((current) => current.map((item) => item.id === id ? { ...item, altText } : item))} />
          </FormSection>

          <FormSection title="Ürün Organizasyonu" description="Kategori, marka ve etiketler ürününüzün yönetimini kolaylaştırır.">
            <div className="grid gap-4 md:grid-cols-2">
              <label className="text-sm font-medium text-dark">Kategori<select value={draft.categoryId} onChange={(event) => updateDraft({ categoryId: event.target.value })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary"><option value="">Kategori seçin</option>{catalog?.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
              <label className="text-sm font-medium text-dark">Marka<select value={draft.brandId} onChange={(event) => updateDraft({ brandId: event.target.value })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary"><option value="">Marka seçin</option>{catalog?.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>
              <div className="md:col-span-2 grid gap-2 sm:grid-cols-2"><div className="flex gap-2"><input value={quickCategoryName} onChange={(event) => setQuickCategoryName(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); void quickCreateCategory(); } }} placeholder="Hızlı kategori ekle" className="min-w-0 flex-1 rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary" /><Button type="button" fullWidth={false} variant="secondary" onClick={() => void quickCreateCategory()}><Plus size={15} /></Button></div><div className="flex gap-2"><input value={quickBrandName} onChange={(event) => setQuickBrandName(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); void quickCreateBrand(); } }} placeholder="Hızlı marka ekle" className="min-w-0 flex-1 rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary" /><Button type="button" fullWidth={false} variant="secondary" onClick={() => void quickCreateBrand()}><Plus size={15} /></Button></div></div>
              <label className="text-sm font-medium text-dark">Ürün tipi<select value={draft.productType} onChange={(event) => updateDraft({ productType: event.target.value as ProductType, requiresShipping: event.target.value === 'physical' ? draft.requiresShipping : false })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary"><option value="physical">Fiziksel ürün</option><option value="digital">Dijital ürün</option><option value="service">Hizmet</option></select></label>
              <label className="text-sm font-medium text-dark">Satıcı / tedarikçi<input value={draft.vendor} onChange={(event) => updateDraft({ vendor: event.target.value })} className="mt-1.5 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary" placeholder="Opsiyonel" /></label>
              <div className="md:col-span-2"><label className="text-sm font-medium text-dark">Etiketler</label><div className="mt-1.5 flex flex-wrap gap-2 rounded-md border border-border p-2">{draft.tags.map((tag) => <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-app-bg px-2 py-1 text-xs text-dark"><Tag size={12} />{tag}<button type="button" onClick={() => updateDraft({ tags: draft.tags.filter((item) => item !== tag) })} aria-label={`${tag} etiketini kaldır`}><X size={12} /></button></span>)}<input value={tagInput} onChange={(event) => setTagInput(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); addTag(); } }} onBlur={addTag} placeholder="Etiket ekle" className="min-w-32 flex-1 border-0 px-1 py-1 text-sm outline-none" /></div></div>
            </div>
          </FormSection>

          <FormSection title="Fiyatlandırma" description="Fiyatlar sunucuda decimal olarak doğrulanır. Kâr bilgisi yalnızca yönetim ekranı görünümüdür.">
            <div className="grid gap-4 md:grid-cols-3"><Input label="Satış fiyatı" type="number" min="0" step="0.01" value={draft.variants[0]?.price ?? ''} onChange={(event) => updateVariant(draft.variants[0]?.clientId ?? '', { price: event.target.value })} /><Input label="Karşılaştırma fiyatı" type="number" min="0" step="0.01" value={draft.variants[0]?.compare_at_price ?? ''} onChange={(event) => updateVariant(draft.variants[0]?.clientId ?? '', { compare_at_price: event.target.value || null })} /><Input label="Maliyet" type="number" min="0" step="0.01" value={draft.variants[0]?.cost_price ?? ''} onChange={(event) => updateVariant(draft.variants[0]?.clientId ?? '', { cost_price: event.target.value || null })} /></div><ProfitPreview variant={draft.variants[0]} currency={store?.default_currency ?? 'TRY'} />
          </FormSection>

          <FormSection title="Varyantlar" description="Seçenekleri eklediğinizde olası kombinasyonlar otomatik oluşturulur.">
            <div className="space-y-4"><OptionBuilder options={draft.options} onChange={setOptions} /><VariantTable variants={draft.variants} locations={catalog?.locations ?? []} onChange={updateVariant} onInventoryChange={setVariantInventory} /></div>
          </FormSection>

          {draft.productType === 'physical' && <FormSection title="Kargo" description="Fiziksel ürünler için ağırlık ve paket ölçülerini girin."><div className="grid gap-4 md:grid-cols-4"><Input label="Genişlik" type="number" min="0" step="0.01" value={draft.packageWidth} onChange={(event) => updateDraft({ packageWidth: event.target.value })} /><Input label="Yükseklik" type="number" min="0" step="0.01" value={draft.packageHeight} onChange={(event) => updateDraft({ packageHeight: event.target.value })} /><Input label="Uzunluk" type="number" min="0" step="0.01" value={draft.packageLength} onChange={(event) => updateDraft({ packageLength: event.target.value })} /><label className="text-sm font-medium text-dark">Birim<select value={draft.packageDimensionUnit} onChange={(event) => updateDraft({ packageDimensionUnit: event.target.value as 'cm' | 'in' })} className="mt-1.5 w-full rounded-md border border-border bg-card px-3 py-2 text-sm"><option value="cm">cm</option><option value="in">in</option></select></label></div><label className="mt-4 flex items-center gap-2 text-sm text-dark"><input type="checkbox" checked={draft.requiresShipping} onChange={(event) => updateDraft({ requiresShipping: event.target.checked })} className="h-4 w-4 accent-primary" />Bu fiziksel bir ürün ve kargo gerektirir</label></FormSection>}

          <FormSection title="Arama Motoru Listesi" description="URL ve arama sonucu görünümünü düzenleyin."><div className="rounded-md border border-border bg-app-bg p-4"><p className="text-base font-medium text-dark">{draft.metaTitle || draft.title || 'Ürün başlığı'}</p><p className="mt-1 text-sm text-emerald-700">{draft.slug || 'urun-url-slug'}</p><p className="mt-2 text-sm text-muted">{draft.metaDescription || 'Ürün açıklamanız burada görünür.'}</p></div><div className="mt-4 grid gap-4"><Input label="Meta başlık" value={draft.metaTitle} onChange={(event) => updateDraft({ metaTitle: event.target.value })} /><Input label="Meta açıklama" value={draft.metaDescription} onChange={(event) => updateDraft({ metaDescription: event.target.value })} /><Input label="URL slug" value={draft.slug} onChange={(event) => updateDraft({ slug: event.target.value })} /></div></FormSection>
        </div>

        <aside className="space-y-5 xl:sticky xl:top-6 xl:self-start">
          <FormSection title="Durum"><select value={draft.status} onChange={(event) => updateDraft({ status: event.target.value as ProductStatus })} className="w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-dark"><option value="draft">Taslak</option><option value="active">Aktif</option><option value="archived">Arşiv</option></select><p className="mt-3 text-sm text-muted">Aktif ürünler satış kanallarında görünür.</p></FormSection>
          <FormSection title="Satış Kanalları"><div className="space-y-3 text-sm"><div className="flex items-center justify-between"><span className="flex items-center gap-2 text-dark"><Check size={15} className="text-emerald-600" />Online Mağaza</span><Badge tone="success">Yayınlanacak</Badge></div>{['Instagram', 'Facebook', 'TikTok'].map((channel) => <div key={channel} className="flex items-center justify-between text-muted"><span>{channel}</span><Badge tone="neutral">Yakında</Badge></div>)}</div></FormSection>
          <FormSection title="Vergi"><label className="flex items-center gap-2 text-sm text-dark"><input type="checkbox" checked={draft.isTaxable} onChange={(event) => updateDraft({ isTaxable: event.target.checked })} className="h-4 w-4 accent-primary" />Bu ürün vergilendirilebilir</label></FormSection>
        </aside>
      </div>

      {dirty && <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 px-4 py-3 shadow-lg backdrop-blur lg:left-64"><div className="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p className="text-sm font-medium text-dark">Kaydedilmemiş değişiklikler var.</p><div className="flex gap-2"><Button fullWidth={false} variant="secondary" onClick={discardChanges} disabled={saving}>Vazgeç</Button><Button fullWidth={false} onClick={() => void save()} disabled={saving}><Save size={16} />{saving ? 'Kaydediliyor...' : 'Kaydet'}</Button></div></div></div>}
      {!dirty && <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 px-4 py-3 shadow-lg backdrop-blur lg:left-64"><div className="mx-auto flex max-w-7xl justify-end"><Button fullWidth={false} onClick={() => void save()} disabled={saving}><Save size={16} />{saving ? 'Kaydediliyor...' : 'Kaydet'}</Button></div></div>}
    </div>
  );
}

function OptionBuilder({ options, onChange }: { options: EditorOption[]; onChange: (options: EditorOption[]) => void }) {
  return (
    <div className="space-y-3">
      {options.map((option) => <OptionRow key={option.clientId} option={option} onChange={(next) => onChange(options.map((item) => item.clientId === option.clientId ? next : item))} onRemove={() => onChange(options.filter((item) => item.clientId !== option.clientId))} />)}
      <Button fullWidth={false} variant="secondary" onClick={() => onChange([...options, { clientId: clientId(), name: '', values: [] }])}><Plus size={16} />Seçenek Ekle</Button>
    </div>
  );
}

function OptionRow({ option, onChange, onRemove }: { option: EditorOption; onChange: (option: EditorOption) => void; onRemove: () => void }) {
  const [value, setValue] = useState('');
  const [dragged, setDragged] = useState<number | null>(null);

  function addValue() {
    const next = value.trim();
    if (!next || option.values.includes(next)) return;
    onChange({ ...option, values: [...option.values, next] });
    setValue('');
  }

  return (
    <div className="rounded-md border border-border bg-app-bg p-3"><div className="flex gap-2"><input value={option.name} onChange={(event) => onChange({ ...option, name: event.target.value })} placeholder="Örn. Renk" className="min-w-0 flex-1 rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary" /><Button fullWidth={false} variant="ghost" onClick={onRemove} aria-label="Seçeneği kaldır"><X size={16} /></Button></div><div className="mt-3 flex flex-wrap gap-2">{option.values.map((item, index) => <span key={`${item}-${index}`} draggable onDragStart={() => setDragged(index)} onDragOver={(event) => event.preventDefault()} onDrop={() => { if (dragged === null || dragged === index) return; const values = [...option.values]; const [moved] = values.splice(dragged, 1); values.splice(index, 0, moved); onChange({ ...option, values }); setDragged(null); }} className="inline-flex cursor-grab items-center gap-1 rounded-full border border-border bg-card px-2 py-1 text-xs text-dark active:cursor-grabbing">{item}<button type="button" onClick={() => onChange({ ...option, values: option.values.filter((valueItem) => valueItem !== item) })} aria-label={`${item} değerini kaldır`}><X size={12} /></button></span>)}</div><div className="mt-3 flex gap-2"><input value={value} onChange={(event) => setValue(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); addValue(); } }} placeholder="Değer ekle" className="min-w-0 flex-1 rounded-md border border-border bg-card px-3 py-2 text-sm outline-none focus:border-primary" /><Button fullWidth={false} variant="secondary" onClick={addValue}>Ekle</Button></div></div>
  );
}

function VariantTable({
  variants,
  locations,
  onChange,
  onInventoryChange,
}: {
  variants: EditorVariant[];
  locations: CatalogOrganization['locations'];
  onChange: (clientId: string, patch: Partial<EditorVariant>) => void;
  onInventoryChange: (clientId: string, locationId: string, quantity: number) => void;
}) {
  const [bulkPrice, setBulkPrice] = useState('');

  return (
    <div className="space-y-3"><div className="flex flex-wrap items-end gap-2 rounded-md bg-app-bg p-3"><label className="text-xs font-semibold text-muted">Tüm varyantlara fiyat uygula<input value={bulkPrice} onChange={(event) => setBulkPrice(event.target.value)} type="number" min="0" step="0.01" className="mt-1 block w-36 rounded-md border border-border bg-card px-2 py-1.5 text-sm text-dark" /></label><Button fullWidth={false} variant="secondary" onClick={() => variants.forEach((variant) => onChange(variant.clientId, { price: bulkPrice || variant.price }))}><CopyPlus size={15} />Uygula</Button></div><div className="overflow-x-auto rounded-md border border-border"><table className="min-w-[980px] w-full text-left text-sm"><thead className="bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted"><tr><th className="px-3 py-3">Varyant</th><th className="px-3 py-3">Fiyat</th><th className="px-3 py-3">SKU</th><th className="px-3 py-3">Barkod</th><th className="px-3 py-3">Envanter</th><th className="px-3 py-3">Durum</th></tr></thead><tbody className="divide-y divide-border">{variants.map((variant) => <tr key={variant.clientId}><td className="px-3 py-3 font-medium text-dark">{variant.title}</td><td className="px-3 py-3"><input value={variant.price} onChange={(event) => onChange(variant.clientId, { price: event.target.value })} type="number" min="0" step="0.01" className="w-28 rounded border border-border px-2 py-1.5 text-sm" /></td><td className="px-3 py-3"><input value={variant.sku ?? ''} onChange={(event) => onChange(variant.clientId, { sku: event.target.value || null })} className="w-36 rounded border border-border px-2 py-1.5 text-sm" /></td><td className="px-3 py-3"><input value={variant.barcode ?? ''} onChange={(event) => onChange(variant.clientId, { barcode: event.target.value || null })} className="w-36 rounded border border-border px-2 py-1.5 text-sm" /></td><td className="px-3 py-3"><label className="flex items-center gap-2 text-xs text-dark"><input type="checkbox" checked={variant.track_inventory} onChange={(event) => onChange(variant.clientId, { track_inventory: event.target.checked })} className="h-4 w-4 accent-primary" />Takip et</label>{variant.track_inventory && <div className="mt-2 space-y-1">{locations.map((location) => { const level = variant.inventory.find((item) => item.location_id === location.id); return <label key={location.id} className="flex items-center justify-between gap-2 text-xs text-muted"><span>{location.name}</span><input value={level?.available_quantity ?? 0} onChange={(event) => onInventoryChange(variant.clientId, location.id, Math.max(0, Number(event.target.value) || 0))} type="number" min="0" className="w-16 rounded border border-border px-1.5 py-1 text-right text-xs text-dark" /></label>; })}</div>}</td><td className="px-3 py-3"><select value={variant.status} onChange={(event) => onChange(variant.clientId, { status: event.target.value as ProductStatus })} className="rounded border border-border bg-card px-2 py-1.5 text-xs"><option value="active">Aktif</option><option value="draft">Taslak</option><option value="archived">Arşiv</option></select></td></tr>)}</tbody></table></div></div>
  );
}

function ProfitPreview({ variant, currency }: { variant?: EditorVariant; currency: string }) {
  if (!variant) return null;
  const price = Number(variant.price || 0);
  const cost = Number(variant.cost_price || 0);
  const profit = price - cost;
  const margin = price > 0 ? (profit / price) * 100 : 0;

  return <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 rounded-md bg-app-bg p-3 text-sm"><span className="text-muted">Kâr <strong className="ml-1 text-dark">{formatMoney(profit.toFixed(2), currency)}</strong></span><span className="text-muted">Marj <strong className="ml-1 text-dark">%{margin.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></span><span className="ml-auto text-xs text-muted"><CircleDollarSign size={14} className="mr-1 inline" />Görüntüleme hesabı</span></div>;
}
