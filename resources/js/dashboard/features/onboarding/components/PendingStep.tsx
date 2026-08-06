import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { useAuth } from '../../../app/providers/AuthProvider';

export function PendingStep() {
  const { refresh } = useAuth();

  return (
    <Card className="text-center">
      <h2 className="mb-2 text-lg font-semibold text-dark">Başvurun İnceleniyor</h2>
      <p className="mb-6 text-sm text-muted">
        Doğrulama başvurun Rivaify ekibine ulaştı. İnceleme tamamlandığında sana e-posta ile haber
        vereceğiz.
      </p>
      <Button onClick={() => void refresh()}>Durumu Kontrol Et</Button>
    </Card>
  );
}
