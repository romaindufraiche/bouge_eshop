/**
 * Transforme un libellé en identifiant d'URL : « Bonnet silicone uni »
 * devient « bonnet-silicone-uni ».
 */
export function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // retire les accents
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);
}

/**
 * Rend un slug unique en lui ajoutant un suffixe numérique si besoin :
 * « bonnet », « bonnet-2 », « bonnet-3 »…
 *
 * @param isTaken renvoie vrai si le slug est déjà utilisé par un AUTRE élément
 */
export async function uniqueSlug(
  base: string,
  isTaken: (slug: string) => Promise<boolean>,
): Promise<string> {
  const root = slugify(base) || 'produit';

  if (!(await isTaken(root))) return root;

  for (let suffix = 2; suffix < 100; suffix += 1) {
    const candidate = `${root}-${suffix}`;
    if (!(await isTaken(candidate))) return candidate;
  }

  // Cas extrême : on retombe sur un suffixe temporel, toujours disponible.
  return `${root}-${Date.now()}`;
}
