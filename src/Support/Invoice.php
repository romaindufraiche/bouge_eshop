<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;

/**
 * La facture d'une commande, en PDF.
 *
 * Elle porte les mentions que le code de commerce exige d'une vente à un
 * particulier : identité complète du vendeur, numéro séquentiel, dates,
 * désignation des articles, prix unitaire hors taxes, taux et montant de la
 * TVA par taux, total toutes taxes comprises.
 *
 * Les montants viennent de la commande, jamais du catalogue : les lignes ont
 * recopié leur prix, leur libellé et leur taux à l'achat, et la facture reste
 * donc fidèle même si le produit change ou disparaît ensuite.
 */
final class Invoice
{
    private const MARGE = 56.0;
    private const DROITE = Pdf::LARGEUR - self::MARGE;

    /**
     * Attribue un numéro de facture à la commande si elle n'en a pas encore.
     *
     * Le numéro doit être séquentiel et sans rupture : il est donc pris sur
     * un compteur propre aux factures, et non sur l'identifiant de commande
     * qui saute à chaque panier abandonné. La transaction et le verrou
     * empêchent deux éditions simultanées d'obtenir le même numéro.
     *
     * @param array<string, mixed> $commande
     */
    public static function assignNumber(int $orderId): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('SELECT invoice_number FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$orderId]);
            $existant = $statement->fetchColumn();

            if ($existant !== false && $existant !== null) {
                $pdo->commit();

                return (int) $existant;
            }

            $suivant = (int) $pdo->query('SELECT COALESCE(MAX(invoice_number), 0) + 1 FROM orders')->fetchColumn();

            Database::run(
                'UPDATE orders SET invoice_number = ?, invoiced_at = NOW() WHERE id = ?',
                [$suivant, $orderId]
            );

            $pdo->commit();

            return $suivant;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /** Le numéro tel qu'il s'imprime : FA-2026-0007. */
    public static function reference(int $numero, ?string $date): string
    {
        $annee = $date === null ? date('Y') : date('Y', strtotime($date));

        return sprintf('FA-%s-%04d', $annee, $numero);
    }

    /**
     * Fabrique le document.
     *
     * @param array<string, mixed> $commande Avec ses lignes, telle que la rend OrderRepository::find().
     */
    public static function render(array $commande): string
    {
        $shop = Config::shopAll();
        $legal = $shop['legal'] ?? [];

        if (($commande['paid_at'] ?? null) === null) {
            // Facturer ce qui n'a pas été payé n'aurait aucun sens, et la
            // pièce circulerait ensuite sans contrepartie.
            throw new RuntimeException("Cette commande n'est pas payée : aucune facture ne peut être émise.");
        }

        $numero = self::assignNumber((int) $commande['id']);
        $pdf = new Pdf();
        $y = Pdf::HAUTEUR - self::MARGE;

        // --- En-tête ---------------------------------------------------------
        $pdf->texte(self::MARGE, $y, mb_strtoupper((string) $shop['name_mark']), 20, Pdf::GRAS);
        $pdf->texte(
            self::MARGE + $pdf->largeurTexte(mb_strtoupper((string) $shop['name_mark']), 20, Pdf::GRAS) + 7,
            $y + 2,
            mb_strtoupper((string) $shop['name_suffix']),
            10,
            Pdf::GRAS,
            '0.35 0.27 0.23'
        );

        $pdf->texteDroite(self::DROITE, $y + 6, 'FACTURE', 16, Pdf::GRAS);
        $pdf->texteDroite(self::DROITE, $y - 10, self::reference($numero, $commande['invoiced_at'] ?? null), 10);

        $y -= 44;

        // --- Vendeur et client, côte à côte -----------------------------------
        $colonne = self::MARGE + 265;

        $pdf->texte(self::MARGE, $y, 'VENDEUR', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $pdf->texte($colonne, $y, 'CLIENT', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $y -= 14;

        $vendeur = array_filter([
            trim(((string) ($legal['company'] ?? '')) . ' — ' . ((string) ($legal['form'] ?? '')), ' —'),
            'Capital ' . (string) ($legal['capital'] ?? ''),
            (string) ($legal['address'] ?? ''),
            'SIRET ' . (string) ($legal['siret'] ?? ''),
            'RCS ' . (string) ($legal['rcs'] ?? '') . ' ' . (string) ($legal['siren'] ?? ''),
            'TVA ' . (string) ($legal['vat'] ?? ''),
            (string) ($shop['email'] ?? ''),
        ], static fn (string $l): bool => trim($l, ' —') !== '');

        $client = array_filter([
            (string) $commande['customer_name'],
            (string) $commande['email'],
            (string) ($commande['shipping_address_line1'] ?? ''),
            (string) ($commande['shipping_address_line2'] ?? ''),
            trim(((string) ($commande['shipping_postal_code'] ?? '')) . ' ' . ((string) ($commande['shipping_city'] ?? ''))),
        ], static fn (string $l): bool => trim($l) !== '');

        $ligneDepart = $y;

        foreach ($vendeur as $ligne) {
            $pdf->texte(self::MARGE, $y, $ligne, 8.5);
            $y -= 11;
        }

        $basVendeur = $y;
        $y = $ligneDepart;

        foreach ($client as $ligne) {
            $pdf->texte($colonne, $y, $ligne, 8.5);
            $y -= 11;
        }

        $y = min($basVendeur, $y) - 18;

        // --- Dates -------------------------------------------------------------
        $pdf->texte(self::MARGE, $y, 'Commande ' . (string) $commande['reference']
            . '   ·   Payée le ' . date('d/m/Y', strtotime((string) $commande['paid_at'])), 9);
        $y -= 24;

        // --- Lignes -------------------------------------------------------------
        $colQte = self::DROITE - 190;
        $colPuHt = self::DROITE - 140;
        $colTva = self::DROITE - 72;
        $colTotal = self::DROITE;

        $pdf->texte(self::MARGE, $y, 'DÉSIGNATION', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $pdf->texteDroite($colQte, $y, 'QTÉ', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $pdf->texteDroite($colPuHt, $y, 'P.U. HT', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $pdf->texteDroite($colTva, $y, 'TVA', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $pdf->texteDroite($colTotal, $y, 'TOTAL TTC', 7.5, Pdf::GRAS, '0.35 0.27 0.23');
        $y -= 6;
        $pdf->filet(self::MARGE, $y, self::DROITE, $y, 0.8, '0.14 0.14 0.14');
        $y -= 16;

        /** @var array<int, array{base: int, tva: int}> $parTaux */
        $parTaux = [];

        foreach ($commande['items'] as $ligne) {
            $taux = (int) ($ligne['vat_rate_bp'] ?? 2000);
            $ttc = (int) $ligne['line_total_cents'];
            $ht = self::horsTaxes($ttc, $taux);

            $parTaux[$taux] ??= ['base' => 0, 'tva' => 0];
            $parTaux[$taux]['base'] += $ht;
            $parTaux[$taux]['tva'] += $ttc - $ht;

            $libelle = (string) $ligne['product_name'];

            if (!empty($ligne['variant_label'])) {
                $libelle .= ' — ' . (string) $ligne['variant_label'];
            }

            // La désignation est tronquée plutôt que repliée : une ligne par
            // article garde la facture lisible, et le nom complet figure déjà
            // sur la commande.
            $pdf->texte(self::MARGE, $y, self::tronquer($libelle, 46), 9);
            $pdf->texteDroite($colQte, $y, (string) (int) $ligne['quantity'], 9);
            $pdf->texteDroite($colPuHt, $y, Money::format(self::horsTaxes((int) $ligne['unit_price_cents'], $taux)), 9);
            $pdf->texteDroite($colTva, $y, self::tauxLisible($taux), 9);
            $pdf->texteDroite($colTotal, $y, Money::format($ttc), 9);

            $y -= 8;
            $pdf->filet(self::MARGE, $y, self::DROITE, $y);
            $y -= 14;
        }

        // Le port suit le taux normal.
        $port = (int) ($commande['shipping_cents'] ?? 0);

        if ($port > 0) {
            $tauxPort = 2000;
            $htPort = self::horsTaxes($port, $tauxPort);
            $parTaux[$tauxPort] ??= ['base' => 0, 'tva' => 0];
            $parTaux[$tauxPort]['base'] += $htPort;
            $parTaux[$tauxPort]['tva'] += $port - $htPort;

            $pdf->texte(self::MARGE, $y, self::modeDeRemise((string) $commande['fulfilment']), 9);
            $pdf->texteDroite($colQte, $y, '1', 9);
            $pdf->texteDroite($colPuHt, $y, Money::format($htPort), 9);
            $pdf->texteDroite($colTva, $y, self::tauxLisible($tauxPort), 9);
            $pdf->texteDroite($colTotal, $y, Money::format($port), 9);
            $y -= 8;
            $pdf->filet(self::MARGE, $y, self::DROITE, $y);
            $y -= 14;
        }

        // --- Récapitulatif de TVA ------------------------------------------------
        $y -= 10;
        ksort($parTaux);
        $totalHt = 0;
        $totalTva = 0;

        foreach ($parTaux as $taux => $montants) {
            $totalHt += $montants['base'];
            $totalTva += $montants['tva'];

            $pdf->texteDroite($colPuHt, $y, 'Base HT ' . self::tauxLisible((int) $taux), 8.5, Pdf::REGULIER, '0.35 0.27 0.23');
            $pdf->texteDroite($colTva, $y, Money::format($montants['base']), 8.5, Pdf::REGULIER, '0.35 0.27 0.23');
            $pdf->texteDroite($colTotal, $y, 'TVA ' . Money::format($montants['tva']), 8.5, Pdf::REGULIER, '0.35 0.27 0.23');
            $y -= 13;
        }

        $y -= 4;
        $pdf->texteDroite($colTva, $y, 'Total HT', 9.5);
        $pdf->texteDroite($colTotal, $y, Money::format($totalHt), 9.5);
        $y -= 14;
        $pdf->texteDroite($colTva, $y, 'TVA', 9.5);
        $pdf->texteDroite($colTotal, $y, Money::format($totalTva), 9.5);
        $y -= 8;
        $pdf->filet($colTva - 60, $y, self::DROITE, $y, 0.8, '0.14 0.14 0.14');
        $y -= 17;
        $pdf->texteDroite($colTva, $y, 'TOTAL TTC', 11, Pdf::GRAS);
        $pdf->texteDroite($colTotal, $y, Money::format((int) $commande['total_cents']), 11, Pdf::GRAS);

        // --- Pied ----------------------------------------------------------------
        $pied = self::MARGE + 34;
        $pdf->filet(self::MARGE, $pied + 42, self::DROITE, $pied + 42);
        $pdf->texte(self::MARGE, $pied + 28, 'Facture acquittée. Paiement par carte bancaire le '
            . date('d/m/Y', strtotime((string) $commande['paid_at'])) . '.', 8);
        $pdf->texte(self::MARGE, $pied + 16, 'En cas de retard de paiement : pénalités au taux de trois fois le taux '
            . "d'intérêt légal et indemnité forfaitaire de 40 € pour frais de recouvrement.", 7.5, Pdf::REGULIER, '0.35 0.27 0.23');
        $pdf->texte(self::MARGE, $pied + 4, (string) ($legal['company'] ?? '') . ' — '
            . (string) ($legal['address'] ?? '') . ' — SIRET ' . (string) ($legal['siret'] ?? ''), 7.5, Pdf::REGULIER, '0.35 0.27 0.23');

        return $pdf->rendu();
    }

    /** Le nom du fichier proposé au téléchargement. */
    public static function filename(array $commande): string
    {
        $numero = (int) ($commande['invoice_number'] ?? 0);

        return ($numero > 0 ? self::reference($numero, $commande['invoiced_at'] ?? null) : 'facture')
            . '-' . (string) $commande['reference'] . '.pdf';
    }

    /**
     * Le montant hors taxes d'un prix TTC, en centimes.
     *
     * Arrondi au centime le plus proche : c'est le TTC qui fait foi — c'est
     * lui que le client a payé — et le HT s'en déduit, jamais l'inverse.
     */
    private static function horsTaxes(int $ttcCentimes, int $tauxPointsDeBase): int
    {
        return (int) round($ttcCentimes * 10000 / (10000 + $tauxPointsDeBase));
    }

    private static function tauxLisible(int $pointsDeBase): string
    {
        $taux = $pointsDeBase / 100;

        return rtrim(rtrim(number_format($taux, 1, ',', ''), '0'), ',') . ' %';
    }

    private static function modeDeRemise(string $fulfilment): string
    {
        return match ($fulfilment) {
            Status::RELAY => 'Livraison en point relais',
            Status::PICKUP => 'Retrait sur place',
            default => 'Livraison à domicile',
        };
    }

    private static function tronquer(string $texte, int $maximum): string
    {
        return mb_strlen($texte) <= $maximum ? $texte : mb_substr($texte, 0, $maximum - 1) . '…';
    }
}
