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
  /**
   * Coordonnées telles qu'elles ont été saisies, renvoyées au formulaire.
   * React réinitialise les champs non contrôlés à la fin d'une action : sans
   * cela, une adresse refusée pour un code postal mal saisi obligerait à tout
   * retaper, au pire moment du parcours d'achat.
   */
  values?: SubmittedCheckoutValues;
};

export type SubmittedCheckoutValues = {
  email: string;
  customerName: string;
  phone: string;
  shippingAddressLine1: string;
  shippingAddressLine2: string;
  shippingPostalCode: string;
  shippingCity: string;
  pickupPointId: string;
};

/** Relit le formulaire tel quel, pour pouvoir le réafficher à l'identique. */
function readSubmittedValues(formData: FormData): SubmittedCheckoutValues {
  const text = (field: string) => String(formData.get(field) ?? '');

  return {
    email: text('email'),
    customerName: text('customerName'),
    phone: text('phone'),
    shippingAddressLine1: text('shippingAddressLine1'),
    shippingAddressLine2: text('shippingAddressLine2'),
    shippingPostalCode: text('shippingPostalCode'),
    shippingCity: text('shippingCity'),
    pickupPointId: text('pickupPointId'),
  };
}

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
  const submitted = readSubmittedValues(formData);

  let lines: unknown;
  try {
    lines = JSON.parse(String(formData.get('lines') ?? '[]'));
  } catch {
    return {
      error: 'Panier illisible. Rechargez la page et réessayez.',
      values: submitted,
    };
  }

  // On valide les valeurs DÉJÀ normalisées en chaînes.
  //
  // `formData.get()` renvoie `null` pour un champ absent du document, et les
  // champs d'adresse comme ceux du point de retrait ne sont rendus que dans la
  // branche choisie : lire le formulaire brut faisait donc échouer toute
  // commande, quel que soit le mode, sur un champ que le client ne voyait même
  // pas à l'écran.
  const parsed = checkoutSchema.safeParse({
    ...submitted,
    fulfilment: String(formData.get('fulfilment') ?? ''),
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
      values: submitted,
    };
  }

  const input = parsed.data;

  // --- 2. Relecture du panier en base --------------------------------------
  const cart = await resolveCart(input.lines);

  if (cart.isEmpty) {
    return { error: 'Votre panier est vide.', values: submitted };
  }

  if (cart.issues.length > 0) {
    return {
      error:
        'Votre panier a changé depuis votre dernière visite (stock ou disponibilité). Vérifiez le récapitulatif ci-dessous, puis relancez le paiement.',
      values: submitted,
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
      values: submitted,
    };
  }
}
