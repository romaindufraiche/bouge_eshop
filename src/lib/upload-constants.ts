/**
 * Contraintes d'envoi des photos.
 *
 * Volontairement isolées de src/lib/storage.ts : ce dernier accède au système
 * de fichiers et ne doit jamais être importé depuis un composant client. Ces
 * valeurs, elles, servent des deux côtés — le navigateur pour annoncer les
 * limites, le serveur pour les faire respecter.
 */

/** Taille maximale par photo, en octets. */
export const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

/** Formats acceptés, avec l'extension utilisée à l'enregistrement. */
export const ALLOWED_IMAGE_TYPES: Record<string, string> = {
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'image/webp': '.webp',
  'image/avif': '.avif',
};

/** Valeur de l'attribut `accept` d'un <input type="file">. */
export const IMAGE_ACCEPT_ATTRIBUTE = Object.keys(ALLOWED_IMAGE_TYPES).join(',');

/** Limite exprimée en mégaoctets, pour l'affichage. */
export const MAX_IMAGE_MEGABYTES = Math.round(MAX_IMAGE_BYTES / 1024 / 1024);
