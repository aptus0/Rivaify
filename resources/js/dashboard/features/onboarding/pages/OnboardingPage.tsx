import { Navigate } from 'react-router-dom';
import { useAuth } from '../../../app/providers/AuthProvider';
import { ProgressBar } from '../components/ProgressBar';
import { CreateStoreStep } from '../components/CreateStoreStep';
import { BusinessInfoStep } from '../components/BusinessInfoStep';
import { TaxInfoStep } from '../components/TaxInfoStep';
import { DocumentsStep } from '../components/DocumentsStep';
import { PendingStep } from '../components/PendingStep';
import type { OnboardingStatus } from '../../../types';

const STEP_LABELS: Record<OnboardingStatus, string> = {
  account_created: 'Mağaza Oluştur',
  store_information: 'Mağaza Oluştur',
  business_information: 'İşletme Bilgileri',
  tax_information: 'Vergi Bilgileri',
  documents: 'Belgeler',
  verification_pending: 'İnceleniyor',
  approved: 'Onaylandı',
  completed: 'Tamamlandı',
};

export function OnboardingPage() {
  const { store, loading } = useAuth();

  if (loading) {
    return null;
  }

  if (store?.onboarding_status === 'completed') {
    return <Navigate to="/" replace />;
  }

  const status = store?.onboarding_status ?? 'account_created';

  return (
    <div className="mx-auto mt-12 w-full max-w-md">
      <ProgressBar step={store?.onboarding_step ?? 1} label={STEP_LABELS[status]} />
      {renderStep(status)}
    </div>
  );
}

function renderStep(status: OnboardingStatus) {
  switch (status) {
    case 'account_created':
    case 'store_information':
      return <CreateStoreStep />;
    case 'business_information':
      return <BusinessInfoStep />;
    case 'tax_information':
      return <TaxInfoStep />;
    case 'documents':
      return <DocumentsStep />;
    case 'verification_pending':
    case 'approved':
      return <PendingStep />;
    default:
      return null;
  }
}
