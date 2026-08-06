import { BrandMarquee } from '../../../brands/BrandMarquee';
import { Container } from '../../../ui/Container';
import { SectionHeading } from '../../../ui/SectionHeading';
import { INTEGRATIONS } from '../../../../data/integrations';

export function HomeBrandEcosystem() {
  return (
    <section className="border-y border-dark/[0.06] bg-surface py-16">
      <Container>
        <SectionHeading title="İşletmenin kullandığı servisleri tek merkeze bağla." />
      </Container>

      <div className="mt-10">
        <BrandMarquee integrations={INTEGRATIONS} />
      </div>
    </section>
  );
}
