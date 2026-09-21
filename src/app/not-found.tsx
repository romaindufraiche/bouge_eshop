import { ShopChrome } from '@/components/layout/ShopChrome';
import { ButtonLink } from '@/components/ui/Button';
import { Container } from '@/components/ui/Container';

/**
 * Page 404 globale, pour les URLs qui ne correspondent à aucune route.
 * Elle vit hors du groupe (boutique) et doit donc poser son habillage
 * elle-même.
 */
export default function NotFound() {
  return (
    <ShopChrome>
      <Container size="narrow">
        <div className="py-24 text-center">
          <h1 className="text-4xl sm:text-5xl">Page introuvable</h1>
          <p className="mt-4 text-ink-soft">
            Cette page n&apos;existe pas ou plus. Le produit a peut-être été
            retiré du catalogue.
          </p>
          <div className="mt-8 flex justify-center gap-3">
            <ButtonLink href="/boutique">Voir le catalogue</ButtonLink>
            <ButtonLink href="/" variant="secondary">
              Accueil
            </ButtonLink>
          </div>
        </div>
      </Container>
    </ShopChrome>
  );
}
