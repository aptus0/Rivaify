import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from './Button';

export function Pagination({
  currentPage,
  lastPage,
  onChange,
}: {
  currentPage: number;
  lastPage: number;
  onChange: (page: number) => void;
}) {
  if (lastPage < 2) return null;

  return (
    <div className="flex items-center justify-between border-t border-border px-4 py-3">
      <p className="text-sm text-muted">Sayfa {currentPage} / {lastPage}</p>
      <div className="flex gap-2">
        <Button fullWidth={false} variant="secondary" disabled={currentPage === 1} onClick={() => onChange(currentPage - 1)} aria-label="Önceki sayfa"><ChevronLeft size={16} /></Button>
        <Button fullWidth={false} variant="secondary" disabled={currentPage === lastPage} onClick={() => onChange(currentPage + 1)} aria-label="Sonraki sayfa"><ChevronRight size={16} /></Button>
      </div>
    </div>
  );
}