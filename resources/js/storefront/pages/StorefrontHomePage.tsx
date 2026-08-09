import { useStorefront } from '../context';
import { StorefrontRenderer } from '../renderer/StorefrontRenderer';

export function StorefrontHomePage() {
  const { runtime } = useStorefront();
  const document = {
    ...runtime.document,
    sections: runtime.document.sections.filter((section) => !['announcement', 'header', 'footer'].includes(section.type)),
  };

  return <StorefrontRenderer store={runtime.store} document={document} products={runtime.products} mode="published" />;
}
