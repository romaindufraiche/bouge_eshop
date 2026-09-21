import 'server-only';
import { randomUUID } from 'node:crypto';
import { mkdir, unlink, writeFile } from 'node:fs/promises';
import path from 'node:path';
import {
  ALLOWED_IMAGE_TYPES,
  MAX_IMAGE_BYTES,
  MAX_IMAGE_MEGABYTES,
} from '@/lib/upload-constants';

/**
 * Stockage des photos produits.
 *
 * En développement, les fichiers sont écrits dans public/uploads et servis
 * directement par Next.js. En production, un hébergement sans disque
 * persistant (Vercel, notamment) perd ces fichiers à chaque déploiement : il
 * faut alors basculer UPLOAD_DRIVER sur un service externe.
 *
 * Tout passe par ce module : ajouter un service revient à écrire ses deux
 * fonctions ici, sans toucher au reste de l'application.
 */

/** Dossier public où atterrissent les fichiers en mode « local ». */
const LOCAL_DIRECTORY = path.join(process.cwd(), 'public', 'uploads');
const LOCAL_URL_PREFIX = '/uploads/';

export type StoredImage = { url: string };

export class UploadError extends Error {}

function getDriver(): string {
  return process.env.UPLOAD_DRIVER?.trim() || 'local';
}

/**
 * Enregistre une photo et renvoie l'URL à stocker en base.
 * @throws {UploadError} si le fichier est vide, trop lourd ou d'un format refusé
 */
export async function saveUploadedImage(file: File): Promise<StoredImage> {
  if (file.size === 0) {
    throw new UploadError('Le fichier est vide.');
  }

  if (file.size > MAX_IMAGE_BYTES) {
    throw new UploadError(
      `« ${file.name} » dépasse ${MAX_IMAGE_MEGABYTES} Mo. Réduisez la photo avant de l'envoyer.`,
    );
  }

  const extension = ALLOWED_IMAGE_TYPES[file.type];
  if (!extension) {
    throw new UploadError(
      `« ${file.name} » n'est pas dans un format accepté. Utilisez du JPEG, PNG, WebP ou AVIF.`,
    );
  }

  const driver = getDriver();

  if (driver === 'local') {
    await mkdir(LOCAL_DIRECTORY, { recursive: true });

    // Nom aléatoire : deux photos du même nom ne s'écrasent pas, et le nom du
    // fichier d'origine — non maîtrisé — ne se retrouve pas dans une URL.
    const filename = `${randomUUID()}${extension}`;
    const buffer = Buffer.from(await file.arrayBuffer());
    await writeFile(path.join(LOCAL_DIRECTORY, filename), buffer);

    return { url: `${LOCAL_URL_PREFIX}${filename}` };
  }

  throw new UploadError(
    `Le stockage « ${driver} » n'est pas encore branché. Implémentez-le dans src/lib/storage.ts, ou repassez UPLOAD_DRIVER sur « local ».`,
  );
}

/**
 * Supprime une photo du stockage.
 * Ne lève jamais : un fichier déjà absent ne doit pas empêcher la suppression
 * du produit correspondant en base.
 */
export async function deleteStoredImage(url: string): Promise<void> {
  if (!url.startsWith(LOCAL_URL_PREFIX)) return;

  // On ne garde que le nom de fichier : un chemin contenant « ../ » ne peut
  // pas faire sortir du dossier d'upload.
  const filename = path.basename(url);

  try {
    await unlink(path.join(LOCAL_DIRECTORY, filename));
  } catch {
    // Fichier déjà supprimé ou jamais écrit : rien à faire.
  }
}
