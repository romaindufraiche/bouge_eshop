<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Ce qui réclame l'attention dans l'administration.
 *
 * Deux compteurs, affichés en pastille dans la barre latérale : les produits
 * qui ne sont plus vendables, et les commandes payées qu'on n'a pas encore
 * commencé à préparer.
 *
 * Les valeurs sont mémorisées le temps de la requête : la barre latérale est
 * rendue une fois par page, mais rien n'empêche qu'on les redemande.
 */
final class AdminAlerts
{
    private static ?int $rupture = null;
    private static ?int $nouvelles = null;

    /**
     * Produits en ligne dont il ne reste rien à vendre.
     *
     * Un produit à déclinaisons compte pour épuisé quand toutes ses tailles le
     * sont : son propre stock ne veut alors rien dire, ce sont les
     * déclinaisons qui font foi. C'est la même règle que celle appliquée à la
     * boutique, sans quoi la pastille annoncerait des ruptures que le client
     * ne voit pas.
     */
    public static function outOfStock(): int
    {
        return self::$rupture ??= (int) Database::connection()->query(
            "SELECT COUNT(*) FROM products p
              WHERE p.status = '" . Status::PRODUCT_PUBLISHED . "'
                AND p.external_url IS NULL
                AND CASE
                        WHEN EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)
                        THEN NOT EXISTS (
                            SELECT 1 FROM product_variants v
                             WHERE v.product_id = p.id AND v.stock > 0
                        )
                        ELSE p.stock <= 0
                    END"
        )->fetchColumn();
    }

    /**
     * Commandes payées qu'on n'a pas encore prises en main.
     *
     * « Payée » est l'état dans lequel le webhook les laisse : dès que la
     * préparation commence, le statut change et la pastille retombe. C'est
     * donc bien une file d'attente, pas un total.
     */
    public static function newOrders(): int
    {
        return self::$nouvelles ??= (int) Database::run(
            'SELECT COUNT(*) FROM orders WHERE status = ?',
            [Status::ORDER_PAID]
        )->fetchColumn();
    }
}
