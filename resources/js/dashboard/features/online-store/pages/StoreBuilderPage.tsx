import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { closestCenter, DndContext, type DragEndEvent, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowLeft, Check, ChevronDown, Copy, Eye, EyeOff, GripVertical, Laptop, LibraryBig, Loader2, Maximize2, Monitor, PanelLeftClose, PanelRightClose, Plus, Redo2, Rocket, Save, Smartphone, Trash2, Undo2 } from 'lucide-react';
import { ApiError } from '../../../lib/api';
import { Button } from '../../../components/ui/Button';
import { getThemeEditor, getThemePreviewRuntime, publishTheme, saveBuilderDocument } from '../api/onlineStoreApi';
import { useBuilderStore } from '../builder/builderStore';
import { builderDocumentSchema } from '../builder/schema';
import { sectionLibrary, sectionRegistry } from '../builder/registry';
import type { BuilderDevice, BuilderSection, StoreTheme, VersionHistoryItem } from '../types';
import { StorefrontRenderer, type RuntimeSection } from '../../../../storefront/renderer/StorefrontRenderer';
import type { StorefrontRuntime } from '../../../../storefront/types';

function previewWidth(device: BuilderDevice): string {
  return device === 'desktop' ? 'min(100%, 1180px)' : device === 'tablet' ? '768px' : '390px';
}

function saveStatusText(status: string): string {
  return {
    idle: 'Hazır',
    dirty: 'Değişiklik yapıldı',
    saving: 'Kaydediliyor...',
    saved: 'Tüm değişiklikler kaydedildi',
    conflict: 'Başka bir kullanıcı güncelledi',
    error: 'Kaydedilemedi',
  }[status] ?? status;
}

function settingString(section: BuilderSection, key: string, fallback = ''): string {
  const value = section.settings[key];
  return typeof value === 'string' ? value : fallback;
}

function settingNumber(section: BuilderSection, key: string, fallback = 0): number {
  const value = section.settings[key];
  return typeof value === 'number' ? value : fallback;
}

function SortableLayer({ section }: { section: BuilderSection }) {
  const selectedNodeId = useBuilderStore((state) => state.selectedNodeId);
  const selectNode = useBuilderStore((state) => state.selectNode);
  const toggleSection = useBuilderStore((state) => state.toggleSection);
  const duplicateSection = useBuilderStore((state) => state.duplicateSection);
  const removeSection = useBuilderStore((state) => state.removeSection);
  const definition = sectionRegistry[section.type] ?? { label: section.type, icon: LibraryBig };
  const sortable = useSortable({ id: section.id, disabled: section.locked });
  const Icon = definition.icon;

  return (
    <div
      ref={sortable.setNodeRef}
      style={{ transform: CSS.Transform.toString(sortable.transform), transition: sortable.transition }}
      className={`group flex items-center gap-2 rounded-md border px-2 py-2 text-sm ${selectedNodeId === section.id ? 'border-primary bg-primary/5' : 'border-border bg-white'}`}
      onClick={() => selectNode(section.id)}
    >
      <button type="button" className="cursor-grab text-muted disabled:cursor-not-allowed disabled:opacity-40" disabled={section.locked} {...sortable.attributes} {...sortable.listeners}>
        <GripVertical className="h-4 w-4" />
      </button>
      <Icon className="h-4 w-4 text-primary" />
      <span className="min-w-0 flex-1 truncate font-medium text-dark">{definition.label}</span>
      {section.locked ? <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-500">LOCK</span> : null}
      <button type="button" className="text-muted hover:text-dark" onClick={(event) => { event.stopPropagation(); toggleSection(section.id); }}>
        {section.enabled ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
      </button>
      <button type="button" className="text-muted hover:text-dark" onClick={(event) => { event.stopPropagation(); duplicateSection(section.id); }}>
        <Copy className="h-4 w-4" />
      </button>
      <button type="button" className="text-muted hover:text-red-600 disabled:opacity-30" disabled={section.locked} onClick={(event) => { event.stopPropagation(); removeSection(section.id); }}>
        <Trash2 className="h-4 w-4" />
      </button>
    </div>
  );
}

type BuilderPanelTab = 'sections' | 'theme' | 'apps' | 'pages';

function LayersPanel({ onAdd }: { onAdd: () => void }) {
  const document = useBuilderStore((state) => state.document);
  const reorderSections = useBuilderStore((state) => state.reorderSections);
  const [tab, setTab] = useState<BuilderPanelTab>('sections');
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  function onDragEnd(event: DragEndEvent) {
    if (event.over && event.active.id !== event.over.id) {
      reorderSections(String(event.active.id), String(event.over.id));
    }
  }

  return (
    <aside className="flex min-h-0 w-72 flex-col border-r border-border bg-card">
      <div className="border-b border-border p-4">
        <p className="text-xs font-semibold uppercase tracking-[.16em] text-muted">Builder</p>
        <div className="mt-3 grid grid-cols-4 gap-1 rounded-md bg-app-bg p-1">
          {[
            ['sections', 'Sections'],
            ['theme', 'Theme'],
            ['apps', 'Apps'],
            ['pages', 'Pages'],
          ].map(([value, label]) => (
            <button type="button" key={value} className={`rounded px-1.5 py-1.5 text-[11px] font-semibold ${tab === value ? 'bg-white text-dark shadow-sm' : 'text-muted'}`} onClick={() => setTab(value as BuilderPanelTab)}>
              {label}
            </button>
          ))}
        </div>
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto p-3">
        {tab === 'sections' ? (
          <>
            <button type="button" className="mb-3 inline-flex w-full items-center justify-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-dark hover:bg-app-bg" onClick={onAdd}>
              <Plus className="h-4 w-4" />Bölüm Ekle
            </button>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
              <SortableContext items={document?.sections.map((section) => section.id) ?? []} strategy={verticalListSortingStrategy}>
                <div className="space-y-2">
                  {document?.sections.map((section) => <SortableLayer key={section.id} section={section} />)}
                </div>
              </SortableContext>
            </DndContext>
          </>
        ) : (
          <div className="space-y-2">
            {(tab === 'theme' ? ['Colors', 'Typography', 'Buttons', 'Cards'] : tab === 'apps' ? ['Product reviews', 'Upsell block', 'Cookie consent'] : ['Home', 'Product template', 'Collection template', 'Footer policies']).map((item) => (
              <div className="rounded-md border border-border bg-white px-3 py-2 text-sm font-medium text-dark" key={item}>{item}</div>
            ))}
          </div>
        )}
      </div>
    </aside>
  );
}

function SectionLibraryPanel({ open, onClose }: { open: boolean; onClose: () => void }) {
  const addSection = useBuilderStore((state) => state.addSection);
  const [query, setQuery] = useState('');
  const filtered = sectionLibrary.filter((section) => `${section.label} ${section.category}`.toLowerCase().includes(query.toLowerCase()));
  const categories = Array.from(new Set(filtered.map((section) => section.category)));

  if (!open) return null;

  return (
    <div className="absolute left-72 top-[57px] z-20 h-[calc(100vh-57px)] w-96 border-r border-border bg-white shadow-xl">
      <div className="border-b border-border p-4">
        <div className="flex items-center justify-between"><h2 className="text-lg font-semibold text-dark">Bölüm Ekle</h2><button type="button" className="text-sm text-muted" onClick={onClose}>Kapat</button></div>
        <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Bölüm ara" className="mt-4 w-full rounded-md border border-border px-3 py-2 text-sm outline-none focus:border-primary" />
      </div>
      <div className="h-full overflow-y-auto p-4 pb-24">
        {categories.map((category) => (
          <div className="mb-5" key={category}>
            <p className="mb-2 text-xs font-semibold uppercase tracking-[.16em] text-muted">{category}</p>
            <div className="grid gap-2">
              {filtered.filter((section) => section.category === category).map((section) => (
                <button type="button" className="flex items-center gap-3 rounded-md border border-border p-3 text-left hover:border-primary hover:bg-primary/5" key={section.type} onClick={() => { addSection(section.type); onClose(); }}>
                  <section.icon className="h-5 w-5 text-primary" />
                  <div><div className="font-medium text-dark">{section.label}</div><div className="text-xs text-muted">Preset ve schema hazır</div></div>
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function PreviewCanvas({ runtime, addMode }: { runtime: StorefrontRuntime | null; addMode?: { anchorId: string; position: 'before' | 'after' } | null }) {
  const document = useBuilderStore((state) => state.document);
  const device = useBuilderStore((state) => state.device);
  const addSectionAt = useBuilderStore((state) => state.addSectionAt);
  const selectedNodeId = useBuilderStore((state) => state.selectedNodeId);
  const selectNode = useBuilderStore((state) => state.selectNode);
  const updateSectionSettings = useBuilderStore((state) => state.updateSectionSettings);
  const toggleSection = useBuilderStore((state) => state.toggleSection);
  const duplicateSection = useBuilderStore((state) => state.duplicateSection);
  const removeSection = useBuilderStore((state) => state.removeSection);
  const [pendingAdd, setPendingAdd] = useState<{ anchorId: string; position: 'before' | 'after' } | null>(null);
  const insertion = addMode ?? pendingAdd;

  return (
    <main className="min-w-0 flex-1 overflow-auto bg-[#EEEFF1] p-6">
      <div className="mx-auto overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-zinc-200 transition-all" style={{ width: previewWidth(device) }}>
        {runtime && document ? (
          <StorefrontRenderer
            store={runtime.store}
            document={document}
            products={runtime.products}
            mode="preview"
            selectedSectionId={selectedNodeId}
            onSelectSection={selectNode}
            onTextChange={(sectionId, field, value) => updateSectionSettings(sectionId, { [field]: value })}
            renderSectionOverlay={(section: RuntimeSection) => (
              <>
                {insertion?.anchorId === section.id && insertion.position === 'before' ? <SectionInsertLine anchorId={section.id} position="before" onChoose={(type) => { addSectionAt(type, section.id, 'before'); setPendingAdd(null); }} /> : null}
                <div className={`absolute left-3 top-3 z-30 rounded-md border px-2 py-1 text-[11px] font-semibold shadow-sm ${selectedNodeId === section.id ? 'border-[#FF6B00] bg-[#FFF2E8] text-[#B54708]' : 'border-zinc-200 bg-white/95 text-zinc-700 opacity-0 group-hover:opacity-100'}`}>{sectionRegistry[section.type as BuilderSection['type']]?.label ?? section.type}</div>
                {selectedNodeId === section.id ? (
                  <div className="absolute right-3 top-3 z-40 flex items-center gap-1 rounded-md border border-zinc-200 bg-white p-1 shadow-sm">
                    <button type="button" className="rounded p-1.5 text-zinc-600 hover:bg-zinc-100" onClick={(event) => { event.stopPropagation(); setPendingAdd({ anchorId: section.id, position: 'before' }); }} aria-label="Üste bölüm ekle"><Plus className="h-4 w-4 rotate-180" /></button>
                    <button type="button" className="rounded p-1.5 text-zinc-600 hover:bg-zinc-100" onClick={(event) => { event.stopPropagation(); setPendingAdd({ anchorId: section.id, position: 'after' }); }} aria-label="Alta bölüm ekle"><Plus className="h-4 w-4" /></button>
                    <button type="button" className="rounded p-1.5 text-zinc-600 hover:bg-zinc-100" onClick={(event) => { event.stopPropagation(); duplicateSection(section.id); }} aria-label="Kopyala"><Copy className="h-4 w-4" /></button>
                    <button type="button" className="rounded p-1.5 text-zinc-600 hover:bg-zinc-100" onClick={(event) => { event.stopPropagation(); toggleSection(section.id); }} aria-label="Gizle"><EyeOff className="h-4 w-4" /></button>
                    <button type="button" className="rounded p-1.5 text-zinc-600 hover:bg-red-50 hover:text-red-600 disabled:opacity-40" disabled={Boolean(section.locked)} onClick={(event) => { event.stopPropagation(); removeSection(section.id); }} aria-label="Sil"><Trash2 className="h-4 w-4" /></button>
                  </div>
                ) : null}
                {insertion?.anchorId === section.id && insertion.position === 'after' ? <SectionInsertLine anchorId={section.id} position="after" onChoose={(type) => { addSectionAt(type, section.id, 'after'); setPendingAdd(null); }} /> : null}
              </>
            )}
          />
        ) : <div className="grid min-h-96 place-items-center text-sm text-muted">Storefront runtime yükleniyor...</div>}
      </div>
    </main>
  );
}

function SectionInsertLine({ onChoose }: { anchorId: string; position: 'before' | 'after'; onChoose: (type: BuilderSection['type']) => void }) {
  return (
    <div className="flex items-center gap-2 bg-[#FFF2E8] px-4 py-2 text-xs text-[#B54708]">
      <span className="h-px flex-1 bg-[#FF6B00]" />
      <span className="font-semibold">Buraya bölüm ekle</span>
      <select className="rounded-md border border-[#FF6B00]/30 bg-white px-2 py-1 text-xs text-dark" defaultValue="" onChange={(event) => event.target.value && onChoose(event.target.value as BuilderSection['type'])}>
        <option value="" disabled>Seç</option>
        {sectionLibrary.slice(0, 8).map((section) => <option key={section.type} value={section.type}>{section.label}</option>)}
      </select>
      <span className="h-px flex-1 bg-[#FF6B00]" />
    </div>
  );
}

function InspectorPanel() {
  const document = useBuilderStore((state) => state.document);
  const selectedNodeId = useBuilderStore((state) => state.selectedNodeId);
  const updateSectionSettings = useBuilderStore((state) => state.updateSectionSettings);
  const selected = document?.sections.find((section) => section.id === selectedNodeId) ?? null;

  if (!selected) {
    return <aside className="w-80 border-l border-border bg-card p-4"><p className="text-sm text-muted">Bir bölüm seçin.</p></aside>;
  }

  const definition = sectionRegistry[selected.type] ?? { label: selected.type, allowedBlocks: [] };

  return (
    <aside className="min-h-0 w-80 overflow-y-auto border-l border-border bg-card">
      <div className="border-b border-border p-4">
        <p className="text-xs font-semibold uppercase tracking-[.16em] text-muted">Ayarlar</p>
        <h2 className="mt-2 text-lg font-semibold text-dark">{definition.label}</h2>
      </div>
      <div className="space-y-6 p-4">
        <section>
          <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">İçerik</h3>
          {'title' in selected.settings ? <label className="mb-3 block text-sm font-medium text-dark">Başlık<input className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={settingString(selected, 'title')} onChange={(event) => updateSectionSettings(selected.id, { title: event.target.value })} /></label> : null}
          {'subtitle' in selected.settings ? <label className="mb-3 block text-sm font-medium text-dark">Alt Başlık<input className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={settingString(selected, 'subtitle')} onChange={(event) => updateSectionSettings(selected.id, { subtitle: event.target.value })} /></label> : null}
          {'button_label' in selected.settings ? <label className="mb-3 block text-sm font-medium text-dark">Button<input className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={settingString(selected, 'button_label')} onChange={(event) => updateSectionSettings(selected.id, { button_label: event.target.value })} /></label> : null}
          {'text' in selected.settings ? <label className="mb-3 block text-sm font-medium text-dark">Metin<input className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={settingString(selected, 'text')} onChange={(event) => updateSectionSettings(selected.id, { text: event.target.value })} /></label> : null}
          {'body' in selected.settings ? <label className="mb-3 block text-sm font-medium text-dark">Gövde<textarea className="mt-1 min-h-24 w-full rounded-md border border-border px-3 py-2 text-sm" value={settingString(selected, 'body')} onChange={(event) => updateSectionSettings(selected.id, { body: event.target.value })} /></label> : null}
        </section>
        <section>
          <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Tasarım</h3>
          {'alignment' in selected.settings ? (
            <div className="mb-3">
              <p className="mb-2 text-sm font-medium text-dark">Hizalama</p>
              <div className="grid grid-cols-3 gap-1 rounded-md bg-app-bg p-1">
                {['left', 'center', 'right'].map((alignment) => <button type="button" key={alignment} className={`rounded px-2 py-1.5 text-xs font-medium ${settingString(selected, 'alignment') === alignment ? 'bg-white text-dark shadow-sm' : 'text-muted'}`} onClick={() => updateSectionSettings(selected.id, { alignment })}>{alignment}</button>)}
              </div>
            </div>
          ) : null}
          {'overlay' in selected.settings ? <label className="block text-sm font-medium text-dark">Overlay<input type="range" min="0" max="80" className="mt-2 w-full" value={settingNumber(selected, 'overlay', 0)} onChange={(event) => updateSectionSettings(selected.id, { overlay: Number(event.target.value) })} /></label> : null}
          {'height' in selected.settings ? (
            <label className="mt-3 block text-sm font-medium text-dark">Yükseklik
              <select className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm" value={settingString(selected, 'height', 'medium')} onChange={(event) => updateSectionSettings(selected.id, { height: event.target.value })}>
                <option value="small">Kompakt</option>
                <option value="medium">Orta</option>
                <option value="large">Geniş</option>
              </select>
            </label>
          ) : null}
          {'scheme' in selected.settings ? (
            <label className="mt-3 block text-sm font-medium text-dark">Renk düzeni
              <select className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm" value={settingString(selected, 'scheme', 'light')} onChange={(event) => updateSectionSettings(selected.id, { scheme: event.target.value })}>
                <option value="light">Açık</option>
                <option value="brand">Marka</option>
                <option value="dark">Koyu</option>
              </select>
            </label>
          ) : null}
        </section>
        {selected.type === 'header' ? (
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Header Builder</h3>
            <label className="mb-3 block text-sm font-medium text-dark">Preset
              <select className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm" value={settingString(selected, 'preset', 'classic')} onChange={(event) => updateSectionSettings(selected.id, { preset: event.target.value })}>
                <option value="classic">Classic Commerce</option>
                <option value="centered">Centered Brand</option>
                <option value="minimal">Minimal</option>
                <option value="search-focused">Search Focused</option>
                <option value="mega-commerce">Mega Commerce</option>
                <option value="luxury">Luxury</option>
                <option value="retail">Marketplace / Retail</option>
              </select>
            </label>
            <div className="grid gap-2">
              {[
                ['sticky', 'Sticky Header'],
                ['show_search', 'Search'],
                ['show_wishlist', 'Favorites'],
                ['show_account', 'Account'],
                ['show_cart', 'Cart'],
              ].map(([key, label]) => (
                <label key={key} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                  <span className="text-dark">{label}</span>
                  <input type="checkbox" checked={typeof selected.settings[key] === 'boolean' ? Boolean(selected.settings[key]) : true} onChange={(event) => updateSectionSettings(selected.id, { [key]: event.target.checked })} />
                </label>
              ))}
            </div>
          </section>
        ) : null}
        {selected.type === 'footer' ? (
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Footer Builder</h3>
            <label className="block text-sm font-medium text-dark">Preset
              <select className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm" value={settingString(selected, 'preset', 'large-commerce')} onChange={(event) => updateSectionSettings(selected.id, { preset: event.target.value })}>
                <option value="large-commerce">Large Commerce</option>
                <option value="classic">Classic Commerce</option>
                <option value="minimal">Minimal</option>
                <option value="centered">Centered</option>
              </select>
            </label>
          </section>
        ) : null}
        {'columns' in selected.settings ? <ColumnsEditor section={selected} /> : null}
        {'image_position' in selected.settings || 'source' in selected.settings ? (
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Kaynak</h3>
            {'image_position' in selected.settings ? (
              <label className="mb-3 block text-sm font-medium text-dark">Görsel konumu
                <select className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm" value={settingString(selected, 'image_position', 'left')} onChange={(event) => updateSectionSettings(selected.id, { image_position: event.target.value })}>
                  <option value="left">Sol</option>
                  <option value="right">Sağ</option>
                </select>
              </label>
            ) : null}
            {'source' in selected.settings ? (
              <label className="block text-sm font-medium text-dark">Dynamic source
                <input className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={typeof (selected.settings.source as { label?: unknown } | undefined)?.label === 'string' ? String((selected.settings.source as { label?: unknown }).label) : ''} onChange={(event) => updateSectionSettings(selected.id, { source: { type: 'collection', label: event.target.value } })} placeholder="Koleksiyon adı" />
              </label>
            ) : null}
          </section>
        ) : null}
        <section>
          <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Responsive</h3>
          <div className="grid gap-2">
            {(['desktop', 'tablet', 'mobile'] as const).map((device) => {
              const visibility = selected.settings.visibility as Record<string, boolean> | undefined;
              return (
                <label key={device} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                  <span className="capitalize text-dark">{device}</span>
                  <input
                    type="checkbox"
                    checked={visibility?.[device] ?? true}
                    onChange={(event) => updateSectionSettings(selected.id, { visibility: { desktop: true, tablet: true, mobile: true, ...visibility, [device]: event.target.checked } })}
                  />
                </label>
              );
            })}
          </div>
        </section>
        <section>
          <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Advanced</h3>
          <div className="rounded-md border border-border bg-app-bg p-3 text-xs text-muted">
            Section ID: <span className="font-mono text-dark">{selected.id}</span>
            {definition.allowedBlocks?.length ? <div className="mt-2">Blocks: {definition.allowedBlocks.join(', ')}</div> : null}
          </div>
        </section>
      </div>
    </aside>
  );
}

function ColumnsEditor({ section }: { section: BuilderSection }) {
  const updateSectionSettings = useBuilderStore((state) => state.updateSectionSettings);
  const columns = section.settings.columns as { desktop?: number; tablet?: number; mobile?: number } | undefined;
  return (
    <section>
      <h3 className="mb-3 text-xs font-semibold uppercase tracking-[.16em] text-muted">Düzen</h3>
      {(['desktop', 'tablet', 'mobile'] as const).map((device) => (
        <label className="mb-3 block text-sm font-medium text-dark" key={device}>{device}
          <input type="number" min={1} max={6} className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm" value={columns?.[device] ?? 1} onChange={(event) => updateSectionSettings(section.id, { columns: { ...columns, [device]: Number(event.target.value) } })} />
        </label>
      ))}
    </section>
  );
}

function TopBar({
  theme,
  onPublish,
  onSave,
  onPreview,
  onToggleLeft,
  onToggleRight,
  publishing,
  savingNow,
}: {
  theme: StoreTheme | null;
  onPublish: () => void;
  onSave: () => void;
  onPreview: () => void;
  onToggleLeft: () => void;
  onToggleRight: () => void;
  publishing: boolean;
  savingNow: boolean;
}) {
  const setDevice = useBuilderStore((state) => state.setDevice);
  const device = useBuilderStore((state) => state.device);
  const undo = useBuilderStore((state) => state.undo);
  const redo = useBuilderStore((state) => state.redo);
  const saveStatus = useBuilderStore((state) => state.saveStatus);

  return (
    <header className="flex h-[57px] items-center justify-between border-b border-border bg-white px-4">
      <div className="flex items-center gap-3">
        <Link to="/online-store" className="inline-flex items-center gap-2 text-sm font-semibold text-dark"><ArrowLeft className="h-4 w-4" />Rivaify</Link>
        <button type="button" className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" onClick={onToggleLeft} aria-label="Sol paneli aç/kapat"><PanelLeftClose className="h-4 w-4" /></button>
        <button type="button" className="inline-flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm text-dark">Ana Sayfa <ChevronDown className="h-4 w-4" /></button>
      </div>
      <div className="flex items-center gap-1 rounded-md bg-app-bg p-1">
        {[
          ['desktop', Monitor],
          ['tablet', Laptop],
          ['mobile', Smartphone],
        ].map(([value, Icon]) => <button type="button" key={value as string} className={`rounded p-2 ${device === value ? 'bg-white text-dark shadow-sm' : 'text-muted'}`} onClick={() => setDevice(value as BuilderDevice)}><Icon className="h-4 w-4" /></button>)}
      </div>
      <div className="flex items-center gap-2">
        <span className={`hidden text-xs sm:inline ${saveStatus === 'conflict' || saveStatus === 'error' ? 'text-red-600' : 'text-muted'}`}>{saveStatusText(saveStatus)}</span>
        <Button type="button" variant="ghost" fullWidth={false} onClick={undo}><Undo2 className="h-4 w-4" /></Button>
        <Button type="button" variant="ghost" fullWidth={false} onClick={redo}><Redo2 className="h-4 w-4" /></Button>
        <Button type="button" variant="secondary" fullWidth={false} onClick={onPreview}><Maximize2 className="h-4 w-4" />Önizle</Button>
        <Button type="button" variant="ghost" fullWidth={false} onClick={onToggleRight}><PanelRightClose className="h-4 w-4" /></Button>
        <Button type="button" variant="secondary" fullWidth={false} disabled={savingNow} onClick={onSave}>{savingNow ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}Kaydet</Button>
        <Button type="button" fullWidth={false} disabled={!theme || publishing} onClick={onPublish}>{publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}Yayınla</Button>
      </div>
    </header>
  );
}

function PreviewOnlyBar({ onClose }: { onClose: () => void }) {
  const setDevice = useBuilderStore((state) => state.setDevice);
  const device = useBuilderStore((state) => state.device);

  return (
    <header className="flex h-[57px] items-center justify-between border-b border-border bg-white px-4">
      <div>
        <p className="text-sm font-semibold text-dark">Tema önizleme</p>
        <p className="text-xs text-muted">Temiz görünüm modu</p>
      </div>
      <div className="flex items-center gap-1 rounded-md bg-app-bg p-1">
        {[
          ['desktop', Monitor],
          ['tablet', Laptop],
          ['mobile', Smartphone],
        ].map(([value, Icon]) => <button type="button" key={value as string} className={`rounded p-2 ${device === value ? 'bg-white text-dark shadow-sm' : 'text-muted'}`} onClick={() => setDevice(value as BuilderDevice)}><Icon className="h-4 w-4" /></button>)}
      </div>
      <Button type="button" variant="secondary" fullWidth={false} onClick={onClose}>Editöre dön</Button>
    </header>
  );
}

export function StoreBuilderPage() {
  const { themeId } = useParams();
  const [theme, setTheme] = useState<StoreTheme | null>(null);
  const [documentId, setDocumentId] = useState<string | null>(null);
  const [history, setHistory] = useState<VersionHistoryItem[]>([]);
  const [libraryOpen, setLibraryOpen] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [previewMode, setPreviewMode] = useState(false);
  const [savingNow, setSavingNow] = useState(false);
  const [leftCollapsed, setLeftCollapsed] = useState(false);
  const [rightCollapsed, setRightCollapsed] = useState(false);
  const [runtime, setRuntime] = useState<StorefrontRuntime | null>(null);
  const document = useBuilderStore((state) => state.document);
  const revision = useBuilderStore((state) => state.revision);
  const isDirty = useBuilderStore((state) => state.isDirty);
  const setInitialDocument = useBuilderStore((state) => state.setInitialDocument);
  const markSaving = useBuilderStore((state) => state.markSaving);
  const markSaved = useBuilderStore((state) => state.markSaved);
  const markConflict = useBuilderStore((state) => state.markConflict);
  const markError = useBuilderStore((state) => state.markError);

  useEffect(() => {
    if (!themeId) return;
    getThemeEditor(themeId).then((response) => {
      const firstDocument = response.data.documents[0];
      setTheme(response.data.theme);
      setDocumentId(firstDocument.id);
      setHistory(response.data.version_history);
      setInitialDocument(firstDocument.document, firstDocument.revision);
    });
    getThemePreviewRuntime(themeId).then((response) => setRuntime(response.data)).catch(() => setRuntime(null));
  }, [setInitialDocument, themeId]);

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      const modifier = event.ctrlKey || event.metaKey;
      if (!modifier) return;
      if (event.key.toLowerCase() === 'z' && event.shiftKey) {
        event.preventDefault();
        useBuilderStore.getState().redo();
      } else if (event.key.toLowerCase() === 'z') {
        event.preventDefault();
        useBuilderStore.getState().undo();
      }
    }
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  const saveCurrentDocument = useCallback(async () => {
    if (!document || !documentId) return;
    const parsed = builderDocumentSchema.safeParse(document);
    if (!parsed.success) {
      markError();
      return;
    }
    setSavingNow(true);
    markSaving();
    try {
      const response = await saveBuilderDocument(documentId, revision, document);
      markSaved(response.data.revision);
    } catch (error: unknown) {
      if (error instanceof ApiError && error.status === 409) markConflict();
      else markError();
    } finally {
      setSavingNow(false);
    }
  }, [document, documentId, markConflict, markError, markSaved, markSaving, revision]);

  useEffect(() => {
    if (!document || !documentId || !isDirty) return;
    const timeout = window.setTimeout(() => {
      void saveCurrentDocument();
    }, 900);
    return () => window.clearTimeout(timeout);
  }, [document, documentId, isDirty, saveCurrentDocument]);

  const selectedTypes = useMemo(() => document?.sections.map((section) => section.type).join(', ') ?? '', [document]);

  async function onPublish() {
    if (!theme) return;
    setPublishing(true);
    try {
      const response = await publishTheme(theme.id);
      setTheme(response.data.theme);
      setHistory((items) => [{ id: response.data.version.id, version: response.data.version.number, status: response.data.version.status, published_at: response.data.version.published_at, created_at: response.data.version.published_at }, ...items]);
    } finally {
      setPublishing(false);
    }
  }

  if (!document) {
    return <div className="flex h-screen items-center justify-center bg-app-bg text-sm text-muted"><Loader2 className="mr-2 h-4 w-4 animate-spin" />Builder yükleniyor</div>;
  }

  if (previewMode) {
    return (
      <div className="h-screen overflow-hidden bg-app-bg text-dark">
        <PreviewOnlyBar onClose={() => setPreviewMode(false)} />
        <div className="h-[calc(100vh-57px)]">
          <PreviewCanvas runtime={runtime} />
        </div>
      </div>
    );
  }

  return (
    <div className="h-screen overflow-hidden bg-app-bg text-dark">
      <TopBar
        theme={theme}
        onPublish={onPublish}
        onSave={() => void saveCurrentDocument()}
        onPreview={() => setPreviewMode(true)}
        onToggleLeft={() => setLeftCollapsed((value) => !value)}
        onToggleRight={() => setRightCollapsed((value) => !value)}
        publishing={publishing}
        savingNow={savingNow}
      />
      <div className="relative flex h-[calc(100vh-57px)] min-h-0">
        {leftCollapsed ? (
          <aside className="flex w-14 flex-col items-center gap-2 border-r border-border bg-white py-3">
            <button type="button" className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" onClick={() => setLeftCollapsed(false)} aria-label="Sol paneli aç"><PanelLeftClose className="h-4 w-4 rotate-180" /></button>
            <button type="button" className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" onClick={() => setLibraryOpen(true)} aria-label="Bölüm ekle"><Plus className="h-4 w-4" /></button>
          </aside>
        ) : <LayersPanel onAdd={() => setLibraryOpen(true)} />}
        <SectionLibraryPanel open={libraryOpen} onClose={() => setLibraryOpen(false)} />
        <PreviewCanvas runtime={runtime} />
        {rightCollapsed ? (
          <aside className="flex w-14 flex-col items-center border-l border-border bg-white py-3">
            <button type="button" className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" onClick={() => setRightCollapsed(false)} aria-label="Inspector aç"><PanelRightClose className="h-4 w-4 rotate-180" /></button>
          </aside>
        ) : <InspectorPanel />}
      </div>
      <div className="pointer-events-none fixed bottom-4 left-1/2 z-30 flex -translate-x-1/2 items-center gap-3 rounded-md border border-border bg-white px-4 py-2 text-xs text-muted shadow-lg">
        <Check className="h-4 w-4 text-emerald-600" />Draft ayrı tutulur · Sections: {selectedTypes || 'empty'} · Versions: {history.length}
        <LibraryBig className="h-4 w-4 text-primary" />
      </div>
    </div>
  );
}
