import { useEffect, useState } from 'react';
import { Building2, CheckCircle2, FileSignature, FileText, IdCard, Loader2, UploadCloud } from 'lucide-react';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { describeApiError } from '../../../utils/errors';
import {
  getVerificationStatus,
  submitVerificationRequest,
  uploadVerificationDocument,
  type DocumentType,
} from '../../verification/api/verificationApi';
import { useAuth } from '../../../app/providers/AuthProvider';

interface DocumentDefinition {
  type: DocumentType['type'];
  label: string;
  description: string;
  required: boolean;
  icon: typeof FileText;
}

// "signature_circular" (imza sirküleri) and "business_license" (işletme
// ruhsatı) genuinely don't apply to every merchant — a sole proprietor
// (şahıs firması) typically has neither — so only the two universal
// documents are required to submit for review.
const DOCUMENTS: DocumentDefinition[] = [
  {
    type: 'tax_certificate',
    label: 'Vergi Levhası',
    description: 'Vergi dairenden aldığın güncel vergi levhası.',
    required: true,
    icon: FileText,
  },
  {
    type: 'identity',
    label: 'Kimlik',
    description: 'İşletme sahibinin T.C. kimlik kartı veya pasaportu.',
    required: true,
    icon: IdCard,
  },
  {
    type: 'signature_circular',
    label: 'İmza Sirküleri',
    description: 'Şirketler için noter onaylı imza sirküleri. Şahıs firmalarında gerekmez.',
    required: false,
    icon: FileSignature,
  },
  {
    type: 'business_license',
    label: 'İşletme Ruhsatı',
    description: 'Varsa işyeri açma ve çalışma ruhsatı.',
    required: false,
    icon: Building2,
  },
];

type SlotStatus = 'idle' | 'uploading' | 'uploaded' | 'error';
interface SlotState {
  status: SlotStatus;
  filename?: string;
  error?: string;
}

export function DocumentsStep() {
  const { refresh } = useAuth();
  const [loading, setLoading] = useState(true);
  const [slots, setSlots] = useState<Partial<Record<DocumentType['type'], SlotState>>>({});
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        const { data } = await getVerificationStatus();
        const next: Partial<Record<DocumentType['type'], SlotState>> = {};
        for (const document of data.documents) {
          next[document.type] = { status: 'uploaded', filename: document.original_filename };
        }
        setSlots(next);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const completedCount = DOCUMENTS.filter((doc) => slots[doc.type]?.status === 'uploaded').length;
  const requiredDone = DOCUMENTS.filter((doc) => doc.required).every((doc) => slots[doc.type]?.status === 'uploaded');

  async function handleFileSelect(doc: DocumentDefinition, file: File) {
    setSlots((prev) => ({ ...prev, [doc.type]: { status: 'uploading' } }));
    try {
      const uploaded = await uploadVerificationDocument(file, doc.type);
      setSlots((prev) => ({ ...prev, [doc.type]: { status: 'uploaded', filename: uploaded.original_filename } }));
    } catch (err) {
      setSlots((prev) => ({
        ...prev,
        [doc.type]: { status: 'error', error: err instanceof Error ? err.message : 'Belge yüklenemedi.' },
      }));
    }
  }

  async function handleSubmitForReview() {
    setSubmitting(true);
    setSubmitError(null);
    try {
      await submitVerificationRequest();
      await refresh();
    } catch (err) {
      setSubmitError(describeApiError(err).message ?? 'Başvuru gönderilemedi.');
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <Card>
        <p className="text-sm text-muted">Belgeler yükleniyor…</p>
      </Card>
    );
  }

  return (
    <Card>
      <div className="flex flex-col gap-6">
        <div>
          <div className="flex items-center justify-between gap-4">
            <h2 className="text-lg font-semibold text-dark">Resmi Belgeler</h2>
            <span className="shrink-0 text-sm font-medium text-muted">
              {completedCount}/{DOCUMENTS.length} yüklendi
            </span>
          </div>
          <p className="mt-1 text-sm text-muted">
            Doğrulama ekibimizin işletmeni onaylayabilmesi için aşağıdaki belgeleri yükle.
          </p>
        </div>

        <div className="flex flex-col gap-3">
          {DOCUMENTS.map((doc) => (
            <DocumentSlot
              key={doc.type}
              doc={doc}
              state={slots[doc.type] ?? { status: 'idle' }}
              onSelect={(file) => void handleFileSelect(doc, file)}
            />
          ))}
        </div>

        {!requiredDone && (
          <p className="text-sm text-amber-700">
            Devam etmek için zorunlu belgeleri (Vergi Levhası, Kimlik) yükle.
          </p>
        )}
        {submitError && <p className="text-sm text-red-600">{submitError}</p>}

        <Button onClick={() => void handleSubmitForReview()} disabled={submitting || !requiredDone}>
          {submitting ? 'Gönderiliyor…' : 'Doğrulama Başvurusunu Gönder'}
        </Button>
      </div>
    </Card>
  );
}

function DocumentSlot({
  doc,
  state,
  onSelect,
}: {
  doc: DocumentDefinition;
  state: SlotState;
  onSelect: (file: File) => void;
}) {
  const Icon = doc.icon;
  const inputId = `doc-${doc.type}`;

  return (
    <div
      className={`flex items-start gap-4 rounded-lg border p-4 transition-colors ${
        state.status === 'uploaded'
          ? 'border-emerald-200 bg-emerald-50/40'
          : state.status === 'error'
            ? 'border-red-200 bg-red-50/40'
            : 'border-border bg-app-bg'
      }`}
    >
      <div
        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${
          state.status === 'uploaded' ? 'bg-emerald-100 text-emerald-600' : 'border border-border bg-white text-dark/50'
        }`}
      >
        {state.status === 'uploading' ? (
          <Loader2 className="h-5 w-5 animate-spin" />
        ) : state.status === 'uploaded' ? (
          <CheckCircle2 className="h-5 w-5" />
        ) : (
          <Icon className="h-5 w-5" />
        )}
      </div>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-sm font-semibold text-dark">{doc.label}</p>
          <Badge tone={doc.required ? 'primary' : 'neutral'}>{doc.required ? 'Zorunlu' : 'Opsiyonel'}</Badge>
        </div>
        <p className="mt-0.5 text-xs text-muted">{doc.description}</p>

        {state.status === 'uploaded' && state.filename && (
          <p className="mt-2 truncate text-xs font-medium text-emerald-700">✓ {state.filename}</p>
        )}
        {state.status === 'error' && state.error && (
          <p className="mt-2 text-xs font-medium text-red-600">{state.error}</p>
        )}

        <label
          htmlFor={inputId}
          className="mt-3 inline-flex cursor-pointer items-center gap-1.5 rounded-control border border-border bg-white px-3 py-1.5 text-xs font-medium text-dark hover:bg-app-bg"
        >
          <UploadCloud className="h-3.5 w-3.5" />
          {state.status === 'uploaded' ? 'Değiştir' : 'Dosya Seç'}
        </label>
        <input
          id={inputId}
          type="file"
          accept=".pdf,.jpg,.jpeg,.png"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) onSelect(file);
            event.target.value = '';
          }}
        />
      </div>
    </div>
  );
}
