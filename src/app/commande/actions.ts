'use server';

import { computeTotals, resolveCart } from '@/lib/cart-server';
import { FULFILMENT, ORDER_STATUS } from '@/lib/constants';
import { generateOrderReference } from '@/lib/order-reference';
import { prisma } from '@/lib/prisma';
import { SITE_URL } from '@/lib/seo';
import { getStripe } from '@/lib/stripe';
import { checkoutSchema } from '@/lib/validation';

export type CheckoutState = {
  /** Message général affiché en haut du formulaire. */
  error?: string;
  /** Messages par champ, affichés sous le champ concerné. */
  fieldErrors?: Record<string, string>;
  /** URL de paiement Stripe vers laquelle rediriger le navigateur. */
  redirectUrl?: string;
};

/**
 * Crée la commande puis la session de paiement Stripe.
 *
 * Rien de ce qui touche à l'argent ne vient du formulaire : les quantités sont
 * revérifiées contre le stock et les prix relus en base. Le client n'envoie
 * que des identifiants.
 */
export async function createCheckoutSession(
  _previousState: CheckoutState,
  formData: FormData,
): Promise<CheckoutState> {
  // --- 1. Validation des coordonnées ---------------------------------------
  let lines: unknown;
  try {
    lines = JSON.parse(String(formData.get('lines') ?? '[]'));
  } catch {
    return { error: 'Panier illisible. Rechargez la page et réessayez.' };
  }

  const parsed = checkoutSchema.safeParse({
    email: formData.get('email'),
    customerName: formData.get('customerName'),
    phone: formData.get('phone'),
    fulfilment: formData.get('fulfilment'),
    shippingAddressLine1: formData.get('shippingAddressLine1'),
    shippingAddressLine2: formData.get('shippingAddressLine2'),
    shippingPostalCode: formData.get('shippingPostalCode'),
    shippingCity: formData.get('shippingCity'),
    pickupPointId: formData.get('pickupPointId'),
    lines,
  });

  if (!parsed.success) {
    const fieldErrors: Record<string, string> = {};
    for (const issue of parsed.error.issues) {
      const field = String(issue.path[0] ?? 'global');
      fieldErrors[field] ??= issue.message;
    }

    return {
      error: 'Certains champs doivent être corrigés.',
      fieldErrors,
    };
  }

  const input = parsed.data;

  // --- 2. Relecture du panier en base --------------------------------------
  const cart = await resolveCart(input.lines);

  if (cart.isEmpty) {
    return { error: 'Votre panier est vide.' };
  }

  if (cart.issues.length > 0) {
    return {
      error:
        'Votre panier a changé depuis votre dernière visite (stock ou disponibilité). Vérifiez le récapitulatif ci-dessous, puis relancez le paiement.',
    };
  }

  // --- 3. Vérification du point de retrait ---------------------------------
  let pickupPointId: string | null = null;
  if (input.fulfilment === FULFILMENT.PICKUP) {
    const point = await prisma.pickupPoint.findFirst({
      where: { id: input.pickupPointId || '', isActive: true },
      select: { id: true },
    });

    if (!point) {
      return {
        error: 'Ce point de retrait n’est plus disponible.',
        fieldErrors: { pickupPointId: 'Choisissez un autre point de retrait.' },
      };
    }
    pickupPointId = point.id;
  }

  const totals = computeTotals(cart.subtotalCents, input.fulfilment);

  // --- 4. Création de la commande (statut : en attente de paiement) ---------
  const order = await prisma.order.create({
    data: {
      reference: generateOrderReference(),
      email: input.email.trim().toLowerCase(),
      customerName: input.customerName.trim(),
      phone: input.phone?.trim() || null,
      status: ORDER_STATUS.PENDING,
      fulfilment: input.fulfilment,

      shippingAddressLine1:
        input.fulfilment === FULFILMENT.DELIVERY
          ? input.shippingAddressLine1!.trim()
          : null,
      shippingAddressLine2:
        input.fulfilment === FULFILMENT.DELIVERY
          ? input.shippingAddressLine2?.trim() || null
          : null,
      shippingPostalCode:
        input.fulfilment === FULFILMENT.DELIVERY
          ? input.shippingPostalCode!.trim()
          : null,
      shippingCity:
        input.fulfilment === FULFILMENT.DELIVERY ? input.shippingCity!.trim() : null,
      shippingCountry: input.fulfilment === FULFILMENT.DELIVERY ? 'FR' : null,

      pickupPointId,

      subtotalCents: totals.subtotalCents,
      shippingCents: totals.shippingCents,
      totalCents: totals.totalCents,

      // Les libellés sont recopiés : la commande reste lisible même si le
      // produit est renommé ou supprimé par la suite.
      items: {
        create: cart.lines.map((line) => ({
          productId: line.productId,
          variantId: line.variantId,
          productName: line.name,
          variantLabel: line.variantLabel,
          imageUrl: line.imageUrl,
          unitPriceCents: line.unitPriceCents,
          quantity: line.quantity,
          lineTotalCents: line.lineTotalCents,
        })),
      },
    },
  });

  // --- 5. Session de paiement Stripe ---------------------------------------
  try {
    const stripe = getStripe();

    // Stripe télécharge les images depuis Internet : inutile de lui passer des
    // URLs locales, qu'il ne pourra pas atteindre.
    const canUseImages = SITE_URL.startsWith('https://');

    const session = await stripe.checkout.sessions.create({
      mode: 'payment',
      locale: 'fr',
      customer_email: order.email,
      client_reference_id: order.reference,
      metadata: { orderId: order.id, reference: order.reference },

      line_items: cart.lines.map((line) => ({
        quantity: line.quantity,
        price_data: {
          currency: 'eur',
          unit_amount: line.unitPriceCents,
          product_data: {
            name: line.variantLabel
              ? `${line.name} — ${line.variantLabel}`
              : line.name,
            images:
              canUseImages && line.imageUrl
                ? [`${SITE_URL}${line.imageUrl}`]
                : undefined,
          },
        },
      })),

      // Les frais de port passent par une option de livraison Stripe plutôt
      // que par une ligne d'article : ils apparaissent ainsi correctement dans
      // les rapports du tableau de bord.
      shipping_options:
        totals.shippingCents > 0
          ? [
              {
                shipping_rate_data: {
                  type: 'fixed_amount',
                  display_name: 'Livraison France',
                  fixed_amount: {
                    amount: totals.shippingCents,
                    currency: 'eur',
                  },
                },
              },
            ]
          : undefined,

      success_url: `${SITE_URL}/commande/confirmation?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${SITE_URL}/panier`,
    });

    if (!session.url) {
      throw new Error('Stripe n’a pas renvoyé d’URL de paiement.');
    }

    await prisma.order.update({
      where: { id: order.id },
      data: { stripeSessionId: session.id },
    });

    return { redirectUrl: session.url };
  } catch (error) {
    // La commande reste en base au statut « en attente de paiement » : elle
    // sert de trace si le client rappelle. Elle ne décompte aucun stock.
    console.error('Création de la session Stripe impossible', error);

    return {
      error:
        'Le paiement n’a pas pu être lancé. Réessayez dans un instant ; si le problème persiste, contactez-nous.',
    };
  }
}
