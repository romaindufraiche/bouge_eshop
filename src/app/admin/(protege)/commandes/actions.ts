'use server';

import { revalidatePath } from 'next/cache';
import {
  availableOrderStatuses,
  type OrderStatus,
} from '@/lib/constants';
import { prisma } from '@/lib/prisma';
import { requireAdmin } from '@/lib/require-admin';

/**
 * Change le statut d'une commande.
 *
 * Le statut proposé dépend du mode de remise — « Expédiée » n'a pas de sens
 * pour un retrait sur place — et la valeur reçue est revérifiée ici : le
 * formulaire ne fait pas foi.
 */
export async function updateOrderStatus(formData: FormData): Promise<void> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '');
  const status = String(formData.get('status') ?? '');
  if (!id || !status) return;

  const order = await prisma.order.findUnique({
    where: { id },
    select: { fulfilment: true },
  });
  if (!order) return;

  const allowed = availableOrderStatuses(order.fulfilment);
  if (!allowed.includes(status as OrderStatus)) return;

  await prisma.order.update({
    where: { id },
    data: { status },
  });

  revalidatePath(`/admin/commandes/${id}`);
  revalidatePath('/admin/commandes');
  revalidatePath('/admin');
}

/** Enregistre une note interne, visible uniquement dans l'administration. */
export async function updateAdminNote(formData: FormData): Promise<void> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '');
  if (!id) return;

  const note = String(formData.get('adminNote') ?? '').trim().slice(0, 2000);

  await prisma.order.update({
    where: { id },
    data: { adminNote: note || null },
  });

  revalidatePath(`/admin/commandes/${id}`);
}
