import Image from 'next/image';
import Link from 'next/link';

export const revalidate = 300;
import { ProductCard } from '@/components/shop/ProductCard';
import { ButtonLink } from '@/components/ui/Button';
import { Container } from '@/components/ui/Container';
import { formatPrice } from '@/lib/money';
import { getCategories, getFeaturedProducts } from '@/lib/queries';
import { SHIPPING, SHOP } from '@/lib/shop-config';

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
          <div className="grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-2 lg:gap-16">
            <div>
              <p className="font-note text-2xl text-accent-deep">
                {SHOP.baseline}
              </p>
              <h1 className="mt-3 text-4xl sm:text-6xl lg:text-7xl">
                Le matériel qui suit,
                <br />
                même le lundi.
              </h1>
              <p className="mt-6 max-w-md text-base text-ink-soft sm:text-lg">
                Bonnets, lunettes, accessoires et textile. Choisis pour tenir la
                distance, pas pour faire joli au fond du sac.
              </p>
              <div className="mt-8 flex flex-wrap gap-3">
                <ButtonLink href="/boutique" variant="accent" size="lg">
                  Voir le catalogue
                </ButtonLink>
                <ButtonLink href="/livraison" variant="secondary" size="lg">
                  Livraison et retrait
                </ButtonLink>
              </div>
            </div>

            {/* La mascotte de la marque : la grenouille, toujours en
                mouvement, jamais pressée (charte, page 27). */}
            <div className="relative flex aspect-4/3 items-center justify-center overflow-hidden rounded-surface bg-sand lg:aspect-square">
              <Image
                src="/brand/mascotte-course.png"
                alt="La mascotte de BOUGE., une grenouille en mouvement, serviette sur l'épaule"
                width={720}
                height={720}
                priority
                sizes="(max-width: 1024px) 100vw, 50vw"
                className="h-4/5 w-auto object-contain"
              />
            </div>
          </div>
        </Container>
      </section>

      {/* --- Repères pratiques, sans superlatif ---------------------------- */}
      <section className="border-b border-line">
        <Container size="wide">
          <ul className="grid gap-6 py-10 text-sm sm:grid-cols-3">
            <li>
              <h3 className="text-sm">Livraison en France</h3>
              <p className="mt-1 text-ink-soft">
                {formatPrice(SHIPPING.flatRateCents)}
                {SHIPPING.freeAboveCents !== null &&
                  `, offerte dès ${formatPrice(SHIPPING.freeAboveCents)}`}
                .
              </p>
            </li>
            <li>
              <h3 className="text-sm">Retrait sur place</h3>
              <p className="mt-1 text-ink-soft">
                Sans frais. On vous écrit dès que c&apos;est prêt.
              </p>
            </li>
            <li>
              <h3 className="text-sm">Paiement sécurisé</h3>
              <p className="mt-1 text-ink-soft">
                Carte bancaire via Stripe. Aucune donnée de paiement ne passe par
                nos serveurs.
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
                  <div className="relative aspect-square overflow-hidden rounded-surface bg-sand">
                    <Image
                      src={
                        CATEGORY_IMAGES[category.slug] ??
                        '/images/demo/accessoires.svg'
                      }
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

      {/* --- Signature ------------------------------------------------------ */}
      <section className="border-t border-line py-14">
        <Container size="wide">
          <div className="flex flex-col items-center gap-4 text-center">
            <Image
              src="/brand/tampon-anthracite.png"
              alt=""
              width={560}
              height={560}
              className="h-24 w-24"
            />
            <p className="max-w-md text-sm text-ink-soft">
              Une question sur une taille, un modèle, un délai&nbsp;? Écrivez-nous
              à{' '}
              <a
                href={`mailto:${SHOP.email}`}
                className="text-ink underline underline-offset-4"
              >
                {SHOP.email}
              </a>
              .
            </p>
          </div>
        </Container>
      </section>
    </>
  );
}
