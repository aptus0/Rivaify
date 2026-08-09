export type OnboardingStatus =
  | 'account_created'
  | 'store_information'
  | 'business_information'
  | 'tax_information'
  | 'documents'
  | 'verification_pending'
  | 'approved'
  | 'completed';

export type StoreStatus = 'draft' | 'pending_approval' | 'active' | 'suspended' | 'closed';
export type StoreUserRole = 'owner' | 'admin' | 'manager' | 'staff' | 'support' | 'developer';
export type StorePermission =
  | 'products.view' | 'products.manage'
  | 'orders.view' | 'orders.manage' | 'orders.refund'
  | 'inventory.view' | 'inventory.manage'
  | 'customers.view' | 'customers.manage'
  | 'discounts.view' | 'discounts.manage'
  | 'analytics.view'
  | 'marketing.view' | 'marketing.manage'
  | 'settings.view' | 'settings.manage'
  | 'integrations.view'
  | 'themes.view' | 'themes.install' | 'themes.edit' | 'themes.publish' | 'themes.delete'
  | 'pages.view' | 'pages.create' | 'pages.update' | 'pages.delete'
  | 'navigation.manage'
  | 'checkout.customize';

export interface StoreCapabilities {
  create_product: boolean;
  create_order: boolean;
  create_customer: boolean;
  create_discount: boolean;
  adjust_inventory: boolean;
  manage_store: boolean;
  view_analytics: boolean;
  edit_theme?: boolean;
  publish_theme?: boolean;
}

export interface CurrentUser {
  id: string;
  name: string;
  email: string;
  email_verified: boolean;
  is_rivaify_admin: boolean;
}

export interface CurrentStoreSummary {
  id: string;
  name: string;
  slug: string;
  status: StoreStatus;
  default_currency: 'TRY' | 'USD' | 'EUR' | 'GBP';
  onboarding_status: OnboardingStatus;
  onboarding_step: number;
  role?: StoreUserRole;
  permissions?: StorePermission[];
  capabilities?: StoreCapabilities;
}

export interface MeResponse {
  data: {
    authenticated: boolean;
    user: CurrentUser | null;
    store: CurrentStoreSummary | null;
  };
}
