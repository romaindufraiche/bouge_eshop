'use server';

import { redirect } from 'next/navigation';
import { requireAdmin } from '@/lib/require-admin';
import { prisma } from '@/lib/prisma';
import { productSchema } from '@/lib/admin-validation';
import { revalidateShop } from '@/lib/revalidate';
import { slugify, uniqueSlug } from '@/lib/slug';
import { deleteStoredImage } from '@/lib/storage';

export type ProductFormState = {
  error?: string;
  fieldErrors?: Record<string, string>;
  /**
   * Valeurs telles qu'elles ont été saisies, renvoyées au formulaire.
   * React réinitialise les champs non contrôlés à la fin d'une action : sans
   * cela, la moindre erreur de validation ferait tout retaper.
   */
  values?: SubmittedProductValues;
};

/** Reflet brut du formulaire, avant toute conversion. */
export type SubmittedProductValues = {
  name: string;
  slug: string;
  description: string;
  categoryId: string;
  price: string;
  salePrice: string;
  saleStartsAt: string;
  saleEndsAt: string;
  status: string;
  stock: string;
  metaTitle: string;
  metaDescription: string;
  featured: string;
  availableInStore: string;
  externalUrl: string;
  externalLabel: string;
  variants: {
    id: string;
    size: string;
    color: string;
    stock: string;
    price: string;
  }[];
};

/** Relit le formulaire tel quel, pour pouvoir le réafficher à l'identique. */
function readSubmittedValues(
  formData: FormData,
  variants: unknown,
): SubmittedProductValues {
  const text = (field: string) => String(formData.get(field) ?? '');

  return {
    name: text('name'),
    slug: text('slug'),
    description: text('description'),
    categoryId: text('categoryId'),
    price: text('price'),
    salePrice: text('salePrice'),
    saleStartsAt: text('saleStartsAt'),
    saleEndsAt: text('saleEndsAt'),
    status: text('status'),
    stock: text('stock'),
    metaTitle: text('metaTitle'),
    metaDescription: text('metaDescription'),
    featured: text('featured'),
    availableInStore: text('availableInStore'),
    externalUrl: text('externalUrl'),
    externalLabel: text('externalLabel'),
    variants: Array.isArray(variants)
      ? variants.map((entry) => {
          const variant = (entry ?? {}) as Record<string, unknown>;
          return {
            id: String(variant.id ?? ''),
            size: String(variant.size ?? ''),
            color: String(variant.color ?? ''),
            stock: String(variant.stock ?? '0'),
            price: String(variant.price ?? ''),
          };
        })
      : [],
  };
}

/**
 * Enregistre un produit : création si `id` est vide, mise à jour sinon.
 *
 * Les déclinaisons arrivent sous forme de JSON dans un champ caché, alimenté
 * par l'éditeur de déclinaisons. Celles qui ont disparu du formulaire sont
 * supprimées, les nouvelles créées, les autres mises à jour.
 */
export async function saveProduct(
  _previousState: ProductFormState,
  formData: FormData,
): Promise<ProductFormState> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '').trim();

  let variants: unknown;
  try {
    variants = JSON.parse(String(formData.get('variants') ?? '[]'));
  } catch {
    return { error: 'Les déclinaisons sont illisibles. Rechargez la page.' };
  }

  const submitted = readSubmittedValues(formData, variants);

  // On valide les valeurs déjà normalisées en chaînes : `formData.get()`
  // renvoie `null` pour un champ absent, ce qu'un schéma attendant une chaîne
  // rejette avec un message impossible à relier à un champ visible.
  const parsed = productSchema.safeParse({ ...submitted, variants });

  if (!parsed.success) {
    const fieldErrors: Record<string, string> = {};
    for (const issue of parsed.error.issues) {
      const field = String(issue.path[0] ?? 'global');
      fieldErrors[field] ??= issue.message;
    }
    return {
      error: 'Certains champs doivent être corrigés.',
      fieldErrors,
      values: submitted,
    };
  }

  const input = parsed.data;

  // La catégorie doit exister : un identifiant forgé ne doit pas créer de
  // produit orphelin.
  const category = await prisma.category.findUnique({
    where: { id: input.categoryId },
    select: { id: true, slug: true },
  });
  if (!category) {
    return {
      error: 'Cette catégorie n’existe plus.',
      fieldErrors: { categoryId: 'Choisissez une autre catégorie.' },
    };
  }

  // Slug : celui saisi s'il l'a été, sinon dérivé du nom. Rendu unique dans
  // tous les cas, en ignorant le produit lui-même lors d'une mise à jour.
  const requestedSlug = input.slug.trim() || input.name;
  const slug = await uniqueSlug(slugify(requestedSlug), async (candidate) => {
    const existing = await prisma.product.findUnique({
      where: { slug: candidate },
      select: { id: true },
    });
    return existing !== null && existing.id !== id;
  });

  const data = {
    name: input.name,
    slug,
    description: input.description,
    categoryId: category.id,
    priceCents: input.price,
    salePriceCents: input.salePrice,
    saleStartsAt: input.saleStartsAt,
    saleEndsAt: input.saleEndsAt,
    status: input.status,
    stock: input.stock,
    metaTitle: input.metaTitle || null,
    metaDescription: input.metaDescription || null,
    featured: input.featured !== '',
    availableInStore: input.availableInStore !== '',
    externalUrl: input.externalUrl || null,
    externalLabel: input.externalLabel || null,
  };

  let productId = id;
  let previousSlug: string | null = null;
  let previousCategorySlug: string | null = null;

  if (id) {
    const existing = await prisma.product.findUnique({
      where: { id },
      select: { slug: true, category: { select: { slug: true } } },
    });
    if (!existing) return { error: 'Ce produit n’existe plus.' };

    previousSlug = existing.slug;
    previousCategorySlug = existing.category.slug;

    await prisma.product.update({ where: { id }, data });
  } else {
    const created = await prisma.product.create({ data });
    productId = created.id;
  }

  // --- Déclinaisons ---------------------------------------------------------
  const submittedIds = input.variants
    .map((variant) => variant.id)
    .filter((variantId): variantId is string => Boolean(variantId));

  // Retirées du formulaire : on les supprime.
  await prisma.productVariant.deleteMany({
    where: { productId, id: { notIn: submittedIds.length ? submittedIds : ['-'] } },
  });

  for (const [index, variant] of input.variants.entries()) {
    const variantPriceCents = variant.price.trim()
      ? Math.round(Number(variant.price.replace(',', '.')) * 100)
      : null;

    const variantData = {
      productId,
      size: variant.size.trim() || null,
      color: variant.color.trim() || null,
      stock: variant.stock,
      priceCents:
        variantPriceCents !== null && Number.isFinite(variantPriceCents)
          ? variantPriceCents
          : null,
      position: index,
    };

    if (variant.id) {
      await prisma.productVariant.update({
        where: { id: variant.id },
        data: variantData,
      });
    } else {
      await prisma.productVariant.create({ data: variantData });
    }
  }

  revalidateShop({
    productSlugs: [slug, previousSlug],
    categorySlugs: [category.slug, previousCategorySlug],
  });

  redirect(`/admin/produits/${productId}?enregistre=1`);
}

/** Supprime définitivement un produit, ses photos et ses déclinaisons. */
export async function deleteProduct(formData: FormData): Promise<void> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '');
  if (!id) return;

  const product = await prisma.product.findUnique({
    where: { id },
    select: {
      slug: true,
      category: { select: { slug: true } },
      images: { select: { url: true } },
    },
  });
  if (!product) redirect('/admin/produits');

  // Les fichiers image sont retirés du stockage avant la suppression en base :
  // en cas d'échec ici, on ne laisse pas de lignes pointant vers du vide.
  for (const image of product.images) {
    await deleteStoredImage(image.url);
  }

  // Les déclinaisons et les photos partent en cascade (voir le schéma) ; les
  // lignes de commande, elles, conservent leurs libellés recopiés.
  await prisma.product.delete({ where: { id } });

  revalidateShop({
    productSlugs: [product.slug],
    categorySlugs: [product.category.slug],
  });

  redirect('/admin/produits?supprime=1');
}
