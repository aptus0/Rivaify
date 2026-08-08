import { apiRequest } from '../../../lib/api';

export interface DocumentType {
  type: 'tax_certificate' | 'identity' | 'signature_circular' | 'business_license' | 'other';
}

export interface VerificationDocumentSummary {
  id: string;
  type: DocumentType['type'];
  original_filename: string;
  status: 'pending' | 'approved' | 'rejected';
}

export function submitVerificationRequest(): Promise<{ data: { id: string; status: string } }> {
  return apiRequest('/api/store/verification-request', { method: 'POST' });
}

export function getVerificationStatus(): Promise<{ data: { status: string; documents: VerificationDocumentSummary[] } }> {
  return apiRequest('/api/store/verification-documents');
}

/** Server-side reasons a document upload can be rejected — kept in sync
 * with VerificationOnboardingController::uploadDocument's validation rules
 * so a 422 can be translated into the specific Turkish sentence the
 * merchant needs, instead of a generic "yüklenemedi". */
function describeUploadError(status: number, body: unknown): string {
  if (status === 422 && body && typeof body === 'object' && 'errors' in body) {
    const errors = (body as { errors: Record<string, string[]> }).errors;
    if (errors.file?.some((m) => m.toLowerCase().includes('max'))) {
      return 'Dosya çok büyük (azami 10 MB).';
    }
    if (errors.file) {
      return 'Bu dosya türü desteklenmiyor. Lütfen PDF, JPG veya PNG yükle.';
    }
    return 'Belge bilgileri eksik veya hatalı.';
  }
  if (status === 401) {
    return 'Oturumun sona ermiş görünüyor. Sayfayı yenileyip tekrar dene.';
  }
  if (status >= 500) {
    return 'Sunucu tarafında bir sorun oluştu. Birkaç dakika sonra tekrar dene; sorun devam ederse bize ulaş.';
  }
  return 'Belge yüklenemedi. Lütfen tekrar dene.';
}

export async function uploadVerificationDocument(
  file: File,
  type: DocumentType['type'],
): Promise<VerificationDocumentSummary> {
  // Multipart upload needs its own fetch call — apiRequest always
  // JSON-encodes bodies, which doesn't fit a file upload.
  const form = new FormData();
  form.append('file', file);
  form.append('type', type);

  const csrfToken = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1];
  const response = await fetch('/api/store/verification-documents', {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
    },
    body: form,
  });

  const contentType = response.headers.get('content-type') ?? '';
  const payload = contentType.includes('application/json') ? await response.json() : undefined;

  if (!response.ok) {
    throw new Error(describeUploadError(response.status, payload));
  }

  return (payload as { data: VerificationDocumentSummary }).data;
}
