'use client';

import { useState } from 'react';

export type VariantDraft = {
  /** Vide pour une déclinaison qui n'a pas encore été enregistrée. */
  id: string;
  size: string;
  color: string;
  stock: string;
  /** Prix en euros, vide pour reprendre celui du produit. */
  price: string;
};

/**
 * Éditeur de déclinaisons (taille / couleur).
 *
 * Les lignes sont tenues en état local et sérialisées dans un champ caché : le
 * formulaire produit part ainsi en une seule fois, sans enregistrement
 * intermédiaire.
 */
export function VariantsEditor({
  initialVariants,
  error,
}: {
  initialVariants: VariantDraft[];
  error?: string;
}) {
  const [variants, setVariants] = useState<VariantDraft[]>(initialVariants);

  function update(index: number, patch: Partial<VariantDraft>) {
    setVariants((current) =>
      current.map((variant, position) =>
        position === index ? { ...variant, ...patch } : variant,
      ),
    );
  }

  function addRow() {
    setVariants((current) => [
      ...current,
      { id: '', size: '', color: '', stock: '0', price: '' },
    ]);
  }

  function removeRow(index: number) {
    setVariants((current) => current.filter((_, position) => position !== index));
  }

  return (
    <div>
      <input type="hidden" name="variants" value={JSON.stringify(variants)} />

      {variants.length === 0 ? (
        <p className="text-sm text-ink-soft">
          Aucune déclinaison : le produit se vend en un seul modèle, et son stock
          est celui indiqué plus haut.
        </p>
      ) : (
        <ul className="space-y-3">
          {/* En-têtes de colonnes, masqués sur mobile où chaque champ porte
              déjà son propre libellé. */}
          <li className="hidden gap-3 text-xs uppercase tracking-widest text-ink-soft sm:grid sm:grid-cols-[1fr_1fr_6rem_7rem_3rem]">
            <span>Taille</span>
            <span>Couleur</span>
            <span>Stock</span>
            <span>Prix spécifique</span>
            <span className="sr-only">Action</span>
          </li>

          {variants.map((variant, index) => (
            <li
              key={variant.id || `nouvelle-${index}`}
              className="grid gap-3 border border-line p-3 sm:grid-cols-[1fr_1fr_6rem_7rem_3rem] sm:items-center sm:border-0 sm:p-0"
            >
              <LabelledInput
                label="Taille"
                value={variant.size}
                placeholder="M, 38, 2 (30)…"
                onChange={(value) => update(index, { size: value })}
              />
              <LabelledInput
                label="Couleur"
                value={variant.color}
                placeholder="Noir, Bleu…"
                onChange={(value) => update(index, { color: value })}
              />
              <LabelledInput
                label="Stock"
                value={variant.stock}
                type="number"
                onChange={(value) => update(index, { stock: value })}
              />
              <LabelledInput
                label="Prix spécifique"
                value={variant.price}
                placeholder="Prix produit"
                onChange={(value) => update(index, { price: value })}
              />

              <button
                type="button"
                onClick={() => removeRow(index)}
                className="justify-self-start text-sm text-accent underline underline-offset-4 sm:justify-self-center"
              >
                <span className="sm:hidden">Retirer cette déclinaison</span>
                <span className="hidden sm:inline" aria-hidden="true">
                  ✕
                </span>
                <span className="sr-only hidden sm:inline">
                  Retirer la déclinaison {index + 1}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}

      {error && <p className="mt-3 text-sm text-accent">{error}</p>}

      <button
        type="button"
        onClick={addRow}
        className="mt-4 rounded-sm border border-ink px-4 py-2 text-sm hover:bg-ink hover:text-cream"
      >
        Ajouter une déclinaison
      </button>

      <p className="mt-3 text-sm text-ink-soft">
        Laissez « Prix spécifique » vide pour appliquer le prix du produit.
        Retirer une déclinaison la supprime à l&apos;enregistrement.
      </p>
    </div>
  );
}

function LabelledInput({
  label,
  value,
  onChange,
  type = 'text',
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  placeholder?: string;
}) {
  return (
    <label className="block">
      <span className="block text-xs text-ink-soft sm:sr-only">{label}</span>
      <input
        type={type}
        min={type === 'number' ? 0 : undefined}
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
        className="mt-1 w-full rounded-sm border border-line bg-cream px-3 py-2 text-sm sm:mt-0"
      />
    </label>
  );
}
