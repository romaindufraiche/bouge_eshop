import 'server-only';
import { prisma } from '@/lib/prisma';
import { PRODUCT_STATUS } from '@/lib/constants';

/**
 * Requêtes partagées par les pages publiques.
 * Centraliser les `select` ici évite que chaque page récupère des colonnes
 * dont elle n'a pas besoin — et garantit qu'aucun brouillon ne fuite côté
 * public : le filtre `status: PUBLISHED` est appliqué une seule fois, ici.
 */

/** Champs strictement nécessaires à l'affichage d'une vignette produit. */
const productCardSelect = {
  id: true,
  name: true,
  slug: true,
  priceCents: true,
  salePriceCents: true,
  saleStartsAt: true,
  saleEndsAt: true,
  externalUrl: true,
  externalLabel: true,
  availableInStore: true,
  category: { select: { name: true, slug: true } },
  images: {
    select: { url: true, alt: true },
    orderBy: { position: 'asc' },
    take: 1,
  },
} as const;

export async function getCategories() {
  return prisma.category.findMany({
    orderBy: [{ position: 'asc' }, { name: 'asc' }],
    select: { id: true, name: true, slug: true, description: true },
  });
}

export async function getCategoryBySlug(slug: string) {
  return prisma.category.findUnique({
    where: { slug },
    select: {
      id: true,
      name: true,
      slug: true,
      description: true,
      metaTitle: true,
      metaDescription: true,
    },
  });
}

/** Produits en ligne, éventuellement filtrés sur une catégorie. */
export async function getPublishedProducts(options?: { categoryId?: string }) {
  return prisma.product.findMany({
    where: {
      status: PRODUCT_STATUS.PUBLISHED,
      ...(options?.categoryId ? { categoryId: options.categoryId } : {}),
    },
    orderBy: { createdAt: 'desc' },
    select: productCardSelect,
  });
}

/**
 * Sélection mise en avant sur la page d'accueil : les promotions d'abord,
 * complétées par les nouveautés.
 */
export async function getFeaturedProducts(limit = 8) {
  const onSale = await prisma.product.findMany({
    where: {
      status: PRODUCT_STATUS.PUBLISHED,
      salePriceCents: { not: null },
    },
    orderBy: { updatedAt: 'desc' },
    take: limit,
    select: productCardSelect,
  });

  if (onSale.length >= limit) return onSale;

  const fillers = await prisma.product.findMany({
    where: {
      status: PRODUCT_STATUS.PUBLISHED,
      id: { notIn: onSale.map((product) => product.id) },
    },
    orderBy: { createdAt: 'desc' },
    take: limit - onSale.length,
    select: productCardSelect,
  });

  return [...onSale, ...fillers];
}

/** Fiche produit complète. Renvoie null si le produit n'est pas publié. */
export async function getPublishedProductBySlug(slug: string) {
  return prisma.product.findFirst({
    where: { slug, status: PRODUCT_STATUS.PUBLISHED },
    include: {
      category: { select: { id: true, name: true, slug: true } },
      images: { orderBy: { position: 'asc' } },
      variants: { orderBy: [{ position: 'asc' }, { createdAt: 'asc' }] },
    },
  });
}

/** Autres produits de la même catégorie, pour le bloc « À voir aussi ». */
export async function getRelatedProducts(
  categoryId: string,
  excludeProductId: string,
  limit = 4,
) {
  return prisma.product.findMany({
    where: {
      status: PRODUCT_STATUS.PUBLISHED,
      categoryId,
      id: { not: excludeProductId },
    },
    orderBy: { createdAt: 'desc' },
    take: limit,
    select: productCardSelect,
  });
}

/**
 * Produit mis en avant en haut de la page d'accueil.
 * Si plusieurs sont cochés dans l'admin, seul le plus récent est retenu :
 * une mise en avant qui en affiche trois n'en est plus une.
 */
export async function getFeaturedProduct() {
  return prisma.product.findFirst({
    where: { status: PRODUCT_STATUS.PUBLISHED, featured: true },
    orderBy: { updatedAt: 'desc' },
    select: {
      ...productCardSelect,
      description: true,
      stock: true,
      images: {
        select: { url: true, alt: true },
        orderBy: { position: 'asc' },
        take: 1,
      },
    },
  });
}

export async function getActivePickupPoints() {
  return prisma.pickupPoint.findMany({
    where: { isActive: true },
    orderBy: [{ position: 'asc' }, { name: 'asc' }],
  });
}
