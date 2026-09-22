'use client';

import Link from 'next/link';
import { useActionState, useState } from 'react';
import {
  deleteCategory,
  saveCategory,
  type CategoryFormState,
} from '@/app/admin/(protege)/categories/actions';
import { ConfirmButton } from '@/components/admin/ConfirmButton';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';

export type AdminCategory = {
  id: string;
  name: string;
  slug: string;
  description: string;
  productCount: number;
};

const INITIAL_STATE: CategoryFormState = {};

export function CategoryManager({ categories }: { categories: AdminCategory[] }) {
  // Identifiant de la catégorie en cours de renommage, null si aucune.
  const [editingId, setEditingId] = useState<string | null>(null);

  return (
    <div className="grid gap-12 lg:grid-cols-[1fr_22rem]">
      {/* --- Liste --------------------------------------------------------- */}
      <div>
        {categories.length === 0 ? (
          <p className="py-12 text-ink-soft">
            Aucune catégorie. Créez-en une pour commencer.
          </p>
        ) : (
          <ul className="divide-y divide-line border-y border-line">
            {categories.map((category) => (
              <li key={category.id} className="py-4">
                {editingId === category.id ? (
                  <CategoryForm
                    category={category}
                    onDone={() => setEditingId(null)}
                  />
                ) : (
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                      <p className="font-medium">{category.name}</p>
                      {category.description && (
                        <p className="mt-1 text-sm text-ink-soft">
                          {category.description}
                        </p>
                      )}
                      <p className="mt-1 text-sm text-ink-soft">
                        {category.productCount} produit
                        {category.productCount > 1 ? 's' : ''} ·{' '}
                        <Link
                          href={`/boutique/${category.slug}`}
                          target="_blank"
                          rel="noreferrer"
                          className="underline underline-offset-4"
                        >
                          /boutique/{category.slug}
                        </Link>
                      </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-4">
                      <button
                        type="button"
                        onClick={() => setEditingId(category.id)}
                        className="text-sm underline underline-offset-4"
                      >
                        Renommer
                      </button>

                      {category.productCount === 0 ? (
                        <form action={deleteCategory}>
                          <input type="hidden" name="id" value={category.id} />
                          <ConfirmButton
                            label="Supprimer"
                            title={`Supprimer « ${category.name} » ?`}
                            message="Cette catégorie ne contient aucun produit. La suppression est définitive."
                          />
                        </form>
                      ) : (
                        <span
                          className="text-sm text-ink-soft"
                          title="Déplacez d'abord ses produits dans une autre catégorie."
                        >
                          Non supprimable
                        </span>
                      )}
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* --- Création ------------------------------------------------------ */}
      <aside className="lg:sticky lg:top-8 lg:self-start">
        <div className="border border-line p-5">
          <h2 className="font-sans text-sm font-medium">Nouvelle catégorie</h2>
          <div className="mt-4">
            {/* Pas de `key` liée au nombre de catégories : la remonter
                réinitialiserait l'état de l'action, et le message de
                confirmation disparaîtrait aussitôt affiché. React vide déjà
                les champs non contrôlés à la fin d'une action réussie. */}
            <CategoryForm />
          </div>
        </div>
      </aside>
    </div>
  );
}

function CategoryForm({
  category,
  onDone,
}: {
  category?: AdminCategory;
  onDone?: () => void;
}) {
  const [state, formAction, isPending] = useActionState(
    saveCategory,
    INITIAL_STATE,
  );
  const errors = state.fieldErrors ?? {};

  // Valeurs réaffichées après une erreur : React vide les champs non
  // contrôlés à la fin d'une action.
  const current = state.values ?? {
    name: category?.name ?? '',
    description: category?.description ?? '',
  };

  // Voir ProductForm : remonter le formulaire rétablit tous les champs après
  // une erreur, pas seulement les champs texte.
  const formKey = state.values ? JSON.stringify(state.values) : 'initial';

  return (
    <form key={formKey} action={formAction} className="space-y-4">
      {category && <input type="hidden" name="id" value={category.id} />}

      {state.error && (
        <p role="alert" className="text-sm text-accent-deep">
          {state.error}
        </p>
      )}
      {state.message && (
        <p role="status" className="text-sm">
          {state.message}
        </p>
      )}

      <Field
        label="Nom"
        name="name"
        fieldId={`categorie-nom-${category?.id ?? 'nouvelle'}`}
        required
        error={errors.name}
      >
        {(props) => (
          <input type="text" defaultValue={current.name} {...props} />
        )}
      </Field>

      <Field
        label="Description"
        name="description"
        fieldId={`categorie-description-${category?.id ?? 'nouvelle'}`}
        hint="Une phrase affichée en haut de la page de la catégorie."
        error={errors.description}
      >
        {(props) => (
          <textarea rows={2} defaultValue={current.description} {...props} />
        )}
      </Field>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={isPending}>
          {isPending ? 'Enregistrement…' : category ? 'Enregistrer' : 'Créer'}
        </Button>
        {onDone && (
          <button
            type="button"
            onClick={onDone}
            className="text-sm text-ink-soft underline underline-offset-4"
          >
            Annuler
          </button>
        )}
      </div>
    </form>
  );
}
