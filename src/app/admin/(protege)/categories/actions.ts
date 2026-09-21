'use server';

import { revalidatePath } from 'next/cache';
import { categorySchema } from '@/lib/admin-validation';
import { prisma } from '@/lib/prisma';
import { requireAdmin } from '@/lib/require-admin';
import { revalidateShop } from '@/lib/revalidate';
import { slugify, uniqueSlug } from '@/lib/slug';

export type CategoryFormState = {
  error?: string;
  fieldErrors?: Record<string, string>;
  message?: string;
  /**
   * Valeurs saisies, renvoyées au formulaire : React réinitialise les champs
   * non contrôlés à la fin d'une action.
   */
  values?: { name: string; description: string };
};

/** Crée une catégorie, ou renomme celle dont l'identifiant est fourni. */
export async function saveCategory(
  _previousState: CategoryFormState,
  formData: FormData,
): Promise<CategoryFormState> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '').trim();

  const submitted = {
    name: String(formData.get('name') ?? ''),
    description: String(formData.get('description') ?? ''),
  };

  const parsed = categorySchema.safeParse(submitted);

  if (!parsed.success) {
    const fieldErrors: Record<string, string> = {};
    for (const issue of parsed.error.issues) {
      fieldErrors[String(issue.path[0] ?? 'global')] ??= issue.message;
    }
    return {
      error: 'Certains champs doivent être corrigés.',
      fieldErrors,
      values: submitted,
    };
  }

  const { name, description } = parsed.data;

  const slug = await uniqueSlug(slugify(name), async (candidate) => {
    const existing = await prisma.category.findUnique({
      where: { slug: candidate },
      select: { id: true },
    });
    return existing !== null && existing.id !== id;
  });

  let previousSlug: string | null = null;

  if (id) {
    const existing = await prisma.category.findUnique({
      where: { id },
      select: { slug: true },
    });
    if (!existing) return { error: 'Cette catégorie n’existe plus.' };

    previousSlug = existing.slug;

    await prisma.category.update({
      where: { id },
      data: { name, slug, description: description || null },
    });
  } else {
    // La nouvelle catégorie se place en fin de liste.
    const last = await prisma.category.aggregate({ _max: { position: true } });

    await prisma.category.create({
      data: {
        name,
        slug,
        description: description || null,
        position: (last._max.position ?? -1) + 1,
      },
    });
  }

  revalidatePath('/admin/categories');
  revalidateShop({ categorySlugs: [slug, previousSlug] });

  return {
    message: id
      ? `La catégorie « ${name} » a été mise à jour.`
      : `La catégorie « ${name} » a été créée.`,
  };
}

/**
 * Supprime une catégorie.
 * Refusée si des produits y sont encore rangés : il faut d'abord les déplacer,
 * sinon ils se retrouveraient sans catégorie.
 */
export async function deleteCategory(formData: FormData): Promise<void> {
  await requireAdmin();

  const id = String(formData.get('id') ?? '');
  if (!id) return;

  const category = await prisma.category.findUnique({
    where: { id },
    select: { slug: true, _count: { select: { products: true } } },
  });
  if (!category || category._count.products > 0) return;

  await prisma.category.delete({ where: { id } });

  revalidatePath('/admin/categories');
  revalidateShop({ categorySlugs: [category.slug] });
}
