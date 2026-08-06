import { useRef, useState } from 'react';
import { GripVertical, ImagePlus, Star, Trash2, UploadCloud, X } from 'lucide-react';
import type { ProductMedia } from '../api/types';

export interface PendingProductMedia {
  id: string;
  file: File;
  previewUrl: string;
  altText: string;
}

export function ProductMediaUploader({
  media,
  pending,
  uploading,
  onFiles,
  onDelete,
  onReorder,
  onMetadata,
  onRemovePending,
  onPendingAltChange,
}: {
  media: ProductMedia[];
  pending: PendingProductMedia[];
  uploading: boolean;
  onFiles: (files: File[]) => void;
  onDelete: (media: ProductMedia) => void;
  onReorder: (mediaIds: string[]) => void;
  onMetadata: (media: ProductMedia, next: { altText: string; isFeatured: boolean }) => void;
  onRemovePending: (id: string) => void;
  onPendingAltChange: (id: string, altText: string) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [draggedId, setDraggedId] = useState<string | null>(null);
  const [isDraggingFiles, setIsDraggingFiles] = useState(false);

  function acceptFiles(files: FileList | null) {
    if (!files) return;
    onFiles(Array.from(files).filter((file) => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes(file.type)));
  }

  function reorder(targetId: string) {
    if (!draggedId || draggedId === targetId) return;
    const ids = media.map((item) => item.id);
    const sourceIndex = ids.indexOf(draggedId);
    const targetIndex = ids.indexOf(targetId);
    ids.splice(sourceIndex, 1);
    ids.splice(targetIndex, 0, draggedId);
    onReorder(ids);
    setDraggedId(null);
  }

  return (
    <div className="space-y-4">
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        onDragOver={(event) => {
          event.preventDefault();
          setIsDraggingFiles(true);
        }}
        onDragLeave={() => setIsDraggingFiles(false)}
        onDrop={(event) => {
          event.preventDefault();
          setIsDraggingFiles(false);
          acceptFiles(event.dataTransfer.files);
        }}
        className={`grid min-h-40 w-full place-items-center rounded-md border border-dashed p-6 text-center transition ${
          isDraggingFiles ? 'border-primary bg-surface-orange' : 'border-border bg-app-bg hover:border-primary'
        }`}
      >
        <span>
          <UploadCloud className="mx-auto text-muted" size={28} />
          <span className="mt-3 block text-sm font-medium text-dark">Medya yüklemek için sürükleyin</span>
          <span className="mt-1 block text-xs text-muted">JPG, PNG, WEBP veya AVIF · En fazla 20 MB</span>
        </span>
      </button>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/avif"
        multiple
        className="hidden"
        onChange={(event) => {
          acceptFiles(event.target.files);
          event.target.value = '';
        }}
      />

      {(media.length > 0 || pending.length > 0) && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {media.map((item) => (
            <div
              key={item.id}
              draggable={!uploading}
              onDragStart={() => setDraggedId(item.id)}
              onDragOver={(event) => event.preventDefault()}
              onDrop={() => reorder(item.id)}
              className="overflow-hidden rounded-md border border-border bg-card"
            >
              <div className="relative aspect-[4/3] bg-app-bg">
                <img src={item.url} alt={item.alt_text ?? item.original_filename} className="h-full w-full object-cover" />
                <span className="absolute left-2 top-2 rounded bg-card/90 p-1 text-muted"><GripVertical size={15} /></span>
                <button type="button" onClick={() => onDelete(item)} className="absolute right-2 top-2 rounded bg-card/90 p-1 text-muted hover:text-red-600" aria-label="Medyayı sil"><Trash2 size={15} /></button>
                <button
                  type="button"
                  onClick={() => onMetadata(item, { altText: item.alt_text ?? '', isFeatured: !item.is_featured })}
                  className={`absolute bottom-2 right-2 rounded p-1 ${item.is_featured ? 'bg-primary text-white' : 'bg-card/90 text-muted'}`}
                  aria-label="Öne çıkan görsel seç"
                ><Star size={15} fill={item.is_featured ? 'currentColor' : 'none'} /></button>
              </div>
              <div className="p-3">
                <input
                  defaultValue={item.alt_text ?? ''}
                  onBlur={(event) => onMetadata(item, { altText: event.target.value, isFeatured: item.is_featured })}
                  placeholder="Alt metin"
                  className="w-full border-0 bg-transparent text-xs text-dark outline-none placeholder:text-muted"
                />
              </div>
            </div>
          ))}
          {pending.map((item) => (
            <div key={item.id} className="overflow-hidden rounded-md border border-dashed border-primary bg-surface-orange">
              <div className="relative aspect-[4/3] bg-app-bg"><img src={item.previewUrl} alt={item.altText || item.file.name} className="h-full w-full object-cover" /><span className="absolute bottom-2 left-2 rounded bg-card/90 px-2 py-1 text-xs font-medium text-primary-hover">Kaydedilecek</span><button type="button" onClick={() => onRemovePending(item.id)} className="absolute right-2 top-2 rounded bg-card/90 p-1 text-muted hover:text-red-600" aria-label="Bekleyen medyayı kaldır"><X size={15} /></button></div>
              <div className="p-3"><input value={item.altText} onChange={(event) => onPendingAltChange(item.id, event.target.value)} placeholder="Alt metin" className="w-full border-0 bg-transparent text-xs text-dark outline-none placeholder:text-muted" /></div>
            </div>
          ))}
        </div>
      )}
      {uploading && <p className="flex items-center gap-2 text-xs text-muted"><ImagePlus size={14} />Medya yükleniyor...</p>}
    </div>
  );
}