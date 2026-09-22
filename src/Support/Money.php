<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Manipulation des montants.
 *
 * Tous les prix circulent en CENTIMES (entiers) dans l'application et la base.
 * Les conversions en euros n'ont lieu qu'à l'affichage et dans les
 * formulaires. Aucun calcul n'est fait en virgule flottante.
 */
final class Money
{
    /** 1490 -> "14,90 €" */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }

    /** 1490 -> "14.90", valeur d'un champ de formulaire en euros. */
    public static function toInput(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * "14,90" ou "14.90" -> 1490.
     * Renvoie null si la saisie n'est pas un montant valide, pour que
     * l'appelant affiche un message clair plutôt qu'enregistrer n'importe quoi.
     */
    public static function fromInput(string $value): ?int
    {
        $normalised = str_replace([' ', ','], ['', '.'], trim($value));

        if ($normalised === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $normalised)) {
            return null;
        }

        return (int) round(((float) $normalised) * 100);
    }
}
