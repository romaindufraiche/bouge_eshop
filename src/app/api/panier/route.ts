import { NextResponse } from 'next/server';
import { resolveCart } from '@/lib/cart-server';
import { cartPayloadSchema } from '@/lib/validation';

/**
 * Renvoie le détail chiffré d'un panier.
 *
 * Le navigateur ne connaît que des identifiants et des quantités : les
 * libellés, les prix, les promotions et les stocks sont relus ici, à chaque
 * affichage du panier. Un prix modifié dans l'admin est donc immédiatement
 * visible côté client.
 */
export async function POST(request: Request) {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { error: 'Requête illisible.' },
      { status: 400 },
    );
  }

  const parsed = cartPayloadSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: 'Panier invalide.' }, { status: 400 });
  }

  const cart = await resolveCart(parsed.data.lines);
  return NextResponse.json(cart);
}
