import { revalidatePath } from 'next/cache';
import { NextResponse } from 'next/server';
import type Stripe from 'stripe';
import { ORDER_STATUS } from '@/lib/constants';
import { requireEnv } from '@/lib/env';
import { prisma } from '@/lib/prisma';
import { getStripe } from '@/lib/stripe';

/**
 * Webhook Stripe — c'est ici, et nulle part ailleurs, qu'une commande devient
 * « payée ».
 *
 * Le retour du navigateur sur la page de confirmation ne prouve rien : il peut
 * être rejoué, interrompu ou fabriqué. Seul cet appel signé par Stripe fait
 * foi, et c'est donc lui qui décrémente le stock.
 *
 * Mise en place :
 *   - en local  : `stripe listen --forward-to localhost:3000/api/stripe/webhook`
 *   - en ligne  : tableau de bord Stripe > Développeurs > Webhooks, endpoint
 *     `<site>/api/stripe/webhook`, événement `checkout.session.completed`
 */

// Le corps de la requête doit rester brut : la signature Stripe porte sur les
// octets exacts reçus.
export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

export async function POST(request: Request) {
  const signature = request.headers.get('stripe-signature');
  if (!signature) {
    return NextResponse.json({ error: 'Signature absente.' }, { status: 400 });
  }

  const payload = await request.text();

  let event: Stripe.Event;
  try {
    event = getStripe().webhooks.constructEvent(
      payload,
      signature,
      requireEnv('STRIPE_WEBHOOK_SECRET'),
    );
  } catch (error) {
    // Signature invalide : la requête ne vient pas de Stripe, on l'ignore.
    console.error('Signature de webhook Stripe invalide', error);
    return NextResponse.json({ error: 'Signature invalide.' }, { status: 400 });
  }

  try {
    switch (event.type) {
      case 'checkout.session.completed':
      case 'checkout.session.async_payment_succeeded':
        await markOrderPaid(event.data.object);
        break;

      case 'checkout.session.expired':
      case 'checkout.session.async_payment_failed':
        await cancelOrder(event.data.object);
        break;

      default:
        // Les autres événements ne nous concernent pas.
        break;
    }
  } catch (error) {
    // On renvoie une erreur pour que Stripe rejoue l'événement plus tard
    // plutôt que de perdre définitivement un paiement encaissé.
    console.error(`Traitement du webhook ${event.type} en échec`, error);
    return NextResponse.json({ error: 'Traitement en échec.' }, { status: 500 });
  }

  return NextResponse.json({ received: true });
}

/** Passe la commande en « payée » et décompte le stock, une seule fois. */
async function markOrderPaid(session: Stripe.Checkout.Session): Promise<void> {
  if (session.payment_status !== 'paid') return;

  const orderId = session.metadata?.orderId;
  if (!orderId) {
    console.error('Session Stripe sans orderId dans les métadonnées', session.id);
    return;
  }

  await prisma.$transaction(async (tx) => {
    const order = await tx.order.findUnique({
      where: { id: orderId },
      include: { items: true },
    });

    if (!order) {
      console.error(`Commande introuvable pour la session ${session.id}`);
      return;
    }

    // Stripe peut rejouer le même événement : sans ce garde-fou, le stock
    // serait décompté deux fois.
    if (order.status !== ORDER_STATUS.PENDING) return;

    await tx.order.update({
      where: { id: order.id },
      data: {
        status: ORDER_STATUS.PAID,
        paidAt: new Date(),
        stripeSessionId: session.id,
        stripePaymentIntentId:
          typeof session.payment_intent === 'string'
            ? session.payment_intent
            : (session.payment_intent?.id ?? null),
      },
    });

    for (const item of order.items) {
      if (item.variantId) {
        const variant = await tx.productVariant.findUnique({
          where: { id: item.variantId },
          select: { stock: true },
        });
        if (!variant) continue;

        await tx.productVariant.update({
          where: { id: item.variantId },
          // Le stock ne descend jamais sous zéro : un compteur négatif serait
          // incompréhensible dans l'admin.
          data: { stock: Math.max(0, variant.stock - item.quantity) },
        });
      } else if (item.productId) {
        const product = await tx.product.findUnique({
          where: { id: item.productId },
          select: { stock: true },
        });
        if (!product) continue;

        await tx.product.update({
          where: { id: item.productId },
          data: { stock: Math.max(0, product.stock - item.quantity) },
        });
      }
    }
  });

  // Les pages produits affichent la disponibilité : on les régénère sans
  // attendre la revalidation périodique.
  revalidatePath('/boutique');
  revalidatePath('/', 'layout');
}

/** Annule une commande dont le paiement a expiré ou échoué. */
async function cancelOrder(session: Stripe.Checkout.Session): Promise<void> {
  const orderId = session.metadata?.orderId;
  if (!orderId) return;

  // Seules les commandes encore en attente sont annulées : on ne touche pas à
  // une commande déjà payée, même si un événement arrive dans le désordre.
  await prisma.order.updateMany({
    where: { id: orderId, status: ORDER_STATUS.PENDING },
    data: { status: ORDER_STATUS.CANCELLED },
  });
}
