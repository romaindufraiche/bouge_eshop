'use client';

import { useActionState, useEffect, useState } from 'react';
import { createCheckoutSession, type CheckoutState } from '@/app/(boutique)/commande/actions';
import { CartIssues } from '@/components/cart/CartIssues';
import { CartLines } from '@/components/cart/CartLines';
import { CartSummary } from '@/components/cart/CartSummary';
import { useCart } from '@/components/cart/CartProvider';
import { useResolvedCart } from '@/components/cart/useResolvedCart';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { FULFILMENT, FULFILMENT_LABELS } from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { computeShippingCents } from '@/lib/shop-config';

export type PickupPointOption = {
  id: string;
  name: string;
  addressLine1: string;
  addressLine2: string | null;
  postalCode: string;
  city: string;
  hours: string | null;
};

const INITIAL_STATE: CheckoutState = {};

export function CheckoutView({
  pickupPoints,
}: {
  pickupPoints: PickupPointOption[];
}) {
  const { lines } = useCart();
  const { cart, isLoading, hasFailed } = useResolvedCart();

  const [fulfilment, setFulfilment] = useState<string>(FULFILMENT.DELIVERY);
  const [state, formAction, isPending] = useActionState(
    createCheckoutSession,
    INITIAL_STATE,
  );

  // Une fois la session créée, Stripe prend la main sur la page.
  useEffect(() => {
    if (state.redirectUrl) {
      window.location.href = state.redirectUrl;
    }
  }, [state.redirectUrl]);

  if (isLoading) {
    return <p className="py-12 text-ink-soft">Chargement du panier…</p>;
  }

  if (hasFailed) {
    return (
      <p className="py-12 text-ink-soft">
        Le panier n&apos;a pas pu être chargé. Vérifiez votre connexion et
        rechargez la page.
      </p>
    );
  }

  if (!cart || cart.isEmpty) {
    return (
      <div className="py-12">
        <p className="text-ink-soft">
          Votre panier est vide : il n&apos;y a rien à commander.
        </p>
        <div className="mt-6">
          <ButtonLink href="/boutique">Voir le catalogue</ButtonLink>
        </div>
      </div>
    );
  }

  const shippingCents = computeShippingCents(cart.subtotalCents, fulfilment);
  const errors = state.fieldErrors ?? {};

  // Après une erreur, on réaffiche ce que le client avait saisi : React vide
  // les champs non contrôlés à la fin d'une action.
  const saisi = state.values;

  // React ne resynchronise `defaultValue` que sur les champs texte : les
  // boutons radio du point de retrait garderaient leur sélection d'origine.
  // Changer la clé remonte le formulaire et rétablit tous les champs.
  const formKey = saisi ? JSON.stringify(saisi) : 'initial';

  return (
    <form
      key={formKey}
      action={formAction}
      className="grid gap-10 lg:grid-cols-[1fr_20rem] lg:gap-14"
    >
      {/* Le panier part en identifiants seuls ; prix et stocks sont revérifiés
          côté serveur avant tout paiement. */}
      <input type="hidden" name="lines" value={JSON.stringify(lines)} />

      <div className="space-y-10">
        {state.error && (
          <p role="alert" className="border-l-2 border-accent bg-sand px-4 py-3 text-sm">
            {state.error}
          </p>
        )}

        {cart.issues.length > 0 && <CartIssues issues={cart.issues} />}

        {/* --- Coordonnées ------------------------------------------------- */}
        <section className="space-y-5">
          <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
            Vos coordonnées
          </h2>

          <Field label="Nom et prénom" name="customerName" required error={errors.customerName}>
            {(props) => (
              <input
                type="text"
                autoComplete="name"
                defaultValue={saisi?.customerName ?? ''}
                {...props}
              />
            )}
          </Field>

          <Field
            label="Adresse électronique"
            name="email"
            required
            hint="La confirmation de commande y sera envoyée."
            error={errors.email}
          >
            {(props) => (
              <input
                type="email"
                autoComplete="email"
                defaultValue={saisi?.email ?? ''}
                {...props}
              />
            )}
          </Field>

          <Field label="Téléphone" name="phone" error={errors.phone}>
            {(props) => (
              <input
                type="tel"
                autoComplete="tel"
                defaultValue={saisi?.phone ?? ''}
                {...props}
              />
            )}
          </Field>
        </section>

        {/* --- Mode de remise ---------------------------------------------- */}
        <section className="space-y-4">
          <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
            Livraison ou retrait
          </h2>

          <div className="grid gap-3 sm:grid-cols-2">
            <FulfilmentChoice
              value={FULFILMENT.DELIVERY}
              checked={fulfilment === FULFILMENT.DELIVERY}
              onChange={setFulfilment}
              title={FULFILMENT_LABELS.DELIVERY}
              detail={
                computeShippingCents(cart.subtotalCents, FULFILMENT.DELIVERY) === 0
                  ? 'Offerte'
                  : formatPrice(
                      computeShippingCents(cart.subtotalCents, FULFILMENT.DELIVERY),
                    )
              }
            />
            <FulfilmentChoice
              value={FULFILMENT.PICKUP}
              checked={fulfilment === FULFILMENT.PICKUP}
              onChange={setFulfilment}
              title={FULFILMENT_LABELS.PICKUP}
              detail="Sans frais"
              disabled={pickupPoints.length === 0}
            />
          </div>

          {fulfilment === FULFILMENT.DELIVERY ? (
            <div className="space-y-5 border-t border-line pt-5">
              <Field
                label="Adresse"
                name="shippingAddressLine1"
                required
                error={errors.shippingAddressLine1}
              >
                {(props) => (
                  <input
                    type="text"
                    autoComplete="address-line1"
                    defaultValue={saisi?.shippingAddressLine1 ?? ''}
                    {...props}
                  />
                )}
              </Field>

              <Field
                label="Complément d'adresse"
                name="shippingAddressLine2"
                hint="Bâtiment, étage, code d'accès."
                error={errors.shippingAddressLine2}
              >
                {(props) => (
                  <input
                    type="text"
                    autoComplete="address-line2"
                    defaultValue={saisi?.shippingAddressLine2 ?? ''}
                    {...props}
                  />
                )}
              </Field>

              <div className="grid gap-5 sm:grid-cols-[10rem_1fr]">
                <Field
                  label="Code postal"
                  name="shippingPostalCode"
                  required
                  error={errors.shippingPostalCode}
                >
                  {(props) => (
                    <input
                      type="text"
                      inputMode="numeric"
                      autoComplete="postal-code"
                      maxLength={5}
                      defaultValue={saisi?.shippingPostalCode ?? ''}
                      {...props}
                    />
                  )}
                </Field>

                <Field
                  label="Ville"
                  name="shippingCity"
                  required
                  error={errors.shippingCity}
                >
                  {(props) => (
                    <input
                      type="text"
                      autoComplete="address-level2"
                      defaultValue={saisi?.shippingCity ?? ''}
                      {...props}
                    />
                  )}
                </Field>
              </div>

              <p className="text-sm text-ink-soft">
                Livraison en France métropolitaine uniquement.
              </p>
            </div>
          ) : (
            <div className="border-t border-line pt-5">
              <fieldset>
                <legend className="text-sm font-medium">
                  Où souhaitez-vous retirer votre commande&nbsp;?
                </legend>

                <div className="mt-3 space-y-3">
                  {pickupPoints.map((point, index) => (
                    <label
                      key={point.id}
                      className="flex cursor-pointer gap-3 border border-line p-4 has-checked:border-ink"
                    >
                      <input
                        type="radio"
                        name="pickupPointId"
                        value={point.id}
                        defaultChecked={
                          saisi?.pickupPointId
                            ? saisi.pickupPointId === point.id
                            : index === 0
                        }
                        className="mt-1"
                      />
                      <span className="text-sm">
                        <span className="block font-medium">{point.name}</span>
                        <span className="mt-1 block text-ink-soft">
                          {point.addressLine1}
                          {point.addressLine2 && <>, {point.addressLine2}</>}
                          <br />
                          {point.postalCode} {point.city}
                          {point.hours && (
                            <>
                              <br />
                              {point.hours}
                            </>
                          )}
                        </span>
                      </span>
                    </label>
                  ))}
                </div>

                {errors.pickupPointId && (
                  <p className="mt-2 text-sm text-accent">{errors.pickupPointId}</p>
                )}
              </fieldset>
            </div>
          )}

          <input type="hidden" name="fulfilment" value={fulfilment} />
        </section>

        {/* --- Récapitulatif des articles ---------------------------------- */}
        <section>
          <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
            Votre panier
          </h2>
          <div className="mt-4">
            <CartLines lines={cart.lines} editable={false} />
          </div>
          <p className="mt-3 text-sm">
            <a href="/panier" className="underline underline-offset-4">
              Modifier le panier
            </a>
          </p>
        </section>
      </div>

      <aside className="lg:sticky lg:top-28 lg:self-start">
        <CartSummary
          subtotalCents={cart.subtotalCents}
          shippingCents={shippingCents}
        >
          <Button type="submit" size="lg" className="w-full" disabled={isPending}>
            {isPending ? 'Redirection…' : 'Payer par carte'}
          </Button>
          <p className="mt-3 text-xs text-ink-soft">
            Vous allez être redirigé vers Stripe pour le paiement. Vos
            coordonnées bancaires ne transitent pas par nos serveurs.
          </p>
        </CartSummary>
      </aside>
    </form>
  );
}

function FulfilmentChoice({
  value,
  checked,
  onChange,
  title,
  detail,
  disabled = false,
}: {
  value: string;
  checked: boolean;
  onChange: (value: string) => void;
  title: string;
  detail: string;
  disabled?: boolean;
}) {
  return (
    <label
      className={`flex cursor-pointer items-start gap-3 border p-4 ${
        checked ? 'border-ink' : 'border-line'
      } ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
    >
      <input
        type="radio"
        name="mode-remise"
        value={value}
        checked={checked}
        disabled={disabled}
        onChange={() => onChange(value)}
        className="mt-1"
      />
      <span className="text-sm">
        <span className="block font-medium">{title}</span>
        <span className="mt-0.5 block text-ink-soft">
          {disabled ? 'Aucun point disponible' : detail}
        </span>
      </span>
    </label>
  );
}
