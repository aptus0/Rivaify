import { apiRequest } from '../../../lib/api';

export interface BusinessAddressInput {
  type: 'registered' | 'billing' | 'shipping';
  line1: string;
  line2?: string;
  city: string;
  state?: string;
  postal_code?: string;
  country_code: string;
}

export interface BusinessProfileInput {
  legal_name: string;
  trade_name?: string;
  registration_number?: string;
  contact_email?: string;
  contact_phone?: string;
  addresses: BusinessAddressInput[];
}

export interface TaxProfileInput {
  tax_number: string;
  legal_entity_name: string;
  tax_office: string;
}

export function submitBusinessProfile(payload: BusinessProfileInput): Promise<{ data: { id: string } }> {
  return apiRequest('/api/store/business-profile', { method: 'POST', body: payload });
}

export function submitTaxProfile(payload: TaxProfileInput): Promise<{ data: { id: string } }> {
  return apiRequest('/api/store/tax-profile', { method: 'POST', body: payload });
}
