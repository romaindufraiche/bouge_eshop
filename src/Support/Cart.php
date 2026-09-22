<?php

declare(strict_types=1);

namespace Bouge\Support;

use Bouge\Repository\ProductRepository;

/**
 * Panier.
 *
 * Il vit dans la session PHP et ne contient QUE des identifiants et des
 * quantités. Les libellés, les prix, les promotions et les stocks sont relus
 * en base à chaque affichage et avant chaque paiement : un prix modifié dans
 * l'administration est donc immédiatement répercuté, et un panier bricolé ne
 * permet pas d'acheter au prix de son choix.
 */
final class Cart
{
    private const KEY = 'cart';

    /** @return array<int, array{product_id: int, variant_id: ?int, quantity: int}> */
    public static function lines(): array
    {
        $lines = Session::get(self::KEY, []);

        return is_array($lines) ? array_values($lines) : [];
    }

    public static function count(): int
    {
        return array_sum(array_map(
            static fn (array $line): int => $line['quantity'],
            self::lines()
        ));
    }

    public static function add(int $productId, ?int $variantId, int $quantity): void
    {
        $lines = self::lines();
        $key = self::key($productId, $variantId);
        $max = (int) Config::shop('cart.max_quantity_per_line', 20);

        foreach ($lines as $index => $line) {
            if (self::key($line['product_id'], $line['variant_id']) === $key) {
                $lines[$index]['quantity'] = min($line['quantity'] + $quantity, $max);
                Session::set(self::KEY, $lines);

                return;
            }
        }

        if (count($lines) >= (int) Config::shop('cart.max_lines', 50)) {
            return;
        }

        $lines[] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity'   => max(1, min($quantity, $max)),
        ];

        Session::set(self::KEY, $lines);
    }

    public static function setQuantity(int $productId, ?int $variantId, int $quantity): void
    {
        $key = self::key($productId, $variantId);
        $max = (int) Config::shop('cart.max_quantity_per_line', 20);

        $lines = array_values(array_filter(
            array_map(
                static function (array $line) use ($key, $quantity, $max): ?array {
                    if (self::key($line['product_id'], $line['variant_id']) !== $key) {
                        return $line;
                    }

                    if ($quantity <= 0) {
                        return null;
                    }

                    $line['quantity'] = min($quantity, $max);

                    return $line;
                },
                self::lines()
            ),
            static fn (?array $line): bool => $line !== null
        ));

        Session::set(self::KEY, $lines);
    }

    public static function remove(int $productId, ?int $variantId): void
    {
        self::setQuantity($productId, $variantId, 0);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Détail chiffré du panier, relu en base.
     *
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal_cents: int,
     *     issues: array<int, string>,
     *     empty: bool
     * }
     */
    public static function resolve(): array
    {
        $stored = self::lines();

        if ($stored === []) {
            return ['lines' => [], 'subtotal_cents' => 0, 'issues' => [], 'empty' => true];
        }

        $repository = new ProductRepository();
        $resolved = [];
        $issues = [];
        $changed = false;

        foreach ($stored as $line) {
            $product = $repository->find($line['product_id']);

            // Produit supprimé, repassé en brouillon, ou devenu introuvable.
            if ($product === null || $product['status'] !== Status::PRODUCT_PUBLISHED) {
                $issues[] = "Un article n'est plus disponible et a été retiré du panier.";
                $changed = true;
                continue;
            }

            // Un produit vendu par un tiers n'a pas de bouton « ajouter au
            // panier ». Le refus est revérifié ici : l'interface ne suffit pas.
            if (($product['external_url'] ?? null) !== null && $product['external_url'] !== '') {
                $issues[] = "« {$product['name']} » est vendu par un revendeur : il ne peut pas être commandé ici.";
                $changed = true;
                continue;
            }

            $variant = null;
            if ($line['variant_id'] !== null) {
                foreach ($product['variants'] as $candidate) {
                    if ((int) $candidate['id'] === $line['variant_id']) {
                        $variant = $candidate;
                        break;
                    }
                }

                if ($variant === null) {
                    $issues[] = "Une déclinaison de « {$product['name']} » n'existe plus et a été retirée.";
                    $changed = true;
                    continue;
                }
            }

            $stock = (int) ($variant['stock'] ?? $product['stock']);
            $variantLabel = $variant === null
                ? null
                : Pricing::variantLabel($variant['size'], $variant['color']);
            $label = $variantLabel === null
                ? $product['name']
                : "{$product['name']} ({$variantLabel})";

            if ($stock <= 0) {
                $issues[] = "« {$label} » est en rupture de stock et a été retiré.";
                $changed = true;
                continue;
            }

            // On plafonne à ce qui reste réellement en stock.
            $quantity = min($line['quantity'], $stock);
            if ($quantity < $line['quantity']) {
                $issues[] = "« {$label} » : quantité ramenée à {$quantity}, c'est tout ce qu'il reste.";
                $changed = true;
            }

            $price = Pricing::forVariant($product, $variant);

            $resolved[] = [
                'product_id'       => (int) $product['id'],
                'variant_id'       => $variant === null ? null : (int) $variant['id'],
                'quantity'         => $quantity,
                'name'             => $product['name'],
                'slug'             => $product['slug'],
                'variant_label'    => $variantLabel,
                'image_url'        => $product['cover']['url'] ?? null,
                'unit_price_cents' => $price->cents,
                'compare_at_cents' => $price->compareAtCents,
                'line_total_cents' => $price->cents * $quantity,
                'available_stock'  => $stock,
            ];
        }

        // Le panier stocké est réaligné sur ce qui est réellement commandable :
        // sans cela le client reverrait le même avertissement à chaque page.
        if ($changed) {
            Session::set(self::KEY, array_map(
                static fn (array $line): array => [
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'quantity'   => $line['quantity'],
                ],
                $resolved
            ));
        }

        $subtotal = array_sum(array_map(
            static fn (array $line): int => $line['line_total_cents'],
            $resolved
        ));

        return [
            'lines'          => $resolved,
            'subtotal_cents' => $subtotal,
            'issues'         => $issues,
            'empty'          => $resolved === [],
        ];
    }

    private static function key(int $productId, ?int $variantId): string
    {
        return $productId . '::' . ($variantId ?? '');
    }
}
