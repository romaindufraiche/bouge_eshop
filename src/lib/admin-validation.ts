import { z } from 'zod';
import { PRODUCT_STATUS } from '@/lib/constants';

/**
 * Validation des formulaires de l'administration.
 * Les messages sont rédigés pour être lus par la personne qui saisit, sans
 * vocabulaire technique.
 */

/** Champ euros : accepte « 14,90 » comme « 14.90 ». */
const euroAmount = z
  .string()
  .trim()
  .refine((value) => /^\d+([.,]\d{1,2})?$/.test(value), {
    message: 'Montant invalide. Exemple : 14,90',
  })
  .transform((value) => Math.round(Number(value.replace(',', '.')) * 100));

const optionalEuroAmount = z
  .union([z.literal(''), euroAmount])
  .transform((value) => (value === '' ? null : value));

/** Champ date : vide ou date au format aaaa-mm-jj. */
const optionalDate = z
  .string()
  .trim()
  .transform((value) => (value === '' ? null : value))
  .refine((value) => value === null || !Number.isNaN(Date.parse(value)), {
    message: 'Date invalide.',
  })
  .transform((value) => (value === null ? null : new Date(value)));

export const variantSchema = z.object({
  /** Vide pour une variante qui vient d'être ajoutée dans le formulaire. */
  id: z.string().optional(),
  size: z.string().trim().max(40),
  color: z.string().trim().max(40),
  stock: z.coerce.number().int().min(0).max(100000),
  /** Prix spécifique en euros, vide pour reprendre celui du produit. */
  price: z.string().trim(),
});

export const productSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(2, { message: 'Donnez un nom au produit.' })
      .max(160),
    slug: z.string().trim().max(80),
    description: z
      .string()
      .trim()
      .min(1, { message: 'Décrivez le produit en quelques lignes.' })
      .max(5000),
    categoryId: z.string().min(1, { message: 'Choisissez une catégorie.' }),

    price: euroAmount,
    salePrice: optionalEuroAmount,
    saleStartsAt: optionalDate,
    saleEndsAt: optionalDate,

    status: z.enum([PRODUCT_STATUS.DRAFT, PRODUCT_STATUS.PUBLISHED]),
    stock: z.coerce.number().int().min(0).max(100000),

    metaTitle: z.string().trim().max(70),
    metaDescription: z.string().trim().max(180),

    /* Cases à cocher : un navigateur n'envoie rien quand elles sont
       décochées, d'où une chaîne vide plutôt qu'un booléen. */
    featured: z.string().max(10),
    availableInStore: z.string().max(10),

    /** Vide = vendu sur cette boutique. */
    externalUrl: z
      .string()
      .trim()
      .max(500)
      .refine(
        (value) =>
          value === '' || /^https?:\/\/.+\..+/.test(value),
        { message: 'Lien invalide. Il doit commencer par https:// ' },
      ),
    externalLabel: z.string().trim().max(60),

    variants: z.array(variantSchema).max(60),
  })
  .refine(
    (data) => data.salePrice === null || data.salePrice < data.price,
    {
      message: 'Le prix promotionnel doit être inférieur au prix normal.',
      path: ['salePrice'],
    },
  )
  .refine(
    (data) =>
      data.saleStartsAt === null ||
      data.saleEndsAt === null ||
      data.saleStartsAt <= data.saleEndsAt,
    {
      message: 'La date de fin doit venir après la date de début.',
      path: ['saleEndsAt'],
    },
  )
  .refine(
    (data) => {
      // Deux variantes ne peuvent pas porter la même combinaison taille/couleur.
      const keys = data.variants.map(
        (variant) => `${variant.size.toLowerCase()}|${variant.color.toLowerCase()}`,
      );
      return new Set(keys).size === keys.length;
    },
    {
      message:
        'Deux déclinaisons ont la même taille et la même couleur. Chaque combinaison doit être unique.',
      path: ['variants'],
    },
  )
  .refine(
    (data) =>
      data.variants.every(
        (variant) => variant.size.trim() !== '' || variant.color.trim() !== '',
      ),
    {
      message: 'Chaque déclinaison doit avoir au moins une taille ou une couleur.',
      path: ['variants'],
    },
  )
  .refine(
    // Un produit vendu ailleurs ne passe pas par le panier : ses déclinaisons
    // ne seraient jamais utilisées et laisseraient croire à une vente en ligne.
    (data) => data.externalUrl === '' || data.variants.length === 0,
    {
      message:
        'Un produit vendu par un revendeur ne peut pas avoir de déclinaisons : retirez-les, ou videz le lien du revendeur.',
      path: ['externalUrl'],
    },
  );

export const categorySchema = z.object({
  name: z
    .string()
    .trim()
    .min(2, { message: 'Donnez un nom à la catégorie.' })
    .max(80),
  description: z.string().trim().max(300),
});
