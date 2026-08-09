import { apiRequest } from '../../../lib/api';

export interface StoreSettings {
  store: {
    id: string;
    name: string;
    slug: string;
    status: 'draft' | 'pending_approval' | 'active' | 'suspended' | 'closed';
    onboarding_status: string;
    default_currency: 'TRY' | 'USD' | 'EUR' | 'GBP';
    default_locale: 'tr' | 'en';
    timezone: string;
    country_code: string;
  };
  domains: Array<{
    id: string;
    domain: string;
    is_primary: boolean;
    verified: boolean;
    verified_at: string | null;
    created_at: string | null;
  }>;
  payments: {
    default_provider: string;
    paytr: {
      configured: boolean;
      enabled: boolean;
      test_mode: boolean;
      installments_enabled: boolean;
      max_installment: number;
      callback_url: string;
    };
  };
  permissions: {
    can_manage: boolean;
  };
}

interface SettingsResponse {
  data: StoreSettings;
}

export type StoreProfilePayload = Pick<
  StoreSettings['store'],
  'name' | 'default_currency' | 'default_locale' | 'timezone' | 'country_code'
>;

export function getSettings(): Promise<SettingsResponse> {
  return apiRequest('/api/v1/settings');
}

export function updateStoreProfile(payload: StoreProfilePayload): Promise<SettingsResponse> {
  return apiRequest('/api/v1/settings/store', { method: 'PATCH', body: payload });
}

export function addStoreDomain(domain: string): Promise<{ data: StoreSettings['domains'][number] }> {
  return apiRequest('/api/v1/settings/domains', { method: 'POST', body: { domain } });
}

export function deleteStoreDomain(domainId: string): Promise<{ data: { deleted: boolean } }> {
  return apiRequest(`/api/v1/settings/domains/${domainId}`, { method: 'DELETE' });
}

export function verifyStoreDomain(domainId: string): Promise<{ data: StoreSettings['domains'][number] }> {
  return apiRequest(`/api/v1/settings/domains/${domainId}/verify`, { method: 'POST' });
}

export function makeStoreDomainPrimary(domainId: string): Promise<{ data: StoreSettings['domains'][number] }> {
  return apiRequest(`/api/v1/settings/domains/${domainId}/primary`, { method: 'POST' });
}

export interface AccountProfilePayload {
  name: string;
  email: string;
}

export interface AccountPasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export function updateAccountProfile(payload: AccountProfilePayload): Promise<unknown> {
  return apiRequest('/user/profile-information', { method: 'PUT', body: payload });
}

export function updateAccountPassword(payload: AccountPasswordPayload): Promise<unknown> {
  return apiRequest('/user/password', { method: 'PUT', body: payload });
}

export type FulfillmentStatus = 'active' | 'inactive';
export type ShippingMethodType = 'flat_rate' | 'free_shipping';

export interface ShippingZoneSettings {
  id: string;
  name: string;
  regions: Array<{
    country_code: string;
    province: string | null;
  }>;
}

export interface ShippingMethodSettings {
  id: string;
  name: string;
  type: ShippingMethodType;
  price: string;
  minimum_order: string | null;
  maximum_order: string | null;
  estimated_days_min: number | null;
  estimated_days_max: number | null;
  status: FulfillmentStatus;
  zone: ShippingZoneSettings | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ShippingMethodPayload {
  name: string;
  type: ShippingMethodType;
  price: string;
  minimum_order: string | null;
  maximum_order: string | null;
  estimated_days_min: number | null;
  estimated_days_max: number | null;
  status: FulfillmentStatus;
  shipping_zone_id: string | null;
}

export interface FulfillmentSummary {
  all: number;
  active: number;
  inactive: number;
}

export interface ShippingMethodsResponse {
  data: ShippingMethodSettings[];
  zones: ShippingZoneSettings[];
  summary: FulfillmentSummary;
}

export function getShippingMethods(): Promise<ShippingMethodsResponse> {
  return apiRequest('/api/v1/settings/shipping-methods');
}

export function createShippingMethod(payload: ShippingMethodPayload): Promise<{ data: ShippingMethodSettings }> {
  return apiRequest('/api/v1/settings/shipping-methods', { method: 'POST', body: payload });
}

export function updateShippingMethod(id: string, payload: ShippingMethodPayload): Promise<{ data: ShippingMethodSettings }> {
  return apiRequest(`/api/v1/settings/shipping-methods/${id}`, { method: 'PATCH', body: payload });
}

export function deleteShippingMethod(id: string): Promise<{ data: { deleted: boolean } }> {
  return apiRequest(`/api/v1/settings/shipping-methods/${id}`, { method: 'DELETE' });
}

export interface TaxRateSettings {
  id: string;
  name: string;
  country_code: string;
  rate: string;
  is_inclusive: boolean;
  status: FulfillmentStatus;
  applies_to_default_country: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface TaxRatePayload {
  name: string;
  country_code: string;
  rate: string;
  is_inclusive: boolean;
  status: FulfillmentStatus;
}

export interface TaxRatesResponse {
  data: TaxRateSettings[];
  default_country_code: string;
  summary: FulfillmentSummary;
}

export function getTaxRates(): Promise<TaxRatesResponse> {
  return apiRequest('/api/v1/settings/tax-rates');
}

export function createTaxRate(payload: TaxRatePayload): Promise<{ data: TaxRateSettings }> {
  return apiRequest('/api/v1/settings/tax-rates', { method: 'POST', body: payload });
}

export function updateTaxRate(id: string, payload: TaxRatePayload): Promise<{ data: TaxRateSettings }> {
  return apiRequest(`/api/v1/settings/tax-rates/${id}`, { method: 'PATCH', body: payload });
}

export function deleteTaxRate(id: string): Promise<{ data: { deleted: boolean } }> {
  return apiRequest(`/api/v1/settings/tax-rates/${id}`, { method: 'DELETE' });
}

export type IntegrationStatus = 'active' | 'needs_attention' | 'test_mode' | 'not_configured' | 'not_available';

export interface IntegrationItem {
  id: string;
  name: string;
  status: IntegrationStatus;
  available: boolean;
  description: string;
  detail: string | null;
  manage_path: string | null;
}

export function getIntegrations(): Promise<{ data: { channels: IntegrationItem[]; apps: IntegrationItem[] } }> {
  return apiRequest('/api/v1/integrations');
}
