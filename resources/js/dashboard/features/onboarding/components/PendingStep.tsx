import { Button } from '../../../components/ui/Button';
import { useAuth } from '../../../app/providers/AuthProvider';

export function PendingStep() {
  const { refresh } = useAuth();

  return (
    <div className="text-center">
      <h2 className="mb-2 text-lg font-medium">Başvurun İnceleniyor</h2>
      <p className="mb-6 text-sm text-neutral-600">
        Doğrulama başvurun Rivaify ekibine ulaştı. İnceleme tamamlandığında sana e-posta ile haber
        vereceğiz.
      </p>
      <Button onClick={() => void refresh()}>Durumu Kontrol Et</Button>
    </div>
  );
}
