<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Valeurs contrôlées de l'application.
 *
 * Elles sont stockées en VARCHAR dans la base plutôt qu'en ENUM : ajouter un
 * statut ne demande alors pas de modifier la structure des tables. Ce fichier
 * est la référence unique des valeurs autorisées et de leurs libellés.
 */
final class Status
{
    // --- Statut de publication d'un produit ---------------------------------

    public const PRODUCT_DRAFT = 'brouillon';
    public const PRODUCT_PUBLISHED = 'en_ligne';

    /** @return array<string, string> */
    public static function productStatuses(): array
    {
        return [
            self::PRODUCT_DRAFT     => 'Brouillon',
            self::PRODUCT_PUBLISHED => 'En ligne',
        ];
    }

    // --- Mode de remise ------------------------------------------------------

    public const DELIVERY = 'livraison';
    public const PICKUP = 'retrait';

    /** @return array<string, string> */
    public static function fulfilments(): array
    {
        return [
            self::DELIVERY => 'Livraison à domicile',
            self::PICKUP   => 'Retrait sur place',
        ];
    }

    // --- Statut d'une commande -----------------------------------------------

    /** Commande créée, le client n'a pas encore payé. */
    public const ORDER_PENDING = 'en_attente';
    /** Paiement confirmé par Stripe. */
    public const ORDER_PAID = 'payee';
    public const ORDER_PREPARING = 'en_preparation';
    /** Expédiée — cas d'une livraison. */
    public const ORDER_SHIPPED = 'expediee';
    /** Retirée par le client — cas d'un retrait sur place. */
    public const ORDER_COLLECTED = 'retiree';
    public const ORDER_CANCELLED = 'annulee';

    /** @return array<string, string> */
    public static function orderStatuses(): array
    {
        return [
            self::ORDER_PENDING   => 'En attente de paiement',
            self::ORDER_PAID      => 'Payée',
            self::ORDER_PREPARING => 'En préparation',
            self::ORDER_SHIPPED   => 'Expédiée',
            self::ORDER_COLLECTED => 'Retirée',
            self::ORDER_CANCELLED => 'Annulée',
        ];
    }

    /**
     * Statuts proposés dans l'administration, selon le mode de remise choisi
     * par le client : inutile de proposer « Expédiée » sur un retrait.
     *
     * @return array<string, string>
     */
    public static function orderStatusesFor(string $fulfilment): array
    {
        $labels = self::orderStatuses();
        $final = $fulfilment === self::PICKUP ? self::ORDER_COLLECTED : self::ORDER_SHIPPED;

        return [
            self::ORDER_PENDING   => $labels[self::ORDER_PENDING],
            self::ORDER_PAID      => $labels[self::ORDER_PAID],
            self::ORDER_PREPARING => $labels[self::ORDER_PREPARING],
            $final                => $labels[$final],
            self::ORDER_CANCELLED => $labels[self::ORDER_CANCELLED],
        ];
    }

    public static function orderLabel(string $status): string
    {
        return self::orderStatuses()[$status] ?? $status;
    }
}
