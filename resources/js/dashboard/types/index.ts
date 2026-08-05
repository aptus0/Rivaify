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

export interface CurrentUser {
  id: string;
  name: string;
  email: string;
  email_verified: boolean;
}

export interface CurrentStoreSummary {
  id: string;
  name: string;
  status: StoreStatus;
  onboarding_status: OnboardingStatus;
  onboarding_step: number;
}

export interface MeResponse {
  data: {
    user: CurrentUser;
    store: CurrentStoreSummary | null;
  };
}
