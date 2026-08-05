import { apiRequest } from '../../../lib/api';

export interface DocumentType {
  type: 'tax_certificate' | 'identity' | 'signature_circular' | 'business_license' | 'other';
}

export function submitVerificationRequest(): Promise<{ data: { id: string; status: string } }> {
  return apiRequest('/api/store/verification-request', { method: 'POST' });
}

export async function uploadVerificationDocument(file: File, type: DocumentType['type']): Promise<void> {
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

  if (!response.ok) {
    throw new Error('Belge yüklenemedi');
  }
}
