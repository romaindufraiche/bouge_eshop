import Image from 'next/image';
import Link from 'next/link';
import { ProductCard } from '@/components/shop/ProductCard';
import { ButtonLink } from '@/components/ui/Button';
import { Container } from '@/components/ui/Container';
import { formatPrice } from '@/lib/money';
import { getCategories, getFeaturedProducts } from '@/lib/queries';
import { SHIPPING } from '@/lib/shop-config';

// Les pages publiques sont régénérées au maximum toutes les 5 minutes :
// un prix ou une promotion modifiés apparaissent sans redéploiement.
export const revalidate = 300;

// Visuels de catégorie provisoires, en attente des photos de la marque.
const CATEGORY_IMAGES: Record<string, string> = {
  bonnets: '/images/demo/bonnets.svg',
  lunettes: '/images/demo/lunettes.svg',
  accessoires: '/images/demo/accessoires.svg',
  vetements: '/images/demo/vetements.svg',
};

export default async function HomePage() {
  const [categories, featured] = await Promise.all([
    getCategories(),
    getFeaturedProducts(8),
  ]);

  return (
    <>
      {/* --- Bandeau d'ouverture ------------------------------------------ */}
      <section className="border-b border-line">
        <Container size="wide">
          <div className="grid items-center gap-10 py-16 sm:py-24 lg:grid-cols-2 lg:gap-16">
            <div>
              <h1 className="text-4xl leading-[1.05] sm:text-6xl lg:text-7xl">
                Le matériel qui suit
                <br />
                votre entraînement.
              </h1>
              <p className="mt-6 max-w-md text-base text-ink-soft sm:text-lg">
                Bonnets, lunettes, accessoires et textile. Sélectionnés pour
                durer plus d&apos;une saison, pas pour faire joli en vitrine.
              </p>
              <div className="mt-8 flex flex-wrap gap-3">
                <ButtonLink href="/boutique" size="lg">
                  Voir le catalogue
                </ButtonLink>
                <ButtonLink href="/livraison" variant="secondary" size="lg">
                  Livraison et retrait
                </ButtonLink>
              </div>
            </div>

            <div className="relative aspect-4/3 bg-sand lg:aspect-square">
              <Image
                src="/images/demo/lunettes.svg"
                alt="Lunettes de natation de la collection BOUGE."
                fill
                priority
                sizes="(max-width: 1024px) 100vw, 50vw"
                className="object-cover"
              />
            </div>
          </div>
        </Container>
      </section>

      {/* --- Arguments courts, sans superlatif ----------------------------- */}
      <section className="border-b border-line">
        <Container size="wide">
          <ul className="grid gap-6 py-10 text-sm sm:grid-cols-3">
            <li>
              <h2 className="font-sans text-sm font-medium">
                Livraison en France
              </h2>
              <p className="mt-1 text-ink-soft">
                {formatPrice(SHIPPING.flatRateCents)}
                {SHIPPING.freeAboveCents !== null &&
                  `, offerte dès ${formatPrice(SHIPPING.freeAboveCents)}`}
                .
              </p>
            </li>
            <li>
              <h2 className="font-sans text-sm font-medium">Retrait sur place</h2>
              <p className="mt-1 text-ink-soft">
                Sans frais, dès que la commande est prête.
              </p>
            </li>
            <li>
              <h2 className="font-sans text-sm font-medium">Paiement sécurisé</h2>
              <p className="mt-1 text-ink-soft">
                Carte bancaire via Stripe. Aucune donnée de paiement ne transite
                par nos serveurs.
              </p>
            </li>
          </ul>
        </Container>
      </section>

      {/* --- Catégories ---------------------------------------------------- */}
      <section className="py-16 sm:py-20">
        <Container size="wide">
          <h2 className="text-3xl sm:text-4xl">Par catégorie</h2>

          <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {categories.map((category) => (
              <li key={category.id}>
                <Link href={`/boutique/${category.slug}`} className="group block">
                  <div className="relative aspect-square overflow-hidden bg-sand">
                    <Image
                      src={CATEGORY_IMAGES[category.slug] ?? '/images/demo/accessoires.svg'}
                      alt={`Catégorie ${category.name}`}
                      fill
                      sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                      className="object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                    />
                  </div>
                  <h3 className="mt-3 text-lg group-hover:underline group-hover:underline-offset-4">
                    {category.name}
                  </h3>
                  {category.description && (
                    <p className="mt-1 text-sm text-ink-soft">
                      {category.description}
                    </p>
                  )}
                </Link>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      {/* --- Produits mis en avant ----------------------------------------- */}
      {featured.length > 0 && (
        <section className="border-t border-line py-16 sm:py-20">
          <Container size="wide">
            <div className="flex flex-wrap items-baseline justify-between gap-4">
              <h2 className="text-3xl sm:text-4xl">À découvrir</h2>
              <Link
                href="/boutique"
                className="text-sm hover:underline hover:underline-offset-4"
              >
                Tout le catalogue
              </Link>
            </div>

            <ul className="mt-8 grid grid-cols-2 gap-x-5 gap-y-10 lg:grid-cols-4">
              {featured.map((product, index) => (
                <li key={product.id}>
                  <ProductCard product={product} priority={index < 4} />
                </li>
              ))}
            </ul>
          </Container>
        </section>
      )}
    </>
  );
}
