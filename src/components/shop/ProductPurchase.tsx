'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import { PriceTag } from '@/components/shop/PriceTag';
import { Button } from '@/components/ui/Button';
import type { EffectivePrice } from '@/lib/pricing';

/**
 * Variante telle qu'elle arrive du serveur : le prix effectif est déjà calculé
 * côté serveur pour que client et serveur affichent exactement le même montant,
 * quelle que soit l'heure locale du visiteur.
 */
export type PurchasableVariant = {
  id: string;
  size: string | null;
  color: string | null;
  stock: number;
  price: EffectivePrice;
};

export function ProductPurchase({
  productId,
  basePrice,
  baseStock,
  variants,
}: {
  productId: string;
  basePrice: EffectivePrice;
  /** Stock du produit lui-même, utilisé uniquement s'il n'a pas de variante. */
  baseStock: number;
  variants: PurchasableVariant[];
}) {
  const hasVariants = variants.length > 0;

  // Dimensions réellement présentes : un produit décliné seulement en couleur
  // n'affiche pas de sélecteur de taille.
  const sizes = useMemo(
    () => unique(variants.map((variant) => variant.size)),
    [variants],
  );
  const colors = useMemo(
    () => unique(variants.map((variant) => variant.color)),
    [variants],
  );

  const [selectedSize, setSelectedSize] = useState<string | null>(null);
  const [selectedColor, setSelectedColor] = useState<string | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [feedback, setFeedback] = useState<'idle' | 'added'>('idle');

  const { add } = useCart();

  // Variante correspondant aux choix en cours.
  const selectedVariant = useMemo(() => {
    if (!hasVariants) return null;

    return (
      variants.find(
        (variant) =>
          (sizes.length === 0 || variant.size === selectedSize) &&
          (colors.length === 0 || variant.color === selectedColor),
      ) ?? null
    );
  }, [hasVariants, variants, sizes.length, colors.length, selectedSize, selectedColor]);

  const needsChoice = hasVariants && selectedVariant === null;
  const price = selectedVariant?.price ?? basePrice;
  const stock = hasVariants ? (selectedVariant?.stock ?? 0) : baseStock;
  const isOutOfStock = !needsChoice && stock <= 0;

  function handleAdd() {
    if (needsChoice || isOutOfStock) return;

    add({
      productId,
      variantId: selectedVariant?.id ?? null,
      quantity: Math.min(quantity, stock),
    });
    setFeedback('added');
  }

  /** Une taille est proposée uniquement si au moins une variante existe avec. */
  function isSizeAvailable(size: string): boolean {
    return variants.some(
      (variant) =>
        variant.size === size &&
        (colors.length === 0 || selectedColor === null || variant.color === selectedColor) &&
        variant.stock > 0,
    );
  }

  function isColorAvailable(color: string): boolean {
    return variants.some(
      (variant) =>
        variant.color === color &&
        (sizes.length === 0 || selectedSize === null || variant.size === selectedSize) &&
        variant.stock > 0,
    );
  }

  return (
    <div className="space-y-6">
      <PriceTag price={price} size="lg" />

      {colors.length > 0 && (
        <OptionGroup
          label="Couleur"
          options={colors}
          selected={selectedColor}
          isAvailable={isColorAvailable}
          onSelect={(value) => {
            setSelectedColor(value);
            setFeedback('idle');
          }}
        />
      )}

      {sizes.length > 0 && (
        <OptionGroup
          label="Taille"
          options={sizes}
          selected={selectedSize}
          isAvailable={isSizeAvailable}
          onSelect={(value) => {
            setSelectedSize(value);
            setFeedback('idle');
          }}
        />
      )}

      {/* Quantité + ajout au panier */}
      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm">
          <span className="text-ink-soft">Quantité</span>
          <input
            type="number"
            min={1}
            max={Math.max(stock, 1)}
            value={quantity}
            onChange={(event) => {
              const next = Number(event.target.value);
              setQuantity(Number.isFinite(next) && next >= 1 ? Math.trunc(next) : 1);
              setFeedback('idle');
            }}
            className="w-20 rounded-sm border border-line bg-cream px-3 py-2.5 text-center tabular-nums"
          />
        </label>

        <Button
          onClick={handleAdd}
          size="lg"
          disabled={needsChoice || isOutOfStock}
          className="flex-1 sm:flex-none"
        >
          {isOutOfStock ? 'Rupture de stock' : 'Ajouter au panier'}
        </Button>
      </div>

      {/* Messages d'état, formulés sans jargon */}
      {needsChoice && (
        <p className="text-sm text-ink-soft" role="status">
          Choisissez {colors.length > 0 && sizes.length > 0
            ? 'une couleur et une taille'
            : colors.length > 0
              ? 'une couleur'
              : 'une taille'}{' '}
          pour continuer.
        </p>
      )}

      {!needsChoice && !isOutOfStock && stock <= 5 && (
        <p className="text-sm text-accent" role="status">
          Plus que {stock} en stock.
        </p>
      )}

      {feedback === 'added' && (
        <p className="text-sm" role="status">
          Ajouté au panier.{' '}
          <Link href="/panier" className="underline underline-offset-4">
            Voir le panier
          </Link>
        </p>
      )}
    </div>
  );
}

function OptionGroup({
  label,
  options,
  selected,
  isAvailable,
  onSelect,
}: {
  label: string;
  options: string[];
  selected: string | null;
  isAvailable: (option: string) => boolean;
  onSelect: (option: string) => void;
}) {
  return (
    <fieldset>
      <legend className="text-xs uppercase tracking-widest text-ink-soft">
        {label}
      </legend>
      <div className="mt-2 flex flex-wrap gap-2">
        {options.map((option) => {
          const available = isAvailable(option);
          const isSelected = selected === option;

          return (
            <button
              key={option}
              type="button"
              onClick={() => onSelect(option)}
              disabled={!available}
              aria-pressed={isSelected}
              className={`rounded-sm border px-4 py-2 text-sm transition-colors ${
                isSelected
                  ? 'border-ink bg-ink text-cream'
                  : 'border-line hover:border-ink'
              } ${!available ? 'cursor-not-allowed text-ink-soft line-through opacity-50' : ''}`}
            >
              {option}
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}

/** Valeurs distinctes, sans les null, dans leur ordre d'apparition. */
function unique(values: (string | null)[]): string[] {
  return [...new Set(values.filter((value): value is string => value !== null))];
}
