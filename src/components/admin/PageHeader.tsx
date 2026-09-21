import type { ReactNode } from 'react';

/** En-tête de page d'administration : titre, phrase d'explication, actions. */
export function PageHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-6">
      <div>
        <h1 className="text-3xl">{title}</h1>
        {description && (
          <p className="mt-2 max-w-2xl text-sm text-ink-soft">{description}</p>
        )}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}
