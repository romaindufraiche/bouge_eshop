'use server';

import { revalidatePath } from 'next/cache';
import { prisma } from '@/lib/prisma';
import { requireAdmin } from '@/lib/require-admin';
import { revalidateShop } from '@/lib/revalidate';
import { deleteStoredImage, saveUploadedImage, UploadError } from '@/lib/storage';

export type ImageActionState = { error?: string; message?: string };

/** Régénère la fiche admin et la fiche publique du produit. */
async function refresh(productId: string): Promise<void> {
  const product = await prisma.product.findUnique({
    where: { id: productId },
    select: { slug: true, category: { select: { slug: true } } },
  });

  revalidatePath(`/admin/produits/${productId}`);
  revalidateShop({
    productSlugs: [product?.slug],
    categorySlugs: [product?.category.slug],
  });
}

/** Envoie une ou plusieurs photos et les ajoute à la fin de la galerie. */
export async function uploadImages(
  _previousState: ImageActionState,
  formData: FormData,
): Promise<ImageActionState> {
  await requireAdmin();

  const productId = String(formData.get('productId') ?? '');
  const files = formData.getAll('files').filter((entry): entry is File =>
    entry instanceof File && entry.size > 0,
  );

  if (!productId) return { error: 'Produit introuvable.' };
  if (files.length === 0) return { error: 'Choisissez au moins une photo.' };

  const product = await prisma.product.findUnique({
    where: { id: productId },
    select: { name: true },
  });
  if (!product) return { error: 'Ce produit n’existe plus.' };

  // Les nouvelles photos se placent après les existantes.
  const lastPosition = await prisma.productImage.aggregate({
    where: { productId },
    _max: { position: true },
  });
  let position = (lastPosition._max.position ?? -1) + 1;

  const saved: string[] = [];

  try {
    for (const file of files) {
      const { url } = await saveUploadedImage(file);
      saved.push(url);

      await prisma.productImage.create({
        data: {
          productId,
          url,
          // Texte alternatif par défaut, modifiable juste après : mieux vaut
          // une description imparfaite qu'une image sans alternative.
          alt: product.name,
          position,
        },
      });
      position += 1;
    }
  } catch (error) {
    // Les fichiers déjà écrits pour cet envoi sont retirés : on ne laisse pas
    // de fichiers orphelins sur le disque.
    for (const url of saved) await deleteStoredImage(url);

    if (error instanceof UploadError) return { error: error.message };

    console.error('Envoi de photos en échec', error);
    return { error: 'L’envoi a échoué. Réessayez.' };
  }

  await refresh(productId);

  return {
    message: `${files.length} photo${files.length > 1 ? 's' : ''} ajoutée${files.length > 1 ? 's' : ''}.`,
  };
}

/** Supprime une photo, en base et sur le stockage. */
export async function deleteImage(formData: FormData): Promise<void> {
  await requireAdmin();

  const imageId = String(formData.get('imageId') ?? '');
  if (!imageId) return;

  const image = await prisma.productImage.findUnique({ where: { id: imageId } });
  if (!image) return;

  await prisma.productImage.delete({ where: { id: imageId } });
  await deleteStoredImage(image.url);

  // Les positions sont recalculées pour rester consécutives.
  await renumber(image.productId);
  await refresh(image.productId);
}

/** Déplace une photo d'un cran vers le haut ou vers le bas. */
export async function moveImage(formData: FormData): Promise<void> {
  await requireAdmin();

  const imageId = String(formData.get('imageId') ?? '');
  const direction = String(formData.get('direction') ?? '');
  if (!imageId || (direction !== 'up' && direction !== 'down')) return;

  const image = await prisma.productImage.findUnique({ where: { id: imageId } });
  if (!image) return;

  const images = await prisma.productImage.findMany({
    where: { productId: image.productId },
    orderBy: { position: 'asc' },
  });

  const index = images.findIndex((candidate) => candidate.id === imageId);
  const target = direction === 'up' ? index - 1 : index + 1;

  // Déjà en première ou en dernière position : rien à faire.
  if (target < 0 || target >= images.length) return;

  const reordered = [...images];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];

  await prisma.$transaction(
    reordered.map((item, position) =>
      prisma.productImage.update({ where: { id: item.id }, data: { position } }),
    ),
  );

  await refresh(image.productId);
}

/** Met à jour le texte alternatif d'une photo. */
export async function updateImageAlt(formData: FormData): Promise<void> {
  await requireAdmin();

  const imageId = String(formData.get('imageId') ?? '');
  const alt = String(formData.get('alt') ?? '').trim().slice(0, 200);
  if (!imageId || alt === '') return;

  const image = await prisma.productImage.update({
    where: { id: imageId },
    data: { alt },
    select: { productId: true },
  });

  await refresh(image.productId);
}

/** Renumérote les positions de 0 à n-1, sans trou. */
async function renumber(productId: string): Promise<void> {
  const images = await prisma.productImage.findMany({
    where: { productId },
    orderBy: { position: 'asc' },
    select: { id: true },
  });

  await prisma.$transaction(
    images.map((image, position) =>
      prisma.productImage.update({ where: { id: image.id }, data: { position } }),
    ),
  );
}
