import { z } from 'zod';
import { CART } from '@/lib/shop-config';
import { FULFILMENT } from '@/lib/constants';

/** Ligne de panier telle qu'envoyée par le navigateur. */
export const cartLineSchema = z.object({
  productId: z.string().min(1),
  variantId: z.string().min(1).nullable(),
  quantity: z.number().int().min(1).max(CART.maxQuantityPerLine),
});

export const cartPayloadSchema = z.object({
  // 50 lignes distinctes : largement au-dessus d'un panier réaliste, mais
  // suffisant pour éviter qu'une requête forgée fasse travailler la base.
  lines: z.array(cartLineSchema).max(50),
});

/**
 * Coordonnées saisies dans le tunnel de commande.
 * Les messages sont rédigés pour être affichés tels quels au client.
 */
export const checkoutSchema = z
  .object({
    email: z.email({ message: 'Adresse électronique invalide.' }),
    customerName: z
      .string()
      .trim()
      .min(2, { message: 'Indiquez votre nom.' })
      .max(120),
    phone: z.string().trim().max(30).optional().or(z.literal('')),

    fulfilment: z.enum([FULFILMENT.DELIVERY, FULFILMENT.PICKUP], {
      message: 'Choisissez la livraison ou le retrait.',
    }),

    // Livraison
    shippingAddressLine1: z.string().trim().max(200).optional().or(z.literal('')),
    shippingAddressLine2: z.string().trim().max(200).optional().or(z.literal('')),
    shippingPostalCode: z.string().trim().max(10).optional().or(z.literal('')),
    shippingCity: z.string().trim().max(120).optional().or(z.literal('')),

    // Retrait
    pickupPointId: z.string().optional().or(z.literal('')),

    lines: z.array(cartLineSchema).min(1).max(50),
  })
  // Les champs d'adresse ne sont exigés que si la livraison est choisie.
  .refine(
    (data) =>
      data.fulfilment !== FULFILMENT.DELIVERY ||
      (data.shippingAddressLine1 ?? '').trim().length > 0,
    { message: 'Indiquez votre adresse.', path: ['shippingAddressLine1'] },
  )
  .refine(
    (data) =>
      data.fulfilment !== FULFILMENT.DELIVERY ||
      /^\d{5}$/.test((data.shippingPostalCode ?? '').trim()),
    { message: 'Code postal à 5 chiffres.', path: ['shippingPostalCode'] },
  )
  .refine(
    (data) =>
      data.fulfilment !== FULFILMENT.DELIVERY ||
      (data.shippingCity ?? '').trim().length > 0,
    { message: 'Indiquez votre ville.', path: ['shippingCity'] },
  )
  .refine(
    (data) =>
      data.fulfilment !== FULFILMENT.PICKUP ||
      (data.pickupPointId ?? '').trim().length > 0,
    { message: 'Choisissez un point de retrait.', path: ['pickupPointId'] },
  );

export type CheckoutInput = z.infer<typeof checkoutSchema>;
