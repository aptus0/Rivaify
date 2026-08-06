import { KeyRound, Lock, ShieldCheck, UserCheck, type LucideIcon } from 'lucide-react';
import { Reveal } from '../../effects/Reveal';
import { Container } from '../../ui/Container';

const POINTS: { icon: LucideIcon; title: string; description: string }[] = [
  { icon: Lock, title: 'Güvenli checkout mimarisi', description: 'Ödeme akışı uçtan uca güvenli bağlantılarla çalışır.' },
  { icon: ShieldCheck, title: 'İzole mağaza verisi', description: 'Her mağazanın verisi diğerlerinden ayrıştırılmış olarak tutulur.' },
  { icon: KeyRound, title: 'Şifreli bağlantılar', description: 'Tüm veri trafiği şifreli kanallar üzerinden iletilir.' },
  { icon: UserCheck, title: 'Korunan hesaplar', description: 'Kimlik doğrulama ve erişim kontrolü ile korunan merchant hesapları.' },
];

export function Trust() {
  return (
    <section className="border-t border-dark/[0.06] px-6 py-20 lg:px-8 lg:py-24">
      <Container>
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {POINTS.map((point, index) => (
            <Reveal key={point.title} delay={index * 0.06}>
              <div className="flex flex-col items-center text-center sm:items-start sm:text-left">
                <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-dark/[0.04] text-dark/60">
                  <point.icon className="h-5 w-5" strokeWidth={2} />
                </span>
                <p className="mt-4 text-sm font-bold text-dark">{point.title}</p>
                <p className="mt-1.5 text-xs leading-relaxed text-dark/45">{point.description}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </Container>
    </section>
  );
}
