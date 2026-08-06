import { ArrowRight } from 'lucide-react';
import { RivaCard } from '../../effects/RivaCard';
import { Reveal } from '../../effects/Reveal';
import { Button } from '../../ui/Button';
import { Container } from '../../ui/Container';
import { LOGIN_URL, REGISTER_URL } from '../../../constants/site';

export function FinalCTA() {
  return (
    <section className="relative overflow-hidden bg-dark px-6 py-24 lg:px-8 lg:py-32">
      <Container size="narrow" className="relative text-center">
        <Reveal>
          <RivaCard variant="spectrum" intensity="medium" ambient interactive={false} className="mx-auto inline-block rounded-2xl">
            <div className="rounded-2xl px-8 py-10 sm:px-16 sm:py-14">
              <h2 className="text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-4xl lg:text-5xl">
                Yeni nesil mağazanı
                <br />
                Rivaify ile kur.
              </h2>

              <div className="mt-9 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <Button href={REGISTER_URL} variant="primary" size="lg" icon={ArrowRight} fullWidthOnMobile>
                  Mağazanı Oluştur
                </Button>
                <Button href={LOGIN_URL} variant="outlineOnDark" size="lg" fullWidthOnMobile>
                  Giriş Yap
                </Button>
              </div>
            </div>
          </RivaCard>
        </Reveal>
      </Container>
    </section>
  );
}
