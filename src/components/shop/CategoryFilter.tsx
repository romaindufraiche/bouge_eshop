import Link from 'next/link';

/**
 * Filtre par catégorie du catalogue.
 * Chaque filtre est un vrai lien vers une page dédiée : l'URL est partageable
 * et chaque catégorie possède ses propres title et meta description.
 */
export function CategoryFilter({
  categories,
  activeSlug,
}: {
  categories: { id: string; name: string; slug: string }[];
  /** Null sur la page « Tout le matériel ». */
  activeSlug: string | null;
}) {
  return (
    <nav aria-label="Filtrer par catégorie">
      <ul className="flex flex-wrap gap-2">
        <li>
          <FilterLink href="/boutique" isActive={activeSlug === null}>
            Tout
          </FilterLink>
        </li>
        {categories.map((category) => (
          <li key={category.id}>
            <FilterLink
              href={`/boutique/${category.slug}`}
              isActive={activeSlug === category.slug}
            >
              {category.name}
            </FilterLink>
          </li>
        ))}
      </ul>
    </nav>
  );
}

function FilterLink({
  href,
  isActive,
  children,
}: {
  href: string;
  isActive: boolean;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      aria-current={isActive ? 'page' : undefined}
      className={`inline-block rounded-control border px-4 py-2 text-sm font-medium transition-colors ${
        isActive
          ? 'border-ink bg-ink text-cream'
          : 'border-line text-ink hover:border-ink'
      }`}
    >
      {children}
    </Link>
  );
}
