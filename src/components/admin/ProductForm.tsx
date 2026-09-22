'use client';

import Link from 'next/link';
import { useActionState, useState } from 'react';
import {
  saveProduct,
  type ProductFormState,
} from '@/app/admin/(protege)/produits/actions';
import {
  VariantsEditor,
  type VariantDraft,
} from '@/components/admin/VariantsEditor';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { PRODUCT_STATUS } from '@/lib/constants';

export type ProductFormValues = {
  id: string;
  name: string;
  slug: string;
  description: string;
  categoryId: string;
  price: string;
  salePrice: string;
  saleStartsAt: string;
  saleEndsAt: string;
  status: string;
  stock: string;
  metaTitle: string;
  metaDescription: string;
  featured: boolean;
  availableInStore: boolean;
  externalUrl: string;
  externalLabel: string;
  variants: VariantDraft[];
};

const INITIAL_STATE: ProductFormState = {};

export function ProductForm({
  values,
  categories,
}: {
  values: ProductFormValues;
  categories: { id: string; name: string }[];
}) {
  const [state, formAction, isPending] = useActionState(
    saveProduct,
    INITIAL_STATE,
  );
  const errors = state.fieldErrors ?? {};

  // Après une erreur de validation, on réaffiche ce qui a été saisi plutôt que
  // les valeurs d'origine : React vide les champs non contrôlés à la fin d'une
  // action, et retaper une fiche entière serait pénible.
  const current = state.values ?? values;

  // Le prix promotionnel est replié tant qu'il n'y en a pas : le formulaire
  // reste court pour le cas courant.
  const [hasSale, setHasSale] = useState(values.salePrice !== '');

  // Idem pour la vente par un tiers, qui reste un cas minoritaire.
  const [venduAilleurs, setVenduAilleurs] = useState(
    (state.values?.externalUrl ?? values.externalUrl) !== '',
  );

  // Les cases à cocher reviennent du serveur sous forme de chaîne ('on' ou
  // vide), alors que les valeurs d'origine sont des booléens : on ramène les
  // deux au même type avant de les passer au formulaire.
  const estMisEnAvant = state.values
    ? state.values.featured !== ''
    : values.featured;
  const estEnMagasin = state.values
    ? state.values.availableInStore !== ''
    : values.availableInStore;

  // React ne resynchronise `defaultValue` que sur les champs texte : un
  // <select> ou un bouton radio garderaient la sélection d'origine et non
  // celle qui vient d'être soumise. Changer la clé remonte le formulaire, ce
  // qui rétablit correctement TOUS les champs après une erreur.
  const formKey = state.values ? JSON.stringify(state.values) : 'initial';

  return (
    <form key={formKey} action={formAction} className="mt-8 space-y-12">
      <input type="hidden" name="id" value={values.id} />

      {state.error && (
        <p role="alert" className="border-l-2 border-accent bg-sand px-4 py-3 text-sm">
          {state.error}
        </p>
      )}

      {/* --- L'essentiel --------------------------------------------------- */}
      <section className="space-y-5">
        <SectionTitle>Le produit</SectionTitle>

        <Field label="Nom" name="name" required error={errors.name}>
          {(props) => (
            <input type="text" defaultValue={current.name} {...props} />
          )}
        </Field>

        <Field
          label="Description"
          name="description"
          required
          hint="Ce que le client lit sur la fiche. Les retours à la ligne sont conservés."
          error={errors.description}
        >
          {(props) => (
            <textarea rows={6} defaultValue={current.description} {...props} />
          )}
        </Field>

        <Field label="Catégorie" name="categoryId" required error={errors.categoryId}>
          {(props) => (
            <select defaultValue={current.categoryId} {...props}>
              <option value="">Choisir…</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          )}
        </Field>

        <fieldset>
          <legend className="text-sm font-medium">Visibilité</legend>
          <div className="mt-2 space-y-2">
            <RadioCard
              name="status"
              value={PRODUCT_STATUS.PUBLISHED}
              defaultChecked={current.status === PRODUCT_STATUS.PUBLISHED}
              title="En ligne"
              detail="Visible par les clients et achetable."
            />
            <RadioCard
              name="status"
              value={PRODUCT_STATUS.DRAFT}
              defaultChecked={current.status !== PRODUCT_STATUS.PUBLISHED}
              title="Brouillon"
              detail="Enregistré, mais invisible sur la boutique."
            />
          </div>
        </fieldset>
      </section>

      {/* --- Prix ---------------------------------------------------------- */}
      <section className="space-y-5">
        <SectionTitle>Prix</SectionTitle>

        <div className="grid gap-5 sm:grid-cols-2">
          <Field
            label="Prix de vente"
            name="price"
            required
            hint="En euros, par exemple 14,90"
            error={errors.price}
          >
            {(props) => (
              <input type="text" inputMode="decimal" defaultValue={current.price} {...props} />
            )}
          </Field>

          <Field
            label="Stock"
            name="stock"
            required
            hint="Utilisé uniquement si le produit n'a aucune déclinaison."
            error={errors.stock}
          >
            {(props) => (
              <input type="number" min={0} defaultValue={current.stock} {...props} />
            )}
          </Field>
        </div>

        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={hasSale}
            onChange={(event) => setHasSale(event.target.checked)}
          />
          Mettre ce produit en promotion
        </label>

        {hasSale && (
          <div className="grid gap-5 border-l-2 border-line pl-5 sm:grid-cols-3">
            <Field
              label="Prix promotionnel"
              name="salePrice"
              hint="Doit être inférieur au prix de vente."
              error={errors.salePrice}
            >
              {(props) => (
                <input
                  type="text"
                  inputMode="decimal"
                  defaultValue={current.salePrice}
                  {...props}
                />
              )}
            </Field>

            <Field
              label="Début de la promotion"
              name="saleStartsAt"
              hint="Vide : active tout de suite."
              error={errors.saleStartsAt}
            >
              {(props) => (
                <input type="date" defaultValue={current.saleStartsAt} {...props} />
              )}
            </Field>

            <Field
              label="Fin de la promotion"
              name="saleEndsAt"
              hint="Vide : sans date de fin."
              error={errors.saleEndsAt}
            >
              {(props) => (
                <input type="date" defaultValue={current.saleEndsAt} {...props} />
              )}
            </Field>
          </div>
        )}

        {/* Le champ reste dans le DOM même replié, pour qu'un décochage
            transmette bien une valeur vide et efface la promotion. */}
        {!hasSale && (
          <>
            <input type="hidden" name="salePrice" value="" />
            <input type="hidden" name="saleStartsAt" value="" />
            <input type="hidden" name="saleEndsAt" value="" />
          </>
        )}
      </section>

      {/* --- Déclinaisons --------------------------------------------------- */}
      <section className="space-y-5">
        <SectionTitle>Déclinaisons</SectionTitle>
        <p className="text-sm text-ink-soft">
          Une ligne par combinaison vendue : une taille, une couleur, ou les
          deux. Chacune a son propre stock.
        </p>
        <VariantsEditor
          initialVariants={current.variants}
          error={errors.variants}
        />
      </section>

      {/* --- Vente et disponibilité ------------------------------------------ */}
      <section className="space-y-5">
        <SectionTitle>Vente et disponibilité</SectionTitle>

        <label className="flex items-start gap-3 text-sm">
          <input
            type="checkbox"
            name="featured"
            defaultChecked={estMisEnAvant}
            className="mt-1"
          />
          <span>
            <span className="block font-medium">
              Mettre en avant sur la page d&apos;accueil
            </span>
            <span className="mt-0.5 block text-ink-soft">
              Un grand encart en haut de l&apos;accueil. Un seul produit à la
              fois : si plusieurs sont cochés, le dernier modifié l&apos;emporte.
            </span>
          </span>
        </label>

        <label className="flex items-start gap-3 text-sm">
          <input
            type="checkbox"
            name="availableInStore"
            defaultChecked={estEnMagasin}
            className="mt-1"
          />
          <span>
            <span className="block font-medium">Disponible en magasin</span>
            <span className="mt-0.5 block text-ink-soft">
              Affiche une pastille « En magasin » sur la fiche et dans le
              catalogue.
            </span>
          </span>
        </label>

        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={venduAilleurs}
            onChange={(event) => setVenduAilleurs(event.target.checked)}
          />
          Ce produit est vendu par un revendeur, pas sur cette boutique
        </label>

        {venduAilleurs ? (
          <div className="grid gap-5 border-l-2 border-line pl-5 sm:grid-cols-2">
            <Field
              label="Lien vers la page du revendeur"
              name="externalUrl"
              required
              hint="Le bouton « Ajouter au panier » est remplacé par un lien vers cette page."
              error={errors.externalUrl}
            >
              {(props) => (
                <input
                  type="url"
                  placeholder="https://..."
                  defaultValue={current.externalUrl}
                  {...props}
                />
              )}
            </Field>

            <Field
              label="Nom du revendeur"
              name="externalLabel"
              hint="Affiché sur le bouton : « Acheter sur la Fnac ». Vide, le nom de domaine est utilisé."
              error={errors.externalLabel}
            >
              {(props) => (
                <input
                  type="text"
                  defaultValue={current.externalLabel}
                  {...props}
                />
              )}
            </Field>

            <p className="text-sm text-ink-soft sm:col-span-2">
              Aucun prix n&apos;est affiché pour un produit vendu ailleurs :
              c&apos;est celui du revendeur qui fait foi, et il peut changer sans
              que nous le sachions.
            </p>
          </div>
        ) : (
          /* Champs conservés vides pour qu'un décochage efface bien le lien. */
          <>
            <input type="hidden" name="externalUrl" value="" />
            <input type="hidden" name="externalLabel" value="" />
          </>
        )}
      </section>

      {/* --- Référencement --------------------------------------------------- */}
      <section className="space-y-5">
        <SectionTitle>Référencement</SectionTitle>
        <p className="text-sm text-ink-soft">
          Facultatif. Laissé vide, Google reprend le nom et la description du
          produit.
        </p>

        <Field
          label="Titre dans Google"
          name="metaTitle"
          hint="70 caractères maximum."
          error={errors.metaTitle}
        >
          {(props) => (
            <input type="text" maxLength={70} defaultValue={current.metaTitle} {...props} />
          )}
        </Field>

        <Field
          label="Description dans Google"
          name="metaDescription"
          hint="160 caractères environ."
          error={errors.metaDescription}
        >
          {(props) => (
            <textarea rows={3} maxLength={180} defaultValue={current.metaDescription} {...props} />
          )}
        </Field>

        <Field
          label="Adresse de la page"
          name="slug"
          hint="Laissez vide pour la déduire du nom. La modifier casse les liens existants."
          error={errors.slug}
        >
          {(props) => (
            <input type="text" defaultValue={current.slug} {...props} />
          )}
        </Field>
      </section>

      {/* --- Enregistrer ----------------------------------------------------- */}
      <div className="sticky bottom-0 flex flex-wrap items-center gap-4 border-t border-line bg-cream py-4">
        <Button type="submit" size="lg" disabled={isPending}>
          {isPending ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
        <Link
          href="/admin/produits"
          className="text-sm text-ink-soft underline underline-offset-4"
        >
          Annuler
        </Link>
      </div>
    </form>
  );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
      {children}
    </h2>
  );
}

function RadioCard({
  name,
  value,
  defaultChecked,
  title,
  detail,
}: {
  name: string;
  value: string;
  defaultChecked: boolean;
  title: string;
  detail: string;
}) {
  return (
    <label className="flex cursor-pointer items-start gap-3 border border-line p-3 has-checked:border-ink">
      <input
        type="radio"
        name={name}
        value={value}
        defaultChecked={defaultChecked}
        className="mt-1"
      />
      <span className="text-sm">
        <span className="block font-medium">{title}</span>
        <span className="mt-0.5 block text-ink-soft">{detail}</span>
      </span>
    </label>
  );
}
