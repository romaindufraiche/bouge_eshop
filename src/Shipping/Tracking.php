<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * Noms et liens de suivi des transporteurs.
 *
 * Deux problèmes qui n'en font qu'un. Boxtal désigne les transporteurs par un
 * code de quatre lettres — MONR, COLI, CHRP — et ce code se retrouvait tel
 * quel sous les yeux du client : « MONR vous préviendra dès que le colis sera
 * arrivé ». Et le numéro de suivi s'affichait en texte mort, à recopier à la
 * main sur le site du transporteur.
 *
 * Cette classe traduit l'un et fabrique l'autre. Elle ne connaît que ce
 * qu'elle sait : un code inconnu ressort inchangé plutôt que de disparaître,
 * et un transporteur sans URL connue n'a simplement pas de lien.
 */
final class Tracking
{
    /**
     * Code opérateur → nom commercial et adresse de suivi.
     *
     * `%s` reçoit le numéro de suivi, encodé pour une URL.
     *
     * @var array<string, array{nom: string, url: string}>
     */
    private const TRANSPORTEURS = [
        'MONR' => ['nom' => 'Mondial Relay', 'url' => 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition=%s'],
        'POFR' => ['nom' => 'Colissimo',     'url' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%s'],
        'COLI' => ['nom' => 'Colissimo',     'url' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%s'],
        'SOGP' => ['nom' => 'Relais Colis',  'url' => 'https://www.relaiscolis.com/suivi-de-colis/?numero=%s'],
        'CHRP' => ['nom' => 'Chronopost',    'url' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=%s'],
        'UPSE' => ['nom' => 'UPS',           'url' => 'https://www.ups.com/track?loc=fr_FR&tracknum=%s'],
        'DHLE' => ['nom' => 'DHL',           'url' => 'https://www.dhl.com/fr-fr/home/tracking.html?tracking-id=%s'],
        'DPDF' => ['nom' => 'DPD',           'url' => 'https://www.dpd.fr/trace/%s'],
        'GLSF' => ['nom' => 'GLS',           'url' => 'https://gls-group.com/FR/fr/suivi-colis?match=%s'],
    ];

    /**
     * Le nom lisible d'un transporteur, à partir de son code ou de son nom.
     *
     * Accepte les deux parce que la base contient les deux : `relay_operator`
     * porte le code rendu par Boxtal, tandis que `tracking_carrier` porte un
     * nom déjà en clair, saisi à la main ou renvoyé avec l'étiquette.
     */
    public static function nom(?string $codeOuNom): string
    {
        $valeur = trim((string) $codeOuNom);

        if ($valeur === '') {
            return '';
        }

        return self::TRANSPORTEURS[strtoupper($valeur)]['nom'] ?? $valeur;
    }

    /**
     * L'adresse de suivi du colis, ou null si le transporteur est inconnu —
     * auquel cas le numéro reste affiché, simplement sans lien.
     */
    public static function lien(?string $transporteur, ?string $numero): ?string
    {
        $numero = trim((string) $numero);
        $cle = strtoupper(trim((string) $transporteur));

        if ($numero === '' || $cle === '') {
            return null;
        }

        if (!isset(self::TRANSPORTEURS[$cle])) {
            // `tracking_carrier` contient souvent le nom en clair plutôt que
            // le code : on retrouve l'entrée par son nom.
            foreach (self::TRANSPORTEURS as $entree) {
                if (strcasecmp($entree['nom'], (string) $transporteur) === 0) {
                    return sprintf($entree['url'], rawurlencode($numero));
                }
            }

            return null;
        }

        return sprintf(self::TRANSPORTEURS[$cle]['url'], rawurlencode($numero));
    }
}
